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
}