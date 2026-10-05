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
include_once _PS_MODULE_DIR_ . 'buckaroo3/library/checkout/checkout.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

class CreditCardCheckout extends Checkout
{
    protected $customVars = [];

    /**
     * Resolve the selected credit-card brand/issuer from the request.
     *
     * Third-party checkouts may omit nested form fields or drop query-string
     * parameters, so both POST field names used by this module are checked.
     */
    public static function resolveIssuer(): string
    {
        $candidates = [
            Tools::getValue('BPE_CreditCard'),
            Tools::getValue('cardCode'),
        ];

        foreach ($candidates as $candidate) {
            $issuer = self::normalizeIssuer($candidate);
            if ($issuer !== '') {
                return $issuer;
            }
        }

        return '';
    }

    private static function normalizeIssuer($value): string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }

        $issuer = trim((string) $value);

        if ($issuer === '' || $issuer === '0') {
            return '';
        }

        return Tools::strtolower($issuer);
    }

    final public function setCheckout()
    {
        parent::setCheckout();
        $this->payment_request->issuer = self::resolveIssuer();
    }

    public function startPayment()
    {
        $this->payment_response = $this->payment_request->pay($this->customVars);
    }

    public function isRedirectRequired()
    {
        return true;
    }

    public function isVerifyRequired()
    {
        return false;
    }

    protected function initialize()
    {
        $this->payment_request = PaymentRequestFactory::create(PaymentRequestFactory::REQUEST_TYPE_CREDITCARD);
    }
}
