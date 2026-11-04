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

namespace Buckaroo\PrestaShop\Src\ErrorReporting;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Merchant consent for sharing plugin error reports with Buckaroo.
 *
 * Tri-state stored in Configuration: '' = not asked yet, '1' = yes, '0' = no.
 * Anything other than an explicit '1' means no consent (opt-in).
 */
class ErrorReportingConsent
{
    public const CONFIG_KEY = 'BUCKAROO_ERROR_REPORTING';

    public const NOT_ASKED = '';
    public const YES = '1';
    public const NO = '0';

    public static function getState(): string
    {
        $value = \Configuration::get(self::CONFIG_KEY);

        if ($value === self::YES || $value === 1 || $value === true) {
            return self::YES;
        }

        if ($value === self::NO || $value === 0) {
            return self::NO;
        }

        return self::NOT_ASKED;
    }

    public static function isGranted(): bool
    {
        return self::getState() === self::YES;
    }

    public static function isDecided(): bool
    {
        return self::getState() !== self::NOT_ASKED;
    }

    public static function needsPrompt(): bool
    {
        return !self::isDecided();
    }

    /**
     * @param bool $granted
     */
    public static function setConsent(bool $granted): bool
    {
        return (bool) \Configuration::updateValue(self::CONFIG_KEY, $granted ? self::YES : self::NO);
    }

    /**
     * Mark the consent as "not asked yet" so the merchant gets prompted.
     * Keeps an existing decision unless $force is true.
     */
    public static function resetToNotAsked(bool $force = false): bool
    {
        if (!$force && self::isDecided()) {
            return true;
        }

        return (bool) \Configuration::updateValue(self::CONFIG_KEY, self::NOT_ASKED);
    }
}
