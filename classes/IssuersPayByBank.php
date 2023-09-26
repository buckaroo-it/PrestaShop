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
require_once _PS_MODULE_DIR_ . 'buckaroo3/config.php';
require_once _PS_MODULE_DIR_ . 'buckaroo3/vendor/autoload.php';

class IssuersPayByBank
{
    protected const CACHE_LAST_ISSUER_LABEL = 'BUCKAROO_LAST_PAYBYBANK_ISSUER';

    public function getIssuerList()
    {
        $savedBankIssuer = Context::getContext()->cookie->{self::CACHE_LAST_ISSUER_LABEL};

        $issuerArray = [
            'ABNANL2A' => [
                'name' => 'ABN AMRO',
                'logo' => 'ABNAMRO.svg',
            ],
            'ASNBNL21' => [
                'name' => 'ASN Bank',
                'logo' => 'ASNBank.svg',
            ],
            'INGBNL2A' => [
                'name' => 'ING',
                'logo' => 'ING.svg',
            ],
            'RABONL2U' => [
                'name' => 'Rabobank',
                'logo' => 'Rabobank.svg',
            ],
            'SNSBNL2A' => [
                'name' => 'SNS Bank',
                'logo' => 'SNS.svg',
            ],
            'RBRBNL21' => [
                'name' => 'RegioBank',
                'logo' => 'RegioBank.svg',
            ],
            'KNABNL2H' => [
                'name' => 'Knab',
                'logo' => 'KNAB.svg',
            ],
            'NTSBDEB1' => [
                'name' => 'N26',
                'logo' => 'n26.svg',
            ],
        ];

        $issuers = [];

        foreach ($issuerArray as $key => $issuer) {
            $issuer['selected'] = $key === $savedBankIssuer;

            $issuers[$key] = $issuer;
        }

        $savedIssuer = array_filter($issuers, function ($issuer) {
            return $issuer['selected'];
        });
        $issuers = array_filter($issuers, function ($issuer) {
            return !$issuer['selected'];
        });

        return array_merge($savedIssuer, $issuers);
    }

    public function getSelectedIssuerLogo()
    {
        $issuers = $this->getIssuerList();
        $selectedIssuer = array_filter($issuers, function ($issuer) {
            return $issuer['selected'];
        });
        if (count($selectedIssuer) > 0) {
            $selectedIssuer = reset($selectedIssuer);

            return 'paybybank/SVG/' . $selectedIssuer['logo'];
        } else {
            return 'paybybank.gif?v';
        }
    }
}
