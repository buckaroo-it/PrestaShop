<?php
/**
* 2014-2015 Buckaroo.nl
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
*  @copyright 2014-2015 Buckaroo.nl
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/

include_once(_PS_MODULE_DIR_ . 'buckaroo3/library/checkout/checkout.php');

class PayGarantByJunoCheckout extends Checkout
{

    protected $customVars = array();

    final public function setCheckout()
    {

        parent::setCheckout();
        $customerbirthdate = Tools::getValue('customerbirthdate');
        $invoicecustomerbirthdate = date(
            'Y-m-d',
            strtotime($customerbirthdate['year'] . '-' . $customerbirthdate['month'] . '-' . $customerbirthdate['day'])
        );



        if ((int)Configuration::get('BUCKAROO_PGBYJUNO_USENOTIFICATION')) {

            $sql = 'SELECT type FROM ' . _DB_PREFIX_ . 'gender where id_gender = ' . (int)($this->customer->id_gender);
            $gender_type = Db::getInstance()->getValue($sql);

            $this->customVars['CustomerFirstName'] = $this->invoice_address->firstname;

            $this->customVars['CustomerLastName'] = $this->invoice_address->lastname;
            $this->customVars['Customeremail'] = !empty($this->customer->email) ? $this->customer->email : '';
            $this->customVars['Customergender'] = ($gender_type == 0) ? '1' : ($gender_type == 1) ? '2' : '0';
            $this->payment_request->usenotification = 1;
            $this->customVars['Notificationtype'] = 'PaymentComplete';
            if ((int)(Configuration::get('BUCKAROO_PGBYJUNO_NOTIFICATIONDELAY')) > 0) {
                $this->customVars['Notificationdelay'] = date(
                    'Y-m-d',
                    strtotime('now + ' . (int)(Configuration::get('BUCKAROO_PGBYJUNO_NOTIFICATIONDELAY')) . ' day')
                );
            }
        }

        $this->customVars['CustomerBirthDate'] = $invoicecustomerbirthdate;
        $this->customVars['CustomerGender'] = Tools::getValue('BPE_BJ_Customergender');
        $this->customVars['CustomerAccountNumber'] = (string)Tools::getValue('bpe_bj_customer_account_number');
        $this->customVars['CustomerCode'] = $this->cart->id_customer;
        $this->customVars['CustomerFirstName'] = $this->invoice_address->firstname;
        $this->customVars['CustomerLastName'] = $this->invoice_address->lastname;
        $this->customVars['CustomerInitials'] = initials($this->invoice_address->firstname);
        $this->customVars['CustomerEmail'] = $this->customer->email;

        if (Tools::getValue('booByJunoPhone')) {
            $number = Buckaroo3::cleanUpPhone(Tools::getValue('booByJunoPhone'));

            if ($number['type'] == 'mobile') {
                $this->customVars['MobilePhoneNumber'] = $number['phone'];
            } else {
                $this->customVars['PhoneNumber'] = $number['phone'];
            }
        } else {
            if (Tools::getValue('booByJunoPhoneLand')) {
                $number = Buckaroo3::cleanUpPhone(Tools::getValue('booByJunoPhoneLand'));

                if ($number['type'] == 'mobile') {
                    $this->customVars['MobilePhoneNumber'] = $number['phone'];
                } else {
                    $this->customVars['PhoneNumber'] = $number['phone'];
                }
            }
            if (Tools::getValue('booByJunoPhoneMobile')) {
                $number = Buckaroo3::cleanUpPhone(Tools::getValue('booByJunoPhoneMobile'));

                if ($number['type'] == 'mobile') {
                    $this->customVars['MobilePhoneNumber'] = $number['phone'];
                } else {
                    $this->customVars['PhoneNumber'] = $number['phone'];
                }
            }
        }

        $address_components = $this->_getAddressComponents($this->invoice_address->address1);
        //customer address
        $this->customVars['ADDRESS'][0]['AddressType'] = 'INVOICE,SHIPPING';
        $this->customVars['ADDRESS'][0]['ZipCode'] = $this->invoice_address->postcode;
        $this->customVars['ADDRESS'][0]['City'] = $this->invoice_address->city;
        if (!empty($address_components['street'])) {
            $this->customVars['ADDRESS'][0]['Street'] = $address_components['street'];
        }
        if (!empty($address_components['house_number'])) {
            $this->customVars['ADDRESS'][0]['HouseNumber'] = $address_components['house_number'];
        }
        if (!empty($address_components['number_addition'])) {
            $this->customVars['ADDRESS'][0]['HouseNumberSuffix'] = $address_components['number_addition'];
        }

        $country = new Country($this->invoice_address->id_country);
        $this->customVars['ADDRESS'][0]['Country'] = Tools::strtoupper($country->iso_code);

        if ((int)Configuration::get('BUCKAROO_PGBYJUNO_DATEDUE') > -1) {
            $this->customVars['InvoiceDate'] = date(
                'Y-m-d',
                strtotime('now + ' . (int)Configuration::get('BUCKAROO_PGBYJUNO_DATEDUE') . ' day')
            );
        } else {
            $this->customVars['InvoiceDate'] = date('Y-m-d', strtotime('now + 14 day'));
        }
        $sepa_pgby = Configuration::get('BUCKAROO_PGBY_PAYMENT');
        if (!empty($sepa_pgby)) {
            $sepa_pgby = unserialize($sepa_pgby);
            $this->customVars['PaymentMethodsAllowed'] = implode(",", $sepa_pgby);
        }
        $this->customVars['DateDue'] = date('Y-m-d', strtotime($this->customVars['InvoiceDate'] . ' + 14 day'));
        $this->customVars['AmountVat'] = (string)round(
            ($this->cart->getOrderTotal(true) - $this->cart->getOrderTotal(false)),
            2
        );
        $this->customVars['SendMail'] = ((int)Configuration::get('BUCKAROO_PGBYJUNO_SENDMAIL') == 1 ? 'TRUE' : 'FALSE');
    }

    public function isRedirectRequired()
    {
        return false;
    }

    public function startPayment()
    {
        $this->payment_response = $this->payment_request->paymentInvitation($this->customVars);
    }

    protected function initialize()
    {
        $this->payment_request = PaymentRequestFactory::create(PaymentRequestFactory::REQUEST_TYPE_PAYGARANTBYJUNO);
    }
}
