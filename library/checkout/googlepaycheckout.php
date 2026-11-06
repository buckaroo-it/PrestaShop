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

class GooglePayCheckout extends Checkout
{
    /**
     * Base64 encoded Google Pay token posted by the express button, or an
     * empty string for the checkout payment method (Buckaroo payment page).
     *
     * @var string
     */
    private $expressPaymentData = '';

    /**
     * The Google Pay token (paymentMethodData.tokenizationData.token, a JSON
     * string) as the express button posts it, sent to Buckaroo base64 encoded.
     */
    public static function resolveExpressPaymentData(): string
    {
        $token = Tools::getValue('googlepay_token');
        if (!is_string($token) || $token === '') {
            return '';
        }

        $decoded = json_decode($token, true);
        if (!is_array($decoded) || empty($decoded['signedMessage']) || empty($decoded['protocolVersion'])) {
            return '';
        }

        return base64_encode($token);
    }

    public static function isExpressRequest(): bool
    {
        return Tools::getValue('buckaroo_express') === 'googlepay';
    }

    final public function setCheckout()
    {
        parent::setCheckout();

        $this->expressPaymentData = self::isExpressRequest() ? self::resolveExpressPaymentData() : '';

        if ($this->expressPaymentData === '') {
            $this->customVars = [
                'servicesSelectableByClient' => 'googlepay',
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
     * Google Pay returns a RequiredAction with a RedirectURL (status 790 -
     * Pending input) for the Buckaroo payment page. The express payment is
     * completed with the token; only follow a redirect when Buckaroo asks
     * for one (3-D Secure).
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
        $this->payment_request = PaymentRequestFactory::create(PaymentRequestFactory::REQUEST_TYPE_GOOGLEPAY);
    }

    private function getCustomerCardName(): string
    {
        $address = new Address((int) $this->cart->id_address_invoice);

        return trim($address->firstname . ' ' . $address->lastname);
    }
}
