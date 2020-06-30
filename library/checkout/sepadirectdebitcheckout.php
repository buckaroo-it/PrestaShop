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

class SepaDirectdebitCheckout extends Checkout
{

    protected $customVars = array();

    final public function setCheckout()
    {

        parent::setCheckout();


        $this->payment_request->customeraccountname = (string)Tools::getValue(
            'bpe_sepadirectdebit_bank_account_holder'
        );
        $this->payment_request->CustomerBIC = (string)Tools::getValue('bpe_sepadirectdebit_bic');
        $this->payment_request->CustomerIBAN = (string)Tools::getValue('bpe_sepadirectdebit_iban');


        $sql = 'SELECT type FROM ' . _DB_PREFIX_ . 'gender where id_gender = ' . (int)($this->customer->id_gender);
        $gender_type = Db::getInstance()->getValue($sql);
        $this->customVars['CustomerFirstName'] = $this->invoice_address->firstname;
        $this->customVars['CustomerLastName'] = $this->invoice_address->lastname;
        $this->customVars['Customeremail'] = !empty($this->customer->email) ? $this->customer->email : '';
        $this->customVars['Customergender'] = ($gender_type == 0) ? '1' : ($gender_type == 1) ? '2' : '0';
        if ((int)Configuration::get('BUCKAROO_SDD_USENOTIFICATION')) {
            $this->payment_request->usenotification = 1;
            $this->customVars['Notificationtype'] = 'PreNotification';
            if ((int)(Configuration::get('BUCKAROO_SDD_NOTIFICATIONDELAY')) > 0) {
                $this->customVars['Notificationdelay'] = date(
                    'Y-m-d',
                    strtotime('now + ' . (int)(Configuration::get('BUCKAROO_SDD_NOTIFICATIONDELAY')) . ' day')
                );
            }
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
        $this->payment_request = PaymentRequestFactory::create(PaymentRequestFactory::REQUEST_TYPE_SEPADIRECTDEBIT);
    }
}
