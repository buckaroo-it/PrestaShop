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

class IDeal extends PaymentMethod
{
    public $issuer;
    protected $data;

    public function __construct()
    {
        $this->type    = "ideal";
        $this->version = 2;
        $this->mode    = Config::getMode($this->type);
    }

    public function pay($customVars = array())
    {
        $this->data['customVars'][$this->type]['issuer'] = $this->getIssuer($this->issuer);

        return parent::pay();
    }

    public function refund()
    {
        return parent::refund();
    }

    public static function getIssuerList()
    {
        $issuerArray = array(
            'ABNAMRO'  => array(
                'name' => 'ABN AMRO',
                'logo' => 'logo_abn_s.gif',
            ),
            'ASNBANK'  => array(
                'name' => 'ASN Bank',
                'logo' => 'icon_asn.gif',
            ),
            'INGBANK'  => array(
                'name' => 'ING',
                'logo' => 'logo_ing_s.gif',
            ),
            'RABOBANK' => array(
                'name' => 'Rabobank',
                'logo' => 'logo_rabo_s.gif',
            ),
            'SNSBANK'  => array(
                'name' => 'SNS Bank',
                'logo' => 'logo_sns_s.gif',
            ),
            'SNSREGIO' => array(
                'name' => 'RegioBank',
                'logo' => 'logo_sns_s.gif',
            ),
            'TRIODOS'  => array(
                'name' => 'Triodos Bank',
                'logo' => 'logo_triodos.gif',
            ),
            'LANSCHOT' => array(
                'name' => 'Van Lanschot',
                'logo' => 'logo_lanschot_s.gif',
            ),
            'KNAB'     => array(
                'name' => 'Knab',
                'logo' => 'logo_knab_s.gif',
            ),
            'BUNQ'     => array(
                'name' => 'Bunq',
                'logo' => 'logo_bunq.png',
            ),
            'MOYONL21' => array(
                'name' => 'Moneyou',
                'logo' => 'MOYONL21.png',
            ),
            'HANDNL2A' => array(
                'name' => 'Handelsbanken',
                'logo' => 'HANDNL2A.png',
            ),
            'REVOLT21' => array(
                'name' => 'Revolut',
                'logo' => 'REVOLT21.png',
            ),
        );

        return $issuerArray;
    }

    protected function getIssuer($issuer)
    {

        $issuerCode = '';
        switch ($issuer) {
            case 'ABNAMRO':
                $issuerCode = 'ABNANL2A';
                break;
            case 'ASNBANK':
                $issuerCode = 'ASNBNL21';
                break;
            case 'INGBANK':
                $issuerCode = 'INGBNL2A';
                break;
            case 'RABOBANK':
                $issuerCode = 'RABONL2U';
                break;
            case 'SNSBANK':
                $issuerCode = 'SNSBNL2A';
                break;
            case 'SNSREGIO':
                $issuerCode = 'RBRBNL21';
                break;
            case 'TRIODOS':
                $issuerCode = 'TRIONL2U';
                break;
            case 'LANSCHOT':
                $issuerCode = 'FVLBNL22';
                break;
            case 'KNAB':
                $issuerCode = 'KNABNL2H';
                break;
            case 'BUNQ':
                $issuerCode = 'BUNQNL2A';
                break;
            case 'MOYONL21':
                $issuerCode = 'MOYONL21';
                break;
            case 'HANDNL2A':
                $issuerCode = 'HANDNL2A';
                break;
            case 'REVOLT21':
                $issuerCode = 'REVOLT21';
                break;
        }

        return $issuerCode;
    }
}
