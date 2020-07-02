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

class IDealResponse extends Response
{
    public $consumerIssuer;
    public $consumerName;
    public $consumerAccountNumber;
    public $consumerCity;

    protected function _parseSoapResponseChild()
    {
        return null;
    }

    protected function _parsePostResponseChild()
    {
        if (Tools::getValue('brq_service_ideal_consumerIssuer')) {
            $this->consumerIssuer = Tools::getValue('brq_service_ideal_consumerIssuer');
        }
        if (Tools::getValue('brq_service_ideal_consumerName')) {
            $this->consumerName = Tools::getValue('brq_service_ideal_consumerName');
        }
        if (Tools::getValue('brq_service_ideal_consumerAccountNumber')) {
            $this->consumerAccountNumber = Tools::getValue('brq_service_ideal_consumerAccountNumber');
        }
        if (Tools::getValue('brq_service_ideal_consumerCity')) {
            $this->consumerCity = Tools::getValue('brq_service_ideal_consumerCity');
        }
    }
}
