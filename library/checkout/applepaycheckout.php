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

class ApplePayCheckout extends Checkout
{
    /**
     * Base64 encoded Apple Pay token posted by the express button, or an
     * empty string for the checkout payment method (Buckaroo payment page).
     *
     * @var string
     */
    private $expressPaymentData = '';

    /**
     * The Apple Pay token (the `token` object of the authorized payment) as
     * the express button posts it: JSON, sent to Buckaroo base64 encoded.
     */
    public static function resolveExpressPaymentData(): string
    {
        $token = Tools::getValue('applepay_token');
        if (!is_string($token) || $token === '') {
            return '';
        }

        $decoded = json_decode($token, true);
        if (!is_array($decoded) || empty($decoded['paymentData'])) {
            return '';
        }

        return base64_encode((string) json_encode($decoded));
    }

    public static function isExpressRequest(): bool
    {
        return Tools::getValue('buckaroo_express') === 'applepay';
    }

    final public function setCheckout()
    {
        parent::setCheckout();

        $this->expressPaymentData = self::isExpressRequest() ? self::resolveExpressPaymentData() : '';

        if ($this->expressPaymentData === '') {
            $this->customVars = [
                'servicesSelectableByClient' => 'applepay',
                'continueOnIncomplete' => '1',
            ];
        }
    }

    public function startPayment()
    {
        if ($this->expressPaymentData !== '') {
            $this->payment_response = $this->payment_request->payWithToken(
                $this->expressPaymentData,
                $this->getCustomerCardName()
            );

            return;
        }

        $this->payment_response = $this->payment_request->pay($this->customVars);
    }

    /**
     * The express payment is completed with the token; only follow a redirect
     * when Buckaroo asks for one.
     */
    public function isRedirectRequired()
    {
        if ($this->expressPaymentData !== '') {
            return !empty($this->payment_response) && $this->payment_response->isRedirectRequired();
        }

        return true;
    }

    public function isVerifyRequired()
    {
        return false;
    }

    protected function initialize()
    {
        $this->payment_request = PaymentRequestFactory::create(PaymentRequestFactory::REQUEST_TYPE_APPLEPAY);
    }

    private function getCustomerCardName(): string
    {
        $address = new Address((int) $this->cart->id_address_invoice);

        return trim($address->firstname . ' ' . $address->lastname);
    }
}
