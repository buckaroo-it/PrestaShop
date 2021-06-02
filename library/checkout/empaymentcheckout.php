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

include_once(_PS_MODULE_DIR_ . 'buckaroo3/library/checkout/checkout.php');

class EmpaymentCheckout extends Checkout
{

    protected $customVars = array();

    final public function setCheckout()
    {
        parent::setCheckout();
        $address_components = $this->getAddressComponents($this->invoice_address->address1);
        $this->customVars['CustomerAccountNumber'] = (string)Tools::getValue('empayment_account_number');
        $this->customVars['emailAddress'] = $this->customer->email;
        $this->customVars['FirstName'] = $this->invoice_address->firstname;
        $this->customVars['LastName'] = $this->invoice_address->lastname;
        $this->customVars['Initials'] = $this->invoice_address->firstname{0} . ". " . $this->invoice_address->lastname{0} . ".";//phpcs:ignore
        $this->customVars['ADDRESS'][0]['AddressType'] = 'HOM';
        $this->customVars['ADDRESS'][0]['ZipCode'] = $this->invoice_address->postcode;
        $this->customVars['ADDRESS'][0]['City'] = $this->invoice_address->city;
        if (!empty($address_components['street'])) {
            $this->customVars['ADDRESS'][0]['Street'] = $address_components['street'];
        }
        if (!empty($address_components['house_number'])) {
            $this->customVars['ADDRESS'][0]['Number'] = $address_components['house_number'];
        }
        if (!empty($address_components['number_addition'])) {
            $this->customVars['ADDRESS'][0]['NumberExtension'] = $address_components['number_addition'];
        }
        $this->customVars['ADDRESS'][0]['Country'] = '528';
    }

    public function isRedirectRequired()
    {
        return true;
    }

    public function isVerifyRequired()
    {
        return false;
    }

    public function startPayment()
    {
        $this->payment_response = $this->payment_request->emPay($this->customVars);
    }

    protected function initialize()
    {
        $this->payment_request = PaymentRequestFactory::create(PaymentRequestFactory::REQUEST_TYPE_EMPAYMENT);
    }
}
