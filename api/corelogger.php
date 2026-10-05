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
    public const LOG_DIR = '/log/';

    public static $log_level = [
        self::DEBUG => 'Debug',
        self::INFO => 'Info',
        self::WARN => 'Warning',
        self::ERROR => 'Error',
    ];
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
     * Append to a log file. Logging must never break the module: PrestaShop 9.1+
     * rejects a module as invalid if a warning is raised while it is loaded
     * (e.g. when api/log/ is missing on a fresh install).
     */
    private function write($filename, $content)
    {
        $dir = dirname(__FILE__) . self::LOG_DIR;
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

    public function logError($info, $descr = null)
    {
        $this->logEvent($info, self::ERROR, $descr);
    }

    public function logForUser($info)
    {
        $this->logUserEvent($info);
    }

    public function logWarn($info, $descr = null)
    {
        $this->logEvent($info, self::WARN, $descr);
    }

    public function logInfo($info, $descr = null)
    {
        $this->logEvent($info, self::INFO, $descr);
    }
}
