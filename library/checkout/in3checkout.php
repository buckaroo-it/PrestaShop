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

class In3Checkout extends Checkout
{
    protected $customVars = [];

    final public function setCheckout()
    {
        parent::setCheckout();

        $this->addRequiredDescription();
        $this->customVars = [
            'billing' => $this->getBillingAddress(),
            'articles' => $this->getArticles(),
            'shipping' => $this->getShippingAddress(),
        ];
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
        $this->payment_request = PaymentRequestFactory::create(PaymentRequestFactory::REQUEST_TYPE_IN3);
    }

    protected function addRequiredDescription()
    {
        if (empty($this->payment_request->description)) {
            $this->payment_request->description = $this->payment_request->invoiceId;
        }
    }

    /**
     * Get customer phone
     *
     * @param $address
     *
     * @return string
     */
    public function getPhone($address)
    {
        // First check if 'customer_phone' value is available.
        $phone = Tools::getValue('customer_phone');

        // If it's not available, then check for 'phone_mobile' in the address.
        if (empty($phone) && !empty($address->phone_mobile)) {
            $phone = $address->phone_mobile;
        }

        // If both above are not available, then check for 'phone' in the address.
        if (empty($phone) && !empty($address->phone)) {
            $phone = $address->phone;
        }

        return $phone;
    }

    /**
     * Get order articles
     *
     * @return array
     */
    protected function getArticles()
    {
        $total = 0;
        $products = [];
        foreach ($this->products as $item) {
            $products[] = [
                'description' => $item['name'],
                'identifier' => $item['id_product'],
                'quantity' => $item['quantity'],
                'price' => round($item['price_wt'], 2),
                'vatPercentage' => $item['rate'],
            ];

            $total += round($item['price_wt'], 2);
        }

        $wrapping = $this->cart->getOrderTotal(true, CartCore::ONLY_WRAPPING);
        if ($wrapping > 0) {
            $products[] = [
                'description' => 'Wrapping',
                'identifier' => 'WRAP',
                'quantity' => 1,
                'price' => round($wrapping, 2),
            ];
            $total += round($wrapping, 2);
        }

        $discounts = $this->cart->getOrderTotal(true, CartCore::ONLY_DISCOUNTS);
        if ($discounts > 0) {
            $products[] = [
                'description' => 'Discounts',
                'identifier' => 'DISC',
                'quantity' => 1,
                'price' => -round($discounts, 2),
            ];
            $total -= round($discounts, 2);
        }

        $shipping = $this->cart->getOrderTotal(true, CartCore::ONLY_SHIPPING);
        if ($shipping > 0) {
            $products[] = [
                'description' => 'Shipping',
                'identifier' => 'SHIP',
                'quantity' => 1,
                'price' => round($shipping, 2),
            ];
            $total += round($shipping, 2);
        }

        if (abs($this->payment_request->amountDebit - $total) >= 0.01) {
            $products[] = [
                'description' => 'Other fee/discount',
                'identifier' => 'OFees',
                'quantity' => 1,
                'price' => round($this->payment_request->amountDebit - $total, 2),
            ];
        }

        return $products;
    }

    /**
     * Get billing address
     *
     * @return array
     */
    protected function getBillingAddress()
    {
        $data = [
            'recipient' => [
                'category' => 'B2C',
                'initials' => initials($this->invoice_address->firstname),
                'firstName' => $this->invoice_address->firstname,
                'lastName' => $this->invoice_address->lastname,
                'birthDate' => date(
                    'Y-m-d',
                    strtotime(
                        Tools::getValue('customerbirthdate_y_billing') . '-' .
                        Tools::getValue('customerbirthdate_m_billing') . '-' .
                        Tools::getValue('customerbirthdate_d_billing')
                    )
                ),
                'customerNumber' => ($this->cart->id_customer) ?: 'guest',
                'phone' => $this->getPhone($this->invoice_address),
                'country' => Tools::strtoupper(
                    (new Country($this->invoice_address->id_country))->iso_code
                ),
            ],
            'phone' => [
                'phone' => $this->getPhone($this->invoice_address),
            ],
            'email' => $this->customer->email,
        ];

        $data['address'] = $this->getAddressData($this->invoice_address);

        return $data;
    }

    protected function getAddressData($address)
    {
        $address_components = $this->getAddressComponents($address->address1);

        if (empty($address_components['house_number'])) {
            $address_components['house_number'] = $address->address2;
        }

        $data = [
            'street' => $address_components['street'],
            'houseNumber' => $address_components['house_number'],
            'zipcode' => $address->postcode,
            'city' => $address->city,
            'country' => Tools::strtoupper(
                (new Country($address->id_country))->iso_code
            ),
        ];

        if (!empty($address_components['number_addition'])) {
            $data['houseNumberAdditional'] = $address_components['number_addition'];
        }

        return $data;
    }

    /**
     * Get shipping address
     *
     * @return array|null
     */
    private function getShippingAddress()
    {
        if (!empty($this->shipping_address)) {
            return [
                'address' => $this->getAddressData($this->shipping_address),
            ];
        }

        return null;
    }
}
