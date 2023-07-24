<?php

require_once _PS_MODULE_DIR_ . 'buckaroo3/config.php';
require_once _PS_MODULE_DIR_ . 'buckaroo3/vendor/autoload.php';

class IssuersPayByBank
{
    protected const CACHE_LAST_ISSUER_LABEL = 'BUCKAROO_LAST_PAYBYBANK_ISSUER';

    public function getIssuerList()
    {
        $savedBankIssuer = Context::getContext()->cookie->{self::CACHE_LAST_ISSUER_LABEL};

        $issuerArray =  array(
            'ABNANL2A' => array(
                'name' => 'ABN AMRO',
                'logo' => 'ABNAMRO.svg',
            ),
            'ASNBNL21' => array(
                'name' => 'ASN Bank',
                'logo' => 'ASNBank.svg',
            ),
            'INGBNL2A' => array(
                'name' => 'ING',
                'logo' => 'ING.svg',
            ),
            'RABONL2U' => array(
                'name' => 'Rabobank',
                'logo' => 'Rabobank.svg',
            ),
            'SNSBNL2A' => array(
                'name' => 'SNS Bank',
                'logo' => 'SNS.svg',
            ),
            'RBRBNL21' => array(
                'name' => 'RegioBank',
                'logo' => 'RegioBank.svg',
            ),
            'KNABNL2H' => array(
                'name' => 'Knab',
                'logo' => 'KNAB.svg',
            ),
            'NTSBDEB1' => array(
                'name' => 'N26',
                'logo' => 'n26.svg',
            )
        );

        $issuers = [];

        foreach ($issuerArray as $key => $issuer) {
            $issuer['selected'] = $key === $savedBankIssuer;

            $issuers[$key] = $issuer;
        }

        $savedIssuer = array_filter($issuers, function($issuer) {
            return $issuer['selected'];
        });
        $issuers = array_filter($issuers, function($issuer) {
            return !$issuer['selected'];
        });
        return array_merge($savedIssuer, $issuers);
    }

    public function getSelectedIssuerLogo()
    {
        $issuers = $this->getIssuerList();
        $selectedIssuer = array_filter($issuers, function($issuer) {
            return $issuer['selected'];
        });
        if (count($selectedIssuer) > 0) {
            $selectedIssuer = reset($selectedIssuer);
            return 'paybybank/SVG/'.$selectedIssuer['logo'];
        } else {
            return 'buckaroo_paybybank.gif?v';
        }
    }
}