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

require_once dirname(__FILE__) . '/../paymentmethod.php';

class Idin extends PaymentMethod
{
    public $issuer;
    protected $data;

    public function __construct()
    {
        $this->type = 'idin';
        $this->version = 0;
    }

    public function verify($customVars = [])
    {
        $this->payload['issuer'] = $this->getIssuer($this->issuer);

        if (isset($customVars['cid'])) {
            $this->payload['additionalParameters']['cid'] = $customVars['cid'];
        }

        return parent::verify();
    }

    // @codingStandardsIgnoreStart
    public function pay($customVars = [])
    {
        // @codingStandardsIgnoreEnd
        return null;
    }

    protected function getIssuer($issuer)
    {
        $issuerCode = '';
        switch ($issuer) {
            case 'BANKNL2Y':
                $issuerCode = 'BANKNL2Y';
                break;
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
