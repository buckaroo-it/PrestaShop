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

namespace Buckaroo\PrestaShop\Src\Refund;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Validates a merchant-entered refund amount against the maximum refundable
 * amount. Amounts are compared in integer cents so no float comparison is
 * involved, and the entered value is never capped or rounded.
 */
class RefundAmount
{
    public const VALID = 'valid';
    public const INVALID = 'invalid';
    public const EXCEEDS_MAXIMUM = 'exceeds_maximum';
    public const NOTHING_TO_REFUND = 'nothing_to_refund';

    /**
     * Parse an entered amount ("9.99", "24.960") into cents.
     * Returns null for empty, non-numeric or negative input, and for input
     * with more than two significant decimals (it would have to be rounded).
     *
     * @param mixed $value
     */
    public static function toCents($value): ?int
    {
        if (!is_scalar($value) || is_bool($value)) {
            return null;
        }

        $value = trim((string) $value);
        if (!preg_match('/^(\d{1,12})(?:\.(\d+))?$/', $value, $matches)) {
            return null;
        }

        $decimals = isset($matches[2]) ? rtrim($matches[2], '0') : '';
        if (strlen($decimals) > 2) {
            return null;
        }

        return (int) $matches[1] * 100 + (int) str_pad($decimals, 2, '0');
    }

    /**
     * Convert an amount already rounded to two decimals (such as the maximum
     * shown in the refunds block) into cents.
     */
    public static function floatToCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * @param mixed $requested the raw merchant-entered amount
     * @param float $maximum the maximum refundable amount shown to the merchant
     *
     * @return string one of the class constants
     */
    public static function validate($requested, float $maximum): string
    {
        $requestedCents = self::toCents($requested);
        if ($requestedCents === null || $requestedCents <= 0) {
            return self::INVALID;
        }

        $maximumCents = self::floatToCents($maximum);
        if ($maximumCents <= 0) {
            return self::NOTHING_TO_REFUND;
        }

        if ($requestedCents > $maximumCents) {
            return self::EXCEEDS_MAXIMUM;
        }

        return self::VALID;
    }
}
