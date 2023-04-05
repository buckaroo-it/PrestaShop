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
    }

    public function startPayment()
    {
        $sellerProtectionEnabled = Configuration::get('BUCKAROO_PAYPAL_SELLER_PROTECTION_ENABLED') == 1;
        if($sellerProtectionEnabled) {

            $state = new State((int) $this->invoice_address->id_state);

            $address = $this->getAddressComponents($this->invoice_address->address1);
            $this->customVars = array_merge(
                $this->customVars,
                [
                    'sellerProtection'   => true,
                    'CustomerName'       => $this->invoice_address->firstname." ".$this->invoice_address->lastname,
                    'ShippingPostalCode' => $this->invoice_address->postcode,
                    'ShippingCity'       => $this->invoice_address->city,
                    'ShippingStreet'     => $address['street'],
                    'ShippingHouse'      => $address['house_number'],
                    'StateOrProvince'    => $state !== null && $state->name !== null ? $state->name :'',
                    'Country'            => Tools::strtoupper(
                        (new Country($this->invoice_address->id_country))->iso_code
                    )
                ]
            );
        }
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
}
