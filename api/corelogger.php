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
            $file = fopen(
                dirname(
                    __FILE__
                ) . '/../api' . self::LOG_DIR . $this->logtype . '-' . $this->filename . '-log-' . date(
                    'Y-m-d'
                ) . '.txt',
                'a'
            );
            $prefix = self::$log_level[$level] . ' ' . date('Y-m-d h:i:s') . ' ';
            $info_str = $info;
            if (!is_null($descr)) {
                if (is_object($descr) || is_array($descr)) {
                    $descr = print_r($descr, true);
                }
                $info_str .= "\nDescription:\n" . $descr . "\n";
            }
            fwrite($file, $prefix . $info_str . "\n");
            fclose($file);
        }
    }

    private function logUserEvent($info)
    {
        $file = fopen(dirname(__FILE__) . '/../api' . self::LOG_DIR . 'report_log.txt', 'a');
        $prefix = date('Y-m-d h:i:s') . '|||';
        fwrite($file, $prefix . $info . "\n");
        fclose($file);
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
