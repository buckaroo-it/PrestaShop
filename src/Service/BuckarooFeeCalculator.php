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

namespace Buckaroo\PrestaShop\Src\Service;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Single calculation for the Buckaroo payment fee.
 *
 * The checkout summary, the payment request total, the amount sent to Buckaroo
 * and the fee stored on the order must all come from this class, so they
 * cannot differ by a rounding cent.
 */
class BuckarooFeeCalculator
{
    /**
     * @param mixed $configuredFee fixed amount (tax excl.) or a percentage such as "2.5%"
     * @param float $percentageBase cart total the percentage applies to
     * @param float $taxRate fee tax rate as a fraction (0.21 for 21%)
     * @param int $precision the shop's computing precision
     *
     * @return array{buckaroo_fee_tax_excl: float, buckaroo_fee_tax: float, buckaroo_fee_tax_incl: float}|null
     */
    public static function calculate($configuredFee, float $percentageBase, float $taxRate, int $precision): ?array
    {
        if ($configuredFee === null || $configuredFee === false) {
            return null;
        }

        $configuredFee = trim((string) $configuredFee);
        if ($configuredFee === '') {
            return null;
        }

        if (self::isPercentage($configuredFee)) {
            $percentage = self::normalizePercentage($configuredFee);
            if ($percentage <= 0 || $percentageBase <= 0) {
                return null;
            }

            $fee = \Tools::ps_round($percentageBase, $precision) * ($percentage / 100);
        } else {
            $fee = (float) $configuredFee;
        }

        if ($fee <= 0) {
            return null;
        }

        $feeTaxExcl = (float) \Tools::ps_round($fee, $precision);
        $feeTax = (float) \Tools::ps_round($feeTaxExcl * max(0.0, $taxRate), $precision);
        $feeTaxIncl = (float) \Tools::ps_round($feeTaxExcl + $feeTax, $precision);

        return [
            'buckaroo_fee_tax_excl' => $feeTaxExcl,
            'buckaroo_fee_tax' => $feeTax,
            'buckaroo_fee_tax_incl' => $feeTaxIncl,
        ];
    }

    /**
     * Adds a calculated fee (see calculate()) to an order total, rounded the same
     * way for the checkout summary and for the payment request.
     *
     * @param array|null $fee
     */
    public static function addToTotal(float $total, $fee, int $precision, bool $withTax = true): float
    {
        $feeAmount = 0.0;
        if (is_array($fee)) {
            $feeAmount = (float) ($withTax ? $fee['buckaroo_fee_tax_incl'] : $fee['buckaroo_fee_tax_excl']);
        }

        return (float) \Tools::ps_round($total + $feeAmount, $precision);
    }

    public static function isPercentage($fee): bool
    {
        return is_string($fee) && strpos($fee, '%') !== false;
    }

    private static function normalizePercentage(string $fee): float
    {
        $normalized = str_replace(['%', ','], ['', '.'], $fee);

        return (float) trim($normalized);
    }
}
