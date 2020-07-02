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

class DirectdebitCheckout extends Checkout
{

    protected $customVars = array();

    final public function setCheckout()
    {

        parent::setCheckout();


        $this->payment_request->customeraccountname = (string)Tools::getValue('bpe_directdebit_bank_account_holder');
        $this->payment_request->customeraccountnumber = (string)Tools::getValue('bpe_directdebit_bank_account_number');

        $sql = 'SELECT type FROM ' . _DB_PREFIX_ . 'gender where id_gender = ' . (int)($this->customer->id_gender);
        $gender_type = Db::getInstance()->getValue($sql);

        $this->customVars['CustomerFirstName'] = $this->invoice_address->firstname;
        $this->customVars['CustomerLastName'] = $this->invoice_address->lastname;
        $this->customVars['Customeremail'] = !empty($this->customer->email) ? $this->customer->email : '';
        $this->customVars['Customergender'] = ($gender_type == 0) ? '1' : ($gender_type == 1) ? '2' : '0';

        if ((int)Configuration::get('BUCKAROO_DD_USECREDITMANAGMENT')) {
            $this->payment_request->usecreditmanagment = 1;
            $sql = 'SELECT type FROM ' . _DB_PREFIX_ . 'gender where id_gender = ' . $this->customer->id_gender;
            $gender_type = Db::getInstance()->getValue($sql);

            $this->customVars['MaxReminderLevel'] = (int)Configuration::get('BUCKAROO_DD_MAXREMINDERLEVEL');
            $this->customVars['CustomerCode'] = $this->cart->id_customer;
            //$this->customVars['CompanyName'] = '';
            $this->customVars['CustomerInitials'] = initials($this->invoice_address->firstname);
            if (Tools::getIsset($this->customer->birthday)) {
                $this->customVars['CustomerBirthDate'] = date(
                    'Y-m-d',
                    strtotime($this->customer->birthday)
                );
            } //1983-09-28

            //Resolve phone number
            if (Tools::getValue('booDirectdebitPhone')) {
                $number = Buckaroo3::cleanUpPhone(Tools::getValue('booDirectdebitPhone'));

                if ($number['type'] == 'mobile') {
                    $this->customVars['MobilePhoneNumber'] = $number['phone'];
                } else {
                    $this->customVars['PhoneNumber'] = $number['phone'];
                }
            } else {
                if (Tools::getValue('booDirectdebitPhoneLand')) {
                    $number = Buckaroo3::cleanUpPhone(Tools::getValue('booDirectdebitPhoneLand'));

                    if ($number['type'] == 'mobile') {
                        $this->customVars['MobilePhoneNumber'] = $number['phone'];
                    } else {
                        $this->customVars['PhoneNumber'] = $number['phone'];
                    }
                }
                if (Tools::getValue('booDirectdebitPhoneMobile')) {
                    $number = Buckaroo3::cleanUpPhone(Tools::getValue('booDirectdebitPhoneMobile'));

                    if ($number['type'] == 'mobile') {
                        $this->customVars['MobilePhoneNumber'] = $number['phone'];
                    } else {
                        $this->customVars['PhoneNumber'] = $number['phone'];
                    }
                }
            }

            $address_components = $this->_getAddressComponents($this->invoice_address->address1);
            //customer address
            $this->customVars['ADDRESS']['ZipCode'] = $this->invoice_address->postcode;
            $this->customVars['ADDRESS']['City'] = $this->invoice_address->city;
            if (!empty($address_components['street'])) {
                $this->customVars['ADDRESS']['Street'] = $address_components['street'];
            }
            if (!empty($address_components['house_number'])) {
                $this->customVars['ADDRESS']['HouseNumber'] = $address_components['house_number'];
            }
            if (!empty($address_components['number_addition'])) {
                $this->customVars['ADDRESS']['HouseNumberSuffix'] = $address_components['number_addition'];
            }
            $country = new Country($this->invoice_address->id_country);
            $this->customVars['ADDRESS']['Country'] = Tools::strtoupper($country->iso_code);

            $sepa_dd = Configuration::get('BUCKAROO_DD_PAYMENT');
            if (!empty($sepa_dd)) {
                $sepa_dd = unserialize($sepa_dd);
                $this->customVars['PaymentMethodsAllowed'] = implode(",", $sepa_dd);
            }

            //invoice
            $this->customVars['InvoiceDate'] = date(
                'Y-m-d',
                strtotime('now + ' . (int)Configuration::get('BUCKAROO_DD_INVOICEDELAY') . ' day')
            );
            $this->customVars['DateDue'] = date(
                'Y-m-d',
                strtotime(
                    $this->customVars['InvoiceDate'] . ' + ' . (int)Configuration::get('BUCKAROO_DD_DATEDUE') . ' day'
                )
            );

            $this->customVars['AmountVat'] = (string)round(
                ($this->cart->getOrderTotal(true) - $this->cart->getOrderTotal(false)),
                2
            );
        }
    }

    public function isRedirectRequired()
    {
        return false;
    }

    public function startPayment()
    {
        $this->payment_response = $this->payment_request->payDirectDebit($this->customVars);
    }

    protected function initialize()
    {
        $this->payment_request = PaymentRequestFactory::create(PaymentRequestFactory::REQUEST_TYPE_DIRECTDEBIT);
    }
}
