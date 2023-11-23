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
require_once dirname(__FILE__) . '/../paymentmethod.php';
class IDeal extends PaymentMethod
{
    public $issuer;
    protected $data;
    protected $payload;
    protected $issuerIsRequired;

    public function __construct()
    {
        $this->type = 'ideal';
        $this->version = 2;
        $this->issuerIsRequired = \Module::getInstanceByName('buckaroo3')->getBuckarooConfigService()->getConfigValue($this->type, 'show_issuers') ?? true;
    }

    // @codingStandardsIgnoreStart
    public function pay($customVars = [])
    {
        if($this->issuerIsRequired){
            $this->payload['issuer'] = is_string($this->issuer) ? $this->issuer : '';
        }else{
            $this->payload['continueOnIncomplete'] = 1;
        }
        return parent::pay();
    }
}
