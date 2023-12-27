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
if (!defined('_PS_VERSION_')) {
    exit;
}

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
        switch ($issuer) {
            case 'MOYONL21':
            case 'HANDNL2A':
            case 'REVOLT21':
            case 'BANKNL2Y':
                return $issuer;
            case 'ABNAMRO':
                return 'ABNANL2A';
            case 'ASNBANK':
                return 'ASNBNL21';
            case 'INGBANK':
                return 'INGBNL2A';
            case 'RABOBANK':
                 return 'RABONL2U';
            case 'SNSBANK':
                return 'SNSBNL2A';
            case 'SNSREGIO':
                return 'RBRBNL21';
            case 'TRIODOS':
                return 'TRIONL2U';
            case 'LANSCHOT':
                 return 'FVLBNL22';
            case 'KNAB':
                return 'KNABNL2H';
            case 'BUNQ':
                return 'BUNQNL2A';
            default:
                return '';
        }
    }
}
