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

class CreditCard extends PaymentMethod
{
    public function __construct()
    {
        $this->type = "creditcard";
        $this->version = 1;
        $this->mode = Config::getMode('CREDITCARD');
    }

    public function refund()
    {
        return parent::refund();
    }

    // @codingStandardsIgnoreStart
    public function pay($customVars = array())
    {
        $this->payload['name'] = $this->issuer;

        return parent::pay();
    }

    public function getIssuerList()
    {
        $issuerArray =  array(
            'amex' => array(
                'name' => 'American Express',
                'logo' => 'AmericanExpress.png',
            ),
            'cartebancaire' => array(
                'name' => 'CarteBancaire',
                'logo' => 'CarteBancaire.png',
            ),
            'cartebleue' => array(
                'name' => 'CarteBleue',
                'logo' => 'CarteBleue.png',
            ),
            'dankort' => array(
                'name' => 'Dankort',
                'logo' => 'Dankort.png',
            ),
            'maestro' => array(
                'name' => 'Maestro',
                'logo' => 'Maestro.png',
            ),
            'mastercard' => array(
                'name' => 'Mastercard',
                'logo' => 'Mastercard.png',
            ),
            'nexi' => array(
                'name' => 'Nexi',
                'logo' => 'Nexi.png',
            ),
            'postepay' => array(
                'name' => 'PostePay',
                'logo' => 'PostePay.png',
            ),
            'visa' => array(
                'name' => 'VISA',
                'logo' => 'VISA.png',
            ),
            'visaelectron' => array(
                'name' => 'VISA Electron',
                'logo' => 'VISAelectron.png',
            ),
            'vpay' => array(
                'name' => 'VPAY',
                'logo' => 'VPAY.png',
            ),
        );

        return $issuerArray;
    }
}
