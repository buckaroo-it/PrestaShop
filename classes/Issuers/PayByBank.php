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

namespace Buckaroo\PrestaShop\Classes\Issuers;

class PayByBank extends Issuers
{
    protected const CACHE_LAST_ISSUER_LABEL = 'BUCKAROO_LAST_PAYBYBANK_ISSUER';
    protected const CACHE_ISSUERS_DATE_KEY = 'BUCKAROO_PAYBYBANK_ISSUERS_CACHE_DATE';
    protected const CACHE_ISSUERS_KEY = 'BUCKAROO_PAYBYBANK_ISSUERS_CACHE';

    public function __construct()
    {
        parent::__construct('paybybank');
    }

    public function get(): array
    {
        $savedBankIssuer = \Context::getContext()->cookie->{self::CACHE_LAST_ISSUER_LABEL};

        $issuerArray = array_merge([
            'NTSBDEB1' => [
                'name' => 'N26',
                'logo' => 'N26.svg',
            ],
            'INGBNL2A' => [
                'name' => 'ING',
                'logo' => 'ING.svg',
            ],
            'ABNANL2A' => [
                'name' => 'ABN AMRO',
                'logo' => 'ABNAMRO.svg',
            ],
            'RABONL2U' => [
                'name' => 'Rabobank',
                'logo' => 'Rabobank.svg',
            ],
            'KNABNL2H' => [
                'name' => 'Knab',
                'logo' => 'KNAB.svg',
            ],
            'SNSBNL2A' => [
                'name' => 'SNS Bank',
                'logo' => 'SNS.svg',
            ],
            'RBRBNL21' => [
                'name' => 'RegioBank',
                'logo' => 'RegioBank.svg',
            ],
            'ASNBNL21' => [
                'name' => 'ASN Bank',
                'logo' => 'ASNBank.svg',
            ],
        ], parent::get());

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
        $issuers = $this->get();
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
}
