<?php
/**
 * Shared support for the return/userreturn/ajax front controller tests.
 *
 * - ExitRewritingLoader: return.php ends every push with a bare `exit;`. It is
 *   loaded through a stream wrapper that swaps each `exit` token for a
 *   `throw new TerminateException()` on the same line, so the push handler runs
 *   unchanged otherwise and coverage still maps to the real file and lines.
 * - RecordingLogger, ServiceContainer, SessionlessCookie, RecordingErrorReporter: doubles.
 * - ReturnFlowTestCase: loads the controllers and wires the doubles.
 */

namespace BuckarooTest\Unit\Controller\Front\ReturnFlow;

use Buckaroo\PrestaShop\Src\ErrorReporting\ErrorReporter;
use BuckarooTest\Stubs;
use BuckarooTest\TerminateException;
use BuckarooTest\TestCase;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use Symfony\Component\DependencyInjection\ContainerInterface;

require_once dirname(__DIR__, 5) . '/library/logger.php';

if (!class_exists(ExitRewritingLoader::class, false)) {
    /**
     * Stream wrapper for the file:// protocol that rewrites `exit` in one target file.
     * Every other file is passed through untouched.
     */
    final class ExitRewritingLoader
    {
        /** @var resource|null set by PHP */
        public $context;

        /** @var string|null real path of the file to rewrite */
        private static $target;

        /** @var array<string, bool> files loaded with the rewrite */
        private static $loaded = [];

        /** @var resource|null */
        private $handle;

        /** @var string|null rewritten source of the target */
        private $data;

        /** @var int */
        private $position = 0;

        public static function load(string $file): bool
        {
            $real = (string) realpath($file);
            if (isset(self::$loaded[$real])) {
                return true;
            }
            if (in_array($real, get_included_files(), true)) {
                return false; // already loaded unchanged by another test: exit cannot be intercepted
            }

            self::$target = $real;
            stream_wrapper_unregister('file');
            stream_wrapper_register('file', self::class);
            try {
                include_once $real;
            } finally {
                self::$target = null;
                stream_wrapper_restore('file');
            }

            return self::$loaded[$real] = true;
        }

        public static function rewrite(string $code): string
        {
            $out = '';
            foreach (token_get_all($code) as $token) {
                if (is_array($token) && $token[0] === T_EXIT) {
                    $out .= 'throw new \\' . TerminateException::class . '()';
                    continue;
                }
                $out .= is_array($token) ? $token[1] : $token;
            }

            return $out;
        }

        private static function native(callable $callback)
        {
            stream_wrapper_restore('file');
            try {
                return $callback();
            } finally {
                stream_wrapper_unregister('file');
                stream_wrapper_register('file', self::class);
            }
        }

        public function stream_open($path, $mode, $options, &$openedPath)
        {
            $real = realpath($path);
            if (self::$target !== null && $real === self::$target && strpbrk($mode, 'waxc+') === false) {
                $this->data = self::rewrite((string) self::native(function () use ($real) {
                    return file_get_contents($real);
                }));
                $openedPath = $real;

                return true;
            }

            $useIncludePath = (bool) ($options & STREAM_USE_PATH);
            $this->handle = self::native(function () use ($path, $mode, $useIncludePath) {
                return @fopen($path, $mode, $useIncludePath);
            });

            return $this->handle !== false;
        }

        public function stream_read($count)
        {
            if ($this->data !== null) {
                $chunk = (string) substr($this->data, $this->position, $count);
                $this->position += strlen($chunk);

                return $chunk;
            }

            return fread($this->handle, $count);
        }

        public function stream_write($data)
        {
            return $this->handle ? fwrite($this->handle, $data) : 0;
        }

        public function stream_eof()
        {
            return $this->data !== null ? $this->position >= strlen($this->data) : feof($this->handle);
        }

        public function stream_tell()
        {
            return $this->data !== null ? $this->position : ftell($this->handle);
        }

        public function stream_seek($offset, $whence = SEEK_SET)
        {
            if ($this->data === null) {
                return fseek($this->handle, $offset, $whence) === 0;
            }
            $length = strlen($this->data);
            if ($whence === SEEK_CUR) {
                $offset += $this->position;
            } elseif ($whence === SEEK_END) {
                $offset += $length;
            }
            if ($offset < 0 || $offset > $length) {
                return false;
            }
            $this->position = $offset;

            return true;
        }

        public function stream_stat()
        {
            if ($this->data !== null) {
                return ['size' => strlen($this->data), 'mode' => 0100644];
            }

            return fstat($this->handle);
        }

        public function stream_close()
        {
            if ($this->handle) {
                fclose($this->handle);
            }
        }

        public function stream_set_option($option, $arg1, $arg2)
        {
            return false;
        }

        public function stream_lock($operation)
        {
            return $this->handle ? flock($this->handle, $operation) : false;
        }

        public function stream_flush()
        {
            return $this->handle ? fflush($this->handle) : true;
        }

        public function url_stat($path, $flags)
        {
            return self::native(function () use ($path, $flags) {
                if ($flags & STREAM_URL_STAT_QUIET) {
                    return @stat($path);
                }

                return stat($path);
            });
        }
    }

    /** Logger that records instead of writing log files. */
    class RecordingLogger extends \Logger
    {
        /** @var array<int, array{0: string, 1: mixed, 2: mixed}> */
        public $entries = [];

        public function __construct()
        {
            parent::__construct(\Logger::INFO, 'test');
        }

        public function logInfo($info, $descr = null)
        {
            $this->entries[] = ['info', $info, $descr];
        }

        public function logError($info, $descr = null)
        {
            $this->entries[] = ['error', $info, $descr];
        }

        public function logWarn($info, $descr = null)
        {
            $this->entries[] = ['warn', $info, $descr];
        }

        public function logDebug($info, $descr = null)
        {
            $this->entries[] = ['debug', $info, $descr];
        }

        /** @return string[] messages logged at $level */
        public function messages(string $level): array
        {
            $found = [];
            foreach ($this->entries as $entry) {
                if ($entry[0] === $level) {
                    $found[] = (string) $entry[1];
                }
            }

            return $found;
        }
    }

    /** Symfony container double with a fixed service map. */
    class ServiceContainer implements ContainerInterface
    {
        /** @var array<string, mixed> */
        public $services = [];

        public function __construct(array $services = [])
        {
            $this->services = $services;
        }

        public function get($id, $invalidBehavior = 1)
        {
            if (!array_key_exists($id, $this->services)) {
                throw new \RuntimeException('Unknown service ' . $id);
            }
            $service = $this->services[$id];

            return $service instanceof \Closure ? $service() : $service;
        }

        public function has($id)
        {
            return array_key_exists($id, $this->services);
        }
    }

    /** Cookie the browser did not send (cross-site POST without SameSite=None). */
    class SessionlessCookie extends \Cookie
    {
        public function exists()
        {
            return false;
        }
    }

    /** ErrorReporter that records what would be reported instead of sending it. */
    class RecordingErrorReporter extends ErrorReporter
    {
        /** @var array<int, array{0: \Throwable, 1: string, 2: string, 3: array}> */
        public $reports = [];

        public function __construct()
        {
            parent::__construct(function () {
                return false;
            }, null, null, 'http://127.0.0.1:9/never', false);
        }

        public function report(\Throwable $exception, string $level = 'error', string $message = '', array $context = []): void
        {
            $this->reports[] = [$exception, $level, $message, $context];
        }
    }

    /**
     * Base class: loads the controllers, registers the module and container doubles,
     * and silences the header() warnings CLI raises once PHPUnit has printed output.
     */
    abstract class ReturnFlowTestCase extends TestCase
    {
        protected const WEBSITE_KEY = 'TESTWEBSITEKEY';
        protected const SECRET_KEY = 'return-flow-test-secret';

        /** @var bool */
        private static $returnRewritten = false;

        /** @var ServiceContainer */
        protected $container;

        /** @var \Module */
        protected $module;

        /** @var string[] header() calls swallowed by the error handler */
        protected $headerWarnings = [];

        /**
         * Loaded from setUp() rather than setUpBeforeClass() so the file-level
         * include statements run while coverage is collected.
         */
        private static function loadControllers(): void
        {
            $root = dirname(__DIR__, 5);
            require_once $root . '/buckaroo3.php';
            require_once $root . '/api/paymentmethods/responsefactory.php';
            require_once $root . '/controllers/front/common.php';
            self::$returnRewritten = ExitRewritingLoader::load($root . '/controllers/front/return.php');
            require_once $root . '/controllers/front/userreturn.php';
            require_once $root . '/controllers/front/ajax.php';
        }

        protected function setUp(): void
        {
            parent::setUp();
            self::loadControllers();

            $test = $this;
            set_error_handler(function ($errno, $errstr) use ($test) {
                if (strpos($errstr, 'Cannot modify header information') !== false) {
                    $test->headerWarnings[] = $errstr;

                    return true;
                }

                return false;
            }, E_WARNING);

            $this->module = new \Module();
            \Module::$instances['buckaroo3'] = $this->module;
            $this->container = new ServiceContainer();
            SymfonyContainer::$instance = $this->container;
            \Db::reset();

            \Configuration::updateValue('BUCKAROO_MERCHANT_KEY', self::WEBSITE_KEY);
            \Configuration::updateValue('BUCKAROO_SECRET_KEY', self::SECRET_KEY);

            Stubs::returns('OrderState::getOrderStates', []);
            Stubs::returns('Order::getByReference', function () {
                return $this->collection([]);
            });
            Stubs::returns('OrderPayment::getByOrderReference', []);
        }

        protected function tearDown(): void
        {
            restore_error_handler();
            SymfonyContainer::$instance = null;
            ErrorReporter::setInstance(null);
            parent::tearDown();
        }

        protected function requireRewrittenReturnController(): void
        {
            if (!self::$returnRewritten) {
                self::markTestSkipped('return.php was loaded before its exit statements could be intercepted');
            }
        }

        /** PrestaShopCollection holding $items. */
        protected function collection(array $items): \PrestaShopCollection
        {
            $collection = new \PrestaShopCollection('');
            $collection->items = array_values($items);

            return $collection;
        }

        /** OrderPayment row as returned by OrderPayment::getByOrderReference(). */
        protected function payment(array $props): \OrderPayment
        {
            $payment = new \OrderPayment();
            $payment->hydrate($props + ['id' => null, 'amount' => 0, 'transaction_id' => '', 'payment_method' => '']);

            return $payment;
        }

        /**
         * Put a Buckaroo push/return signed with the test secret into $_POST.
         *
         * @param array<string, string> $fields brq_* fields
         *
         * @return array<string, string> the signed payload
         */
        protected function signedPush(array $fields, bool $tamper = false): array
        {
            $fields['brq_signature'] = $this->sign($fields);
            if ($tamper) {
                $fields['brq_amount'] = '9999.99';
            }
            $this->request([], $fields);

            return $fields;
        }

        /** Same scheme as the SDK reply handler (sha1 over sorted brq_/add_/cust_ fields + secret). */
        protected function sign(array $data): string
        {
            $data = array_filter($data, static function ($key) {
                $key = strtolower((string) $key);

                return $key !== 'brq_signature' && in_array(explode('_', $key)[0], ['brq', 'add', 'cust'], true);
            }, ARRAY_FILTER_USE_KEY);

            uksort($data, static function ($a, $b) {
                return strcmp(strtolower((string) $a), strtolower((string) $b));
            });

            $string = '';
            foreach ($data as $key => $value) {
                $string .= $key . '=' . html_entity_decode((string) $value);
            }

            return sha1($string . self::SECRET_KEY);
        }

        /** Replace a controller's file logger with a recording one. */
        protected function recordLogs(object $controller): RecordingLogger
        {
            $logger = new RecordingLogger();
            self::setProperty($controller, 'logger', $logger);

            return $logger;
        }

        /** Arguments of every OrderHistory->changeIdOrderState() call. */
        protected function stateChanges(): array
        {
            return Stubs::callsTo('OrderHistory->changeIdOrderState');
        }

        /** @return string[] texts of every Message added */
        protected function addedMessages(): array
        {
            $texts = [];
            foreach (Stubs::persisted('Message', 'add') as $message) {
                $texts[] = (int) $message->id_order . ':' . $message->message;
            }

            return $texts;
        }
    }
}
