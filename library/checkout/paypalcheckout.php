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

class PayPalCheckout extends Checkout
{
    protected $customVars = [];

    /**
     * PayPal order the shopper approved with the express button; empty for
     * the checkout payment method (redirect to PayPal).
     *
     * @var string
     */
    private $expressOrderId = '';

    /**
     * The PayPal order ID the express button posts.
     */
    public static function resolveExpressOrderId(): string
    {
        $orderId = Tools::getValue('paypal_order_id');
        if (!is_string($orderId)) {
            return '';
        }

        $orderId = trim($orderId);

        return preg_match('/^[A-Za-z0-9-]{1,64}$/', $orderId) ? $orderId : '';
    }

    public static function isExpressRequest(): bool
    {
        return Tools::getValue('buckaroo_express') === 'paypal';
    }

    final public function setCheckout()
    {
        parent::setCheckout();

        $this->expressOrderId = self::isExpressRequest() ? self::resolveExpressOrderId() : '';

        $sellerProtection = $this->buckarooConfigService->getConfigValue('paypal', 'seller_protection');

        // Data required for Seller Protection payload. An express order uses
        // the shipping address the shopper picked in PayPal.
        if ($sellerProtection == 1) {
            $address = $this->expressOrderId !== '' ? $this->shipping_address : $this->invoice_address;
            $this->customVars = [
                'customer' => [
                    'name' => $address->firstname . ' ' . $address->lastname,
                ],
                'address' => $this->getAddress($address),
                'phone' => [
                    'mobile' => $address->phone ?: $address->phone_mobile,
                ],
            ];
        }
    }

    public function startPayment()
    {
        if ($this->expressOrderId !== '') {
            $this->payment_response = $this->payment_request->payExpress($this->expressOrderId, $this->customVars);

            return;
        }

        $this->payment_response = $this->payment_request->pay($this->customVars);
    }

    /**
     * The express payment completes the approved PayPal order; only follow a
     * redirect when Buckaroo asks for one.
     */
    public function isRedirectRequired()
    {
        if ($this->expressOrderId !== '') {
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
        $this->payment_request = PaymentRequestFactory::create(PaymentRequestFactory::REQUEST_TYPE_PAYPAL);
    }

    /**
     * Customer address for seller protection
     *
     * @return array
     */
    protected function getAddress(Address $customerAddress)
    {
        $address_components = $this->getAddressComponents($customerAddress->address1);
        if (empty($address_components['house_number'])) {
            $address_components['house_number'] = $customerAddress->address2;
        }

        $address = [
            'street' => $address_components['street'] ?? '',
            'street2' => $address_components['house_number'] ?? '',
            'zipcode' => $customerAddress->postcode,
            'city' => $customerAddress->city,
            'country' => Tools::strtoupper(
                (new Country($customerAddress->id_country))->iso_code
            ),
        ];

        if ($customerAddress->id_state) {
            $state = new State((int) $customerAddress->id_state);
            $stateName = $state->name;
            if (is_array($stateName)) {
                $stateName = reset($stateName) ?: null;
            }
            if (!empty($stateName)) {
                $address['state'] = (string) $stateName;
            }
        }

        return $address;
    }
}
