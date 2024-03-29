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
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../paymentmethod.php';
class CreditCard extends PaymentMethod
{
    public $issuer;

    public function __construct()
    {
        $this->type = 'creditcard';
        $this->version = 1;
    }

    // @codingStandardsIgnoreStart
    public function pay($customVars = [])
    {
        $this->payload['name'] = $this->issuer;

        return parent::pay();
    }
}
