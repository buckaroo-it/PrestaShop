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

include_once _PS_MODULE_DIR_ . 'buckaroo3/library/checkout/checkout.php';

class BuckarooPayPalCheckout extends Checkout
{

    protected $customVars = array();

    final public function setCheckout()
    {
        parent::setCheckout();

        $this->customVars = [
            "customer_name" => $this->invoice_address->firstname .' '. $this->invoice_address->lastname,
            "address" => $this->getAddress(),
            "phone" => $this->invoice_address->phone
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
        $this->payment_request = PaymentRequestFactory::create(PaymentRequestFactory::REQUEST_TYPE_PAYPAL);
    }

    /**
     * Get customer address
     *
     * @return array
     */
    protected function getAddress()
    {

        $address_components = $this->getAddressComponents($this->invoice_address->address1);
        if (empty($address_components['house_number'])) {
            $address_components['house_number'] = $this->invoice_address->address2;
        }
        $data = [
            "street"  => $address_components['street'],
            "street2" => $address_components['house_number'],
            "zipcode" => $this->invoice_address->postcode,
            "state"   => (int) new State((int) $this->invoice_address->id_state),
            "city"    => $this->invoice_address->city,
            "country" => Tools::strtoupper(
                (new Country($this->invoice_address->id_country))->iso_code
            ),
        ];

        return $data;
    }
}