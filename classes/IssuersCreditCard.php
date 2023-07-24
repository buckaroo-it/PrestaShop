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
require_once _PS_MODULE_DIR_ . 'buckaroo3/vendor/autoload.php';

class IssuersCreditCard
{
    public function getIssuerList()
    {
        $issuerArray =  array(
            'amex' => array(
                'name' => 'American Express',
                'logo' => 'AMEX.svg',
            ),
            'cartebancaire' => array(
                'name' => 'CarteBancaire',
                'logo' => 'CarteBancaire.svg',
            ),
            'cartebleue' => array(
                'name' => 'CarteBleue',
                'logo' => 'CarteBleue.svg',
            ),
            'dankort' => array(
                'name' => 'Dankort',
                'logo' => 'Dankort.svg',
            ),
            'maestro' => array(
                'name' => 'Maestro',
                'logo' => 'Maestro.svg',
            ),
            'mastercard' => array(
                'name' => 'Mastercard',
                'logo' => 'MasterCard.svg',
            ),
            'nexi' => array(
                'name' => 'Nexi',
                'logo' => 'Nexi.svg',
            ),
            'postepay' => array(
                'name' => 'PostePay',
                'logo' => 'Postepay.svg',
            ),
            'visa' => array(
                'name' => 'VISA',
                'logo' => 'Visa.svg',
            ),
            'visaelectron' => array(
                'name' => 'VISA Electron',
                'logo' => 'VisaElectron.svg',
            ),
            'vpay' => array(
                'name' => 'VPAY',
                'logo' => 'VPay.svg',
            ),
        );

        return $issuerArray;
    }
}
