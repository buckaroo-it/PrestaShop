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
require_once _PS_MODULE_DIR_ . 'buckaroo3/vendor/autoload.php';

class IssuersCreditCard
{
    public function getIssuerList()
    {
        $issuerArray = [
            'amex' => [
                'name' => 'American Express',
                'logo' => 'AMEX.svg',
            ],
            'cartebancaire' => [
                'name' => 'CarteBancaire',
                'logo' => 'CarteBancaire.svg',
            ],
            'cartebleue' => [
                'name' => 'CarteBleue',
                'logo' => 'CarteBleue.svg',
            ],
            'dankort' => [
                'name' => 'Dankort',
                'logo' => 'Dankort.svg',
            ],
            'maestro' => [
                'name' => 'Maestro',
                'logo' => 'Maestro.svg',
            ],
            'mastercard' => [
                'name' => 'Mastercard',
                'logo' => 'MasterCard.svg',
            ],
            'nexi' => [
                'name' => 'Nexi',
                'logo' => 'Nexi.svg',
            ],
            'postepay' => [
                'name' => 'PostePay',
                'logo' => 'Postepay.svg',
            ],
            'visa' => [
                'name' => 'VISA',
                'logo' => 'Visa.svg',
            ],
            'visaelectron' => [
                'name' => 'VISA Electron',
                'logo' => 'VisaElectron.svg',
            ],
            'vpay' => [
                'name' => 'VPAY',
                'logo' => 'VPay.svg',
            ],
        ];

        return $issuerArray;
    }
}
