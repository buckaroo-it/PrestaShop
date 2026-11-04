<?php

namespace BuckarooTest\Unit\Api;

use Buckaroo\PrestaShop\Src\ErrorReporting\ErrorReporter;
use BuckarooTest\TestCase;

require_once __DIR__ . '/ApiTestSupport.php';

/**
 * api/corelogger.php and library/logger.php: log files below var/logs/buckaroo3.
 */
class LoggerTest extends TestCase
{
    /** @var string */
    private $name;

    protected function setUp(): void
    {
        parent::setUp();
        ApiFixtures::load();
        $this->name = 'apitest-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        @unlink($this->file());
        ErrorReporter::setInstance(null);
        parent::tearDown();
    }

    public function testLogDirectoryIsBelowTheShopVarDirectory(): void
    {
        self::assertSame(rtrim(_PS_ROOT_DIR_, '/') . '/var/logs/buckaroo3/', \CoreLogger::logDirectory());
    }

    /**
     * @dataProvider levels
     */
    public function testEachLevelIsWrittenWithItsLabel(string $method, string $label): void
    {
        $logger = new \Logger(\Logger::DEBUG, $this->name);

        $logger->$method('Something happened');

        $log = $this->log();
        self::assertMatchesRegularExpression('/^' . $label . ' \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} Something happened\n$/', $log);
    }

    public function levels(): array
    {
        return [
            'debug' => ['logDebug', 'Debug'],
            'info' => ['logInfo', 'Info'],
            'warning' => ['logWarn', 'Warning'],
            'error' => ['logError', 'Error'],
        ];
    }

    public function testMessagesBelowTheLevelAreNotWritten(): void
    {
        $logger = new \Logger(\Logger::WARN, $this->name);

        $logger->logDebug('debug');
        $logger->logInfo('info');
        $logger->logWarn('warn');

        $log = $this->log();
        self::assertStringNotContainsString('debug', $log);
        self::assertStringNotContainsString('info', $log);
        self::assertStringContainsString('Warning', $log);
    }

    public function testArrayDescriptionIsDumpedAndExceptionsAreShortened(): void
    {
        $logger = new \Logger(\Logger::INFO, $this->name);
        $error = new \RuntimeException('gateway down');

        $logger->logInfo('Request failed', ['order_id' => 5, 'exception' => $error]);

        $log = $this->log();
        self::assertStringContainsString("Request failed\nDescription:\n", $log);
        self::assertStringContainsString('[order_id] => 5', $log);
        self::assertStringContainsString('[exception] => RuntimeException: gateway down in ' . __FILE__ . ':', $log);
        self::assertStringNotContainsString('trace', strtolower($log));
    }

    public function testThrowableDescriptionIsShortened(): void
    {
        $logger = new \Logger(\Logger::INFO, $this->name);

        $logger->logInfo('Oops', new \LogicException('bad state'));

        self::assertStringContainsString("Description:\nLogicException: bad state in " . __FILE__, $this->log());
    }

    public function testScalarAndObjectDescriptions(): void
    {
        $logger = new \Logger(\Logger::INFO, $this->name);
        $object = new \stdClass();
        $object->status = 'paid';

        $logger->logInfo('scalar', 'plain text');
        $logger->logInfo('object', $object);

        $log = $this->log();
        self::assertStringContainsString("scalar\nDescription:\nplain text\n", $log);
        self::assertStringContainsString('[status] => paid', $log);
    }

    public function testLogForUserAppendsToTheReportLog(): void
    {
        $file = \CoreLogger::logDirectory() . 'report_log.txt';
        $before = is_file($file) ? filesize($file) : 0;

        (new \Logger(\Logger::INFO, $this->name))->logForUser('Refund of 5.00 by ' . $this->name);

        $log = (string) file_get_contents($file, false, null, $before);
        self::assertMatchesRegularExpression('/\d{2}:\d{2}:\d{2}\|\|\|Refund of 5\.00 by ' . $this->name . '\n/', $log);
    }

    /**
     * Regression: library/logger.php's $logtype ('plugin') used to be ignored
     * in favour of CoreLogger's private 'api', so plugin logs were named api-*.
     */
    public function testPluginLoggerWritesPluginPrefixedFiles(): void
    {
        (new \Logger(\Logger::INFO, $this->name))->logInfo('x');

        self::assertFileExists($this->file());
        self::assertFileDoesNotExist(\CoreLogger::logDirectory() . 'api-' . $this->name . '-log-' . date('Y-m-d') . '.txt');
    }

    public function testCoreLoggerKeepsTheApiPrefix(): void
    {
        $file = \CoreLogger::logDirectory() . 'api-' . $this->name . '-log-' . date('Y-m-d') . '.txt';

        try {
            (new \CoreLogger(\CoreLogger::INFO, $this->name))->logInfo('x');
            self::assertFileExists($file);
        } finally {
            @unlink($file);
        }
    }

    /**
     * Regression: timestamps used 'h' (12-hour clock without AM/PM).
     */
    public function testTimestampsUseThe24HourClock(): void
    {
        $logger = new \Logger(\Logger::INFO, $this->name);
        $before = date('Y-m-d H:i');
        $logger->logInfo('clock');
        $after = date('Y-m-d H:i');

        preg_match('/^Info (\d{4}-\d{2}-\d{2} \d{2}:\d{2}):\d{2} clock/', $this->log(), $match);
        self::assertContains($match[1] ?? null, [$before, $after]);
    }

    public function testLoggedErrorsAreForwardedToErrorReporting(): void
    {
        $reporter = new class extends ErrorReporter {
            /** @var array */
            public $captured = [];

            public function __construct()
            {
            }

            public function report(\Throwable $exception, string $level = 'error', string $message = '', array $context = []): void
            {
                $this->captured[] = [get_class($exception), $level, $message, $context];
            }
        };
        ErrorReporter::setInstance($reporter);
        $logger = new \Logger('9', $this->name);

        $logger->logError('Refund failed', ['exception' => new \RuntimeException('x'), 'order_id' => 5, 'email' => 'a@b.c', 'cart_id' => ['no']]);
        $logger->logWarn('Fallback', new \LogicException('y'));
        $logger->logError('No exception', ['order_id' => 5]);
        $logger->logError('Not an exception', ['exception' => 'text']);

        self::assertSame([
            ['RuntimeException', 'error', 'Refund failed', ['order_id' => '5', 'action' => $this->name]],
            ['LogicException', 'warning', 'Fallback', ['action' => $this->name]],
        ], $reporter->captured);
        // Level 9 is above every level: nothing is written.
        self::assertFileDoesNotExist($this->file());
    }

    public function testExplicitActionContextIsKept(): void
    {
        $reporter = new class extends ErrorReporter {
            /** @var array */
            public $captured = [];

            public function __construct()
            {
            }

            public function report(\Throwable $exception, string $level = 'error', string $message = '', array $context = []): void
            {
                $this->captured[] = $context;
            }
        };
        ErrorReporter::setInstance($reporter);

        (new \Logger('9', $this->name))->logError('Refund failed', ['exception' => new \RuntimeException('x'), 'action' => 'refund']);

        self::assertSame([['action' => 'refund']], $reporter->captured);
    }

    private function file(): string
    {
        return \CoreLogger::logDirectory() . 'plugin-' . $this->name . '-log-' . date('Y-m-d') . '.txt';
    }

    private function log(): string
    {
        return is_file($this->file()) ? (string) file_get_contents($this->file()) : '';
    }
}
