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

namespace Buckaroo\PrestaShop\Src\Logs;

use Buckaroo\PrestaShop\Src\Config\Config;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Log levels of CoreLogger and the level the merchant chose in the module
 * settings (Config::BUCKAROO_LOG_LEVEL). A logger writes an entry when its
 * level is at or above the configured one.
 */
final class LogLevel
{
    public const DEBUG = '0';
    public const INFO = '1';
    public const WARNING = '2';
    public const ERROR = '3';

    /** Used on new installations, after upgrading and whenever the setting is missing or invalid. */
    public const DEFAULT_LEVEL = self::WARNING;

    public const LABELS = [
        self::DEBUG => 'Debug',
        self::INFO => 'Info',
        self::WARNING => 'Warning',
        self::ERROR => 'Error',
    ];

    /**
     * @param mixed $level
     *
     * @return string|null one of the level constants, or null when $level is not a valid level
     */
    public static function normalize($level): ?string
    {
        if (is_int($level)) {
            $level = (string) $level;
        }

        if (!is_string($level) || !isset(self::LABELS[$level])) {
            return null;
        }

        return $level;
    }

    /**
     * The configured level. Logging must never break a request, so the default
     * is used when the configuration cannot be read (e.g. while the shop boots).
     */
    public static function configured(): string
    {
        try {
            if (class_exists('Configuration')) {
                $level = self::normalize(\Configuration::get(Config::BUCKAROO_LOG_LEVEL));
                if ($level !== null) {
                    return $level;
                }
            }
        } catch (\Throwable $e) {
            // Fall back to the default below.
        }

        return self::DEFAULT_LEVEL;
    }
}
