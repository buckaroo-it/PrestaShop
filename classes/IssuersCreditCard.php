<?php

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
                'logo' => 'Mastercard.svg',
            ),
            'nexi' => array(
                'name' => 'Nexi',
                'logo' => 'Nexi.svg',
            ),
            'postepay' => array(
                'name' => 'PostePay',
                'logo' => 'PostePay.svg',
            ),
            'visa' => array(
                'name' => 'VISA',
                'logo' => 'VISA.svg',
            ),
            'visaelectron' => array(
                'name' => 'VISA Electron',
                'logo' => 'VISAelectron.svg',
            ),
            'vpay' => array(
                'name' => 'VPAY',
                'logo' => 'VPAY.svg',
            ),
        );

        return $issuerArray;
    }
}