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

class TransferCheckout extends Checkout
{

    final public function setCheckout()
    {

        parent::setCheckout();

        $this->customVars['CustomerEmail']     = $this->customer->email;
        $this->customVars['CustomerFirstName'] = $this->invoice_address->firstname;
        $this->customVars['CustomerLastName']  = $this->invoice_address->lastname;
        $this->customVars['SendMail']          = ((int) Configuration::get('BUCKAROO_TRANSFER_SENDMAIL') == 1 ? 'TRUE' : 'FALSE');
        $this->customVars['DateDue']           = date(
            'Y-m-d',
            strtotime('now + ' . (int) Configuration::get('BUCKAROO_TRANSFER_DATEDUE') . ' day')
        );
        $country                             = new Country($this->invoice_address->id_country);
        $this->customVars['CustomerCountry'] = Tools::strtoupper($country->iso_code);

        if ((int) Configuration::get('BUCKAROO_TRANSFER_USENOTIFICATION')) {
            $sql         = 'SELECT type FROM ' . _DB_PREFIX_ . 'gender where id_gender = ' . (int) ($this->customer->id_gender);
            $gender_type = Db::getInstance()->getValue($sql);

            $this->customVars['CustomerFirstName'] = $this->invoice_address->firstname;

            $this->customVars['CustomerLastName']   = $this->invoice_address->lastname;
            $this->customVars['Customeremail']      = !empty($this->customer->email) ? $this->customer->email : '';
            $this->customVars['Customergender']     = ($gender_type == 0) ? '1' : ($gender_type == 1) ? '2' : '0';
            $this->payment_request->usenotification = 1;
            $this->customVars['Notificationtype']   = 'PaymentComplete';
            if ((int) (Configuration::get('BUCKAROO_TRANSFER_NOTIFICATIONDELAY')) > 0) {
                $this->customVars['Notificationdelay'] = date(
                    'Y-m-d',
                    strtotime('now + ' . (int) (Configuration::get('BUCKAROO_TRANSFER_NOTIFICATIONDELAY')) . ' day')
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
        $this->payment_response = $this->payment_request->payTransfer($this->customVars);
    }

    protected function initialize()
    {
        $this->payment_request = PaymentRequestFactory::create(PaymentRequestFactory::REQUEST_TYPE_TRANSFER);
    }
}
