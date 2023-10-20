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
require_once dirname(__FILE__) . '/../response.php';
require_once dirname(__FILE__) . '/../../../library/logger.php';

class IdinResponse extends Response
{
    public function __construct($transactionResponse = null)
    {
        $this->parsePostResponseChild();
        parent::__construct($transactionResponse);
    }

    protected function parsePostResponseChild()
    {
        if ($customerId = \Tools::getValue('ADD_cid')) {
            if ($consumerbin = \Tools::getValue('brq_SERVICE_idin_ConsumerBIN')) {
                if ($iseighteenorolder = \Tools::getValue('brq_SERVICE_idin_IsEighteenOrOlder')) {
                    // Check if there's already a record for this customer in the bk_customer_idin table
                    $sqlCheck = 'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'bk_customer_idin WHERE customer_id = ' . (int) $customerId;
                    $exists = \Db::getInstance()->getValue($sqlCheck);

                    if ($exists) {
                        // If there's a record, update it
                        $sql = 'UPDATE ' . _DB_PREFIX_ . 'bk_customer_idin SET buckaroo_idin_consumerbin="' . pSQL($consumerbin) .
                            '", buckaroo_idin_iseighteenorolder="' . pSQL($iseighteenorolder) .
                            '" WHERE customer_id=' . (int) pSQL($customerId);
                    } else {
                        // If there isn't, insert a new record
                        $sql = 'INSERT INTO ' . _DB_PREFIX_ . 'bk_customer_idin (customer_id, buckaroo_idin_consumerbin, buckaroo_idin_iseighteenorolder) VALUES (' .
                            (int) $customerId . ', "' . pSQL($consumerbin) . '", "' . pSQL($iseighteenorolder) . '")';
                    }

                    try {
                        \Db::getInstance()->execute($sql);
                    } catch (Exception $e) {
                        throw new Exception('Error while saving iDIN data: ' . $e->getMessage());
                    }
                }
            }
        }
    }
}
