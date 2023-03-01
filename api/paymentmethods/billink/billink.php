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

require_once(dirname(__FILE__) . '/../paymentmethod.php');

class Billink extends PaymentMethod
{
    public const CUSTOMER_TYPE_B2C = 'b2c';
    public const CUSTOMER_TYPE_B2B = 'b2b';
    public const CUSTOMER_TYPE_BOTH = 'both';

    public function __construct()
    {
        $this->type = "billink";
        $this->version = '1';
        $this->mode = Config::getMode('BILLINK');
    }

    // @codingStandardsIgnoreStart
    public function pay($customVars = Array())
    {
    // @codingStandardsIgnoreEnd
        return null;
    }

    public function payBillink($products, $customVars = array())
    {
        $this->payload = $this->getPayload($products);
        return parent::pay();
    }

    public function getPayload($products)
    {        
        $payload = array();
        return $payload;        
    }
}
