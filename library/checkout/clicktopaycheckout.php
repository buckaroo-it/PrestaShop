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

class ClickToPayCheckout extends Checkout
{
    /**
     * Read the identifier and encrypted transient token that the Drop-in UI
     * posted along with the payment form.
     *
     * @return array{identifier: string, transientToken: string}
     */
    public static function resolveDropInPaymentData(): array
    {
        return [
            'identifier' => self::normalizeToken(Tools::getValue('clicktopay_identifier')),
            'transientToken' => self::normalizeToken(Tools::getValue('clicktopay_transient_token')),
        ];
    }

    private static function normalizeToken($value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return trim($value);
    }

    final public function setCheckout()
    {
        parent::setCheckout();
        $this->customVars = array_merge($this->customVars, self::resolveDropInPaymentData());
    }

    public function startPayment()
    {
        $this->payment_response = $this->payment_request->pay($this->customVars);
    }

    /**
     * A recognized shopper pays with an already tokenized card, so the
     * transaction usually completes without leaving the shop. Only follow a
     * redirect when Buckaroo asks for one, e.g. for a 3DS challenge.
     */
    public function isRedirectRequired()
    {
        return !empty($this->payment_response) && $this->payment_response->isRedirectRequired();
    }

    public function isVerifyRequired()
    {
        return false;
    }

    protected function initialize()
    {
        $this->payment_request = PaymentRequestFactory::create(PaymentRequestFactory::REQUEST_TYPE_CLICKTOPAY);
    }
}
