<?php
/**
*
*
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

require_once(dirname(__FILE__) . '/../response.php');

class IdinResponse extends Response
{
    public $idinConsumerbin;
    public $idinIseighteenorolder;
    public $buckarooCid;

    protected function parseSoapResponseChild()
    {
        return null;
    }

    protected function parsePostResponseChild()
    {
        if ($customerId = Tools::getValue('add_cid')) {
            Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'customer SET buckaroo_idin_consumerbin="'.pSQL(Tools::getValue('brq_service_idin_consumerbin')).'", buckaroo_idin_iseighteenorolder="'.pSQL(Tools::getValue('brq_service_idin_iseighteenorolder')).'" WHERE id_customer='.(int) $customerId);
        }
    }
}
