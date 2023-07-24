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

require_once dirname(__FILE__) . '/../paymentmethod.php';
class PayByBank extends PaymentMethod
{
    public $issuer;
    protected const CACHE_LAST_ISSUER_LABEL = 'BUCKAROO_LAST_PAYBYBANK_ISSUER';
    protected $data;
    protected $payload;

    public function __construct()
    {
        $this->type    = "paybybank";
        $this->version = 2;
        $this->mode    = Config::getMode($this->type);
    }

    // @codingStandardsIgnoreStart
    public function pay($customVars = array())
    {
        Context::getContext()->cookie->__set(self::CACHE_LAST_ISSUER_LABEL, $this->issuer);

        if($this->issuer === 'INGBNL2A' && Context::getContext()->isMobile()){
            $this->type = 'ideal'; // send ideal request if issuer is ING and is on mobile
        }
        $this->payload['issuer'] = is_string($this->issuer) ? $this->issuer : '';
        return parent::pay();
    }
}
