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

namespace Buckaroo\PrestaShop\Src\Repository;

if (!defined('_PS_VERSION_')) {
    exit;
}
class RawBuckarooFeeRepository
{
    /**
     * Inserts a fee record into the database.
     *
     * @param string $reference
     * @param int $cartId
     * @param int $orderId
     * @param float $feeExcl
     * @param float $feeIncl
     * @param string $currency
     * @return bool
     */
    public function insertFee($reference, $cartId, $orderId, $feeExcl, $feeIncl, $currency)
    {
        try {
            $data = [
                'reference' => pSQL($reference),
                'id_cart' => (int) $cartId,
                'id_order' => (int) $orderId,
                'buckaroo_fee_tax_excl' => (float) $feeExcl,
                'buckaroo_fee_tax_incl' => (float) $feeIncl,
                'currency' => pSQL($currency),
                'created_at' => date('Y-m-d H:i:s'),
            ];

            return \Db::getInstance()->insert('bk_buckaroo_fee', $data);
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog('Failed to insert buckaroo fee: ' . $e->getMessage(), 3);
            return false;
        }
    }

    /**
     * Retrieves a fee record by order ID.
     *
     * @param int $orderId
     * @return array|false
     */
    public function getFeeByOrderId($orderId)
    {
        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'bk_buckaroo_fee WHERE id_order = ' . (int)$orderId;
        return \Db::getInstance()->getRow($sql);
    }

    /**
     * Returns the part of the payment fee that has not been refunded yet.
     *
     * The fee can be refunded in several steps, so this is the fee including tax
     * minus everything that was already sent to Buckaroo for this order.
     *
     * @param int $orderId
     * @return float
     */
    public function getRefundableFeeAmount(int $orderId): float
    {
        $row = $this->getFeeByOrderId($orderId);

        if (!is_array($row)) {
            return 0.0;
        }

        $remaining = (float) $row['buckaroo_fee_tax_incl'] - (float) (isset($row['fee_refunded_amount']) ? $row['fee_refunded_amount'] : 0);

        return $remaining > 0 ? round($remaining, 2) : 0.0;
    }

    /**
     * Registers an additional refunded part of the payment fee.
     *
     * The fee is flagged as fully refunded once nothing refundable is left, so
     * that the order page can stop offering it.
     *
     * @param int $orderId
     * @param float $amount
     * @return bool
     */
    public function addRefundedFeeAmount(int $orderId, float $amount): bool
    {
        if ($amount <= 0) {
            return false;
        }

        $row = $this->getFeeByOrderId($orderId);

        if (!is_array($row)) {
            return false;
        }

        $feeTotal = (float) $row['buckaroo_fee_tax_incl'];
        $alreadyRefunded = (float) (isset($row['fee_refunded_amount']) ? $row['fee_refunded_amount'] : 0);
        $refunded = min($feeTotal, round($alreadyRefunded + $amount, 2));

        try {
            return \Db::getInstance()->update(
                'bk_buckaroo_fee',
                [
                    'fee_refunded_amount' => $refunded,
                    'fee_refunded' => ($feeTotal - $refunded) < 0.01 ? 1 : 0,
                ],
                'id_order = ' . (int) $orderId
            );
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog('Failed to register refunded buckaroo fee: ' . $e->getMessage(), 3);
            return false;
        }
    }

    /**
     * Returns whether the payment fee for an order has already been refunded in full.
     *
     * @param int $orderId
     * @return bool
     */
    public function isFeeRefunded(int $orderId): bool
    {
        $row = $this->getFeeByOrderId($orderId);

        return is_array($row) && !empty($row['fee_refunded']);
    }
}