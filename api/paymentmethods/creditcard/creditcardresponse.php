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

class CreditCardResponse extends Response
{
    //put your code here
    public $cardNumberEnding = '';

    protected function _parseSoapResponseChild()
    {

    }


    protected function _parsePostResponseChild()
    {
        if (Tools::getValue('brq_service_' . $this->payment_method . '_CardNumberEnding')) {
            $this->cardNumberEnding = Tools::getValue('brq_service_' . $this->payment_method . '_CardNumberEnding');
        }
    }
}
