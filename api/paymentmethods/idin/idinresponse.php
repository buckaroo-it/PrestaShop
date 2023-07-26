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

// TODO - Fix IDIN
class IdinResponse extends Response
{
    public $idinConsumerbin;
    public $idinIseighteenorolder;
    public $buckarooCid;

    protected function parsePostResponseChild()
    {
        if ($customerId = Tools::getValue('add_cid')) {
            if ($consumerbin = pSQL(Tools::getValue('brq_service_idin_consumerbin'))) {
                if ($iseighteenorolder = pSQL(Tools::getValue('brq_service_idin_iseighteenorolder'))) {
                    Db::getInstance()->execute(
                        'UPDATE ' . _DB_PREFIX_ . 'customer SET buckaroo_idin_consumerbin="' .
                        $consumerbin . '", buckaroo_idin_iseighteenorolder="' . $iseighteenorolder . '" WHERE id_customer=' .
                        (int) $customerId
                    );
                }
            }
        }
    }
}
