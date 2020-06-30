<?php
/**
* 2014-2015 Buckaroo.nl
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
*  @copyright 2014-2015 Buckaroo.nl
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/

require_once(dirname(__FILE__) . '/../response.php');

class PayPalResponse extends Response
{
    //put your code here
    public $payerEmail;
    public $payerCountry;
    public $payerStatus;
    public $payerFirstname;
    public $payerLastname;
    public $paypalTransactionID;

    protected function _parseSoapResponseChild()
    {
        $this->payerEmail = '';
        $this->payerCountry = '';
        $this->payerStatus = '';
        $this->payerFirstname = '';
        $this->payerLastname = '';
        $this->paypalTransactionID = '';

    }


    protected function _parsePostResponseChild()
    {
        if (Tools::getValue('brq_service_paypal_payerEmail')) {
            $this->payerEmail = Tools::getValue('brq_service_paypal_payerEmail');
        }
        if (Tools::getValue('brq_service_paypal_payerCountry')) {
            $this->payerCountry = Tools::getValue('brq_service_paypal_payerCountry');
        }
        if (Tools::getValue('brq_service_paypal_payerStatus')) {
            $this->payerStatus = Tools::getValue('brq_service_paypal_payerStatus');
        }
        if (Tools::getValue('brq_service_paypal_payerFirstname')) {
            $this->payerFirstname = Tools::getValue('brq_service_paypal_payerFirstname');
        }
        if (Tools::getValue('brq_service_paypal_payerLastname')) {
            $this->payerLastname = Tools::getValue('brq_service_paypal_payerLastname');
        }
        if (Tools::getValue('brq_service_paypal_paypalTransactionID')) {
            $this->paypalTransactionID = Tools::getValue('brq_service_paypal_paypalTransactionID');
        }
    }
}
