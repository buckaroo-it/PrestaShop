<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * It is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this file
 *
 *  @author    Buckaroo.nl <plugins@buckaroo.nl>
 *  @copyright Copyright (c) Buckaroo B.V.
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class CoreLogger
{
    // put your code here

    public const DEBUG = '0';
    public const INFO = '1';
    public const WARN = '2';
    public const ERROR = '3';

    public const LOG = true;

    /** Log directory below the shop root, outside the public module directory. */
    public const LOG_DIR = '/var/logs/buckaroo3/';

    public static $log_level = [
        self::DEBUG => 'Debug',
        self::INFO => 'Info',
        self::WARN => 'Warning',
        self::ERROR => 'Error',
    ];
    /**
     * Context keys that may be forwarded to Buckaroo error reporting.
     * Anything else in a log description stays local.
     */
    public const REPORTABLE_CONTEXT_KEYS = ['payment_method', 'action', 'order_id', 'cart_id'];

    private $level = self::DEBUG;
    private $filename = 'logger';
    private $logtype = 'api';

    public function __construct($level, $filename = 'logger')
    {
        $this->level = $level;
        $this->filename = $filename;
    }

    private function logEvent($info, $level, $descr = null)
    {
        if (self::LOG && $level >= $this->level) {
            $prefix = self::$log_level[$level] . ' ' . date('Y-m-d h:i:s') . ' ';
            $info_str = $info;
            if (!is_null($descr)) {
                $descr = $this->normalizeDescription($descr);
                if (is_object($descr) || is_array($descr)) {
                    $descr = print_r($descr, true);
                }
                $info_str .= "\nDescription:\n" . $descr . "\n";
            }
            $this->write(
                $this->logtype . '-' . $this->filename . '-log-' . date('Y-m-d') . '.txt',
                $prefix . $info_str . "\n"
            );
        }
    }

    private function logUserEvent($info)
    {
        $this->write('report_log.txt', date('Y-m-d h:i:s') . '|||' . $info . "\n");
    }

    /**
     * Absolute log directory. PrestaShop's var/ is denied over HTTP by the core
     * var/.htaccess on Apache and by the recommended Nginx configuration, so the
     * logs are never downloadable, unlike files inside modules/buckaroo3/.
     */
    public static function logDirectory(): string
    {
        $root = defined('_PS_ROOT_DIR_') ? _PS_ROOT_DIR_ : dirname(__DIR__, 3);

        return rtrim($root, '/\\') . self::LOG_DIR;
    }

    /**
     * Append to a log file. Logging must never break the module: PrestaShop 9.1+
     * rejects a module as invalid if a warning is raised while it is loaded
     * (e.g. when the log directory cannot be created on a fresh install).
     */
    private function write($filename, $content)
    {
        $dir = self::logDirectory();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return;
        }
        if (!is_writable($dir)) {
            return;
        }
        @file_put_contents($dir . $filename, $content, FILE_APPEND | LOCK_EX);
    }

    public function logDebug($info, $descr = null)
    {
        $this->logEvent($info, self::DEBUG, $descr);
    }

    /**
     * @param string $info
     * @param mixed $descr a \Throwable, or an array with an 'exception' key, is
     *                     also forwarded to Buckaroo error reporting (opt-in)
     */
    public function logError($info, $descr = null)
    {
        $this->logEvent($info, self::ERROR, $descr);
        $this->reportException('error', $info, $descr);
    }

    public function logForUser($info)
    {
        $this->logUserEvent($info);
    }

    public function logWarn($info, $descr = null)
    {
        $this->logEvent($info, self::WARN, $descr);
        $this->reportException('warning', $info, $descr);
    }

    /**
     * Render exceptions as a short string so log files do not get a print_r()
     * dump of the whole exception object.
     */
    private function normalizeDescription($descr)
    {
        if ($descr instanceof Throwable) {
            return $this->describeThrowable($descr);
        }

        if (is_array($descr)) {
            foreach ($descr as $key => $value) {
                if ($value instanceof Throwable) {
                    $descr[$key] = $this->describeThrowable($value);
                }
            }
        }

        return $descr;
    }

    private function describeThrowable(Throwable $throwable)
    {
        return get_class($throwable) . ': ' . $throwable->getMessage()
            . ' in ' . $throwable->getFile() . ':' . $throwable->getLine();
    }

    /**
     * Forward an exception logged by the plugin to the Buckaroo error reporter.
     * The reporter itself checks the merchant's consent and never throws.
     */
    private function reportException($level, $info, $descr)
    {
        try {
            $exception = null;
            $context = [];

            if ($descr instanceof Throwable) {
                $exception = $descr;
            } elseif (is_array($descr)) {
                if (isset($descr['exception']) && $descr['exception'] instanceof Throwable) {
                    $exception = $descr['exception'];
                }
                foreach (self::REPORTABLE_CONTEXT_KEYS as $key) {
                    if (isset($descr[$key]) && is_scalar($descr[$key])) {
                        $context[$key] = (string) $descr[$key];
                    }
                }
            }

            $reporterClass = 'Buckaroo\\PrestaShop\\Src\\ErrorReporting\\ErrorReporter';
            if ($exception === null || !class_exists($reporterClass)) {
                return;
            }

            if ($this->filename !== '' && !isset($context['action'])) {
                $context['action'] = (string) $this->filename;
            }

            $reporterClass::captureException($exception, $level, is_string($info) ? $info : '', $context);
        } catch (Throwable $e) {
            // Error reporting must never break the shop.
        }
    }

    public function logInfo($info, $descr = null)
    {
        $this->logEvent($info, self::INFO, $descr);
    }
}
