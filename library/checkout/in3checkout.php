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
        $this->payment_request->billingFirstName = $this->invoice_address->firstname;
        $this->payment_request->billingLastName = $this->invoice_address->lastname;
        $this->payment_request->billingInitials = initials($this->invoice_address->firstname);
        $this->payment_request->billingBirthDate = date(
            'Y-m-d',
            strtotime(
                Tools::getValue('customerbirthdate_y_billing') . '-' .
                Tools::getValue('customerbirthdate_m_billing') . '-' .
                Tools::getValue('customerbirthdate_d_billing')
            )
        );
        $this->payment_request->customerNumber = ($this->cart->id_customer) ?: 'guest';
        $this->payment_request->billingPhoneNumber = $this->getPhone();
        $address_components = $this->getAddressComponents($this->invoice_address->address1);
        if (empty($address_components['house_number'])) {
            $address_components['house_number'] = $this->invoice_address->address2;
        }
        $this->payment_request->billingStreet = $address_components['street'];
        $this->payment_request->billingHouseNumber = $address_components['house_number'];
        if (!empty($address_components['number_addition'])) {
            $this->payment_request->billingHouseNumberSuffix = $address_components['number_addition'];
        }
        $this->payment_request->billingPostalCode = $this->invoice_address->postcode;
        $this->payment_request->billingCity = $this->invoice_address->city;
        $this->payment_request->billingCountry = Tools::strtoupper(
            (new Country($this->invoice_address->id_country))->iso_code
        );
        $this->payment_request->billingEmail = $this->customer->email;

        $this->payment_request->addressesDiffer = false;
        if (!empty($this->shipping_address)) {
            $this->payment_request->addressesDiffer = true;
            $this->payment_request->shippingStreet = $address_components['street'];
            $this->payment_request->shippingHouseNumber = $address_components['house_number'];
            if (!empty($address_components['number_addition'])) {
                $this->payment_request->shippingHouseNumberSuffix = $address_components['number_addition'];
            }
            $this->payment_request->shippingPostalCode = $this->shipping_address->postcode;
            $this->payment_request->shippingCity = $this->shipping_address->city;
            $this->payment_request->shippingCountryCode = Tools::strtoupper(
                (new Country($this->shipping_address->id_country))->iso_code
            );
        }

        $this->customVars = [
            'articles' => $this->getArticles(),
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
     * @return string
     */
    public function getPhone()
    {
        $phone = Tools::getValue('customer_phone');
        if (is_scalar($phone)) {
            return (string) $phone;
        }

        return '';
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
}
