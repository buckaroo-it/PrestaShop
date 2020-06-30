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

class PayGarantResponse extends Response
{
    public $paylink = '';

    protected function _parseSoapResponseChild()
    {
        if (Tools::getIsset(
            $this->_response->Services->Service->ResponseParameter
        ) && Tools::getIsset($this->_response->Services->Service->Name)
        ) {
            if ($this->_response->Services->Service->Name == 'paymentguarantee' && $this->_response->Services->Service->ResponseParameter->Name == 'paylink') {
                $this->paylink = $this->_response->Services->Service->ResponseParameter->_;
            }
        }
    }


    protected function _parsePostResponseChild()
    {

    }
}
