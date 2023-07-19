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
    private const SESSION_LAST_ISSUER_LABEL = 'buckaroo_last_payByBank_issuer';
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
        Context::getContext()->cookie->__set(self::SESSION_LAST_ISSUER_LABEL, $this->issuer);

        if($this->issuer === 'INGBNL2A' && Context::getContext()->isMobile()){
            $this->type = 'ideal'; // send ideal request if issuer is ING and is on mobile
        }
        $this->payload['issuer'] = is_string($this->issuer) ? $this->issuer : '';
        return parent::pay();
    }

    public function refund()
    {
        return parent::refund();
    }

    public function getIssuerList()
    {
        $savedBankIssuer = Context::getContext()->cookie->{self::SESSION_LAST_ISSUER_LABEL};

        $issuerArray =  array(
            'ABNANL2A' => array(
                'name' => 'ABN AMRO',
                'logo' => 'abnamro.png',
            ),
            'ASNBNL21' => array(
                'name' => 'ASN Bank',
                'logo' => 'asnbank.png',
            ),
            'INGBNL2A' => array(
                'name' => 'ING',
                'logo' => 'ing.png',
            ),
            'RABONL2U' => array(
                'name' => 'Rabobank',
                'logo' => 'rabobank.png',
            ),
            'SNSBNL2A' => array(
                'name' => 'SNS Bank',
                'logo' => 'sns.png',
            ),
            'RBRBNL21' => array(
                'name' => 'RegioBank',
                'logo' => 'regiobank.png',
            ),
            'KNABNL2H' => array(
                'name' => 'Knab',
                'logo' => 'knab.png',
            ),
            'NTSBDEB1' => array(
                'name' => 'N26',
                'logo' => 'n26.png',
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
            return 'paybybank/'.$selectedIssuer['logo'];
        } else {
            return 'buckaroo_paybybank.gif?v';
        }
    }

}
