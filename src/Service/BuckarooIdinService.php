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

class BuckarooIdinService
{
    public function checkCustomerIdExists(int $customerId)
    {
        $sqlCheck = new \DbQuery();
        $sqlCheck->select('COUNT(*)');
        $sqlCheck->from('bk_customer_idin');
        $sqlCheck->where('customer_id = ' . pSQL($customerId));

        return \Db::getInstance()->getValue($sqlCheck);
    }

    public function updateCustomerData($customerId, $consumerbin, $iseighteenorolder)
    {
        $sql = 'UPDATE ' . _DB_PREFIX_ . 'bk_customer_idin SET buckaroo_idin_consumerbin="' . pSQL($consumerbin) .
            '", buckaroo_idin_iseighteenorolder="' . pSQL($iseighteenorolder) .
            '" WHERE customer_id=' . (int) pSQL($customerId);

        return $this->executeQuery($sql);
    }

    public function insertCustomerData($customerId, $consumerbin, $iseighteenorolder)
    {
        $sql = 'INSERT INTO ' . _DB_PREFIX_
            . 'bk_customer_idin (customer_id, buckaroo_idin_consumerbin, buckaroo_idin_iseighteenorolder) VALUES (' .
            (int) $customerId . ', "' . pSQL($consumerbin) . '", "' . pSQL($iseighteenorolder) . '")';

        return $this->executeQuery($sql);
    }

    public function checkProductIdExists(int $productId)
    {
        $sqlCheck = new \DbQuery();
        $sqlCheck->select('COUNT(*)');
        $sqlCheck->from('bk_product_idin');
        $sqlCheck->where('product_id = ' . pSQL($productId));

        return \Db::getInstance()->getValue($sqlCheck);
    }

    public function updateProductData($productId, $buckarooIdin)
    {
        $sql = 'UPDATE ' . _DB_PREFIX_ . 'bk_product_idin SET buckaroo_idin = ' . (int) $buckarooIdin . ' WHERE product_id = ' . (int) $productId;

        return $this->executeQuery($sql);
    }

    public function insertProductData($productId, $buckarooIdin)
    {
        $sql = 'INSERT INTO ' . _DB_PREFIX_ . 'bk_product_idin (product_id, buckaroo_idin) VALUES (' . (int) $productId . ', ' . (int) $buckarooIdin . ')';

        return $this->executeQuery($sql);
    }

    /**
     * @throws \Exception
     */
    private function executeQuery($sql)
    {
        try {
            \Db::getInstance()->execute($sql);

            return true;
        } catch (\Exception $e) {
            throw new \Exception('Error while executing SQL query: ' . $e->getMessage());
        }
    }
}
