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

namespace Buckaroo\PrestaShop\Classes;

class IssuersPayByBank
{
    protected const CACHE_LAST_ISSUER_LABEL = 'BUCKAROO_LAST_PAYBYBANK_ISSUER';

    public function getIssuerList()
    {
        $savedBankIssuer = \Context::getContext()->cookie->{self::CACHE_LAST_ISSUER_LABEL};

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

        return $this->orderByPopularity(array_merge($savedIssuer, $issuers));
    }

    public function getSelectedIssuerLogo()
    {
        $issuers = $this->getIssuerList();
        $selectedIssuer = array_filter($issuers, function ($issuer) {
            return $issuer['selected'];
        });
        if (!empty($selectedIssuer)) {
            $selectedIssuer = reset($selectedIssuer);

            return '../../PayByBank issuers/' . $selectedIssuer['logo'];
        } else {
            return 'PayByBank.gif?v';
        }
    }
    public function orderByPopularity($issuers = []){
        $issuersByPopularity = [
            'N26',
            'ING',
            'ABN AMRO',
            'Rabobank',
            'Knab',
            'BUNQ',
            'SNS Bank',
            'RegioBank',
            'ASN Bank',
            'Revolut',
            'Triodos',
            'van',
            'Lanschot',
            'Bankiers',
            'Nationale',
            'Nederlanden',
            'YourSafe',
        ];
        $ordered = [];
        $issuerNames = array_map(function ($issuer) {
            return $issuer['name'];
        },$issuers);
        foreach($issuersByPopularity as $issuer) {
            $issuerIndex = array_search($issuer,$issuerNames);
            if($issuerIndex){
                $ordered[$issuerIndex] = $issuers[$issuerIndex];
            }
        }
        return $ordered + $issuers;
    }
}
