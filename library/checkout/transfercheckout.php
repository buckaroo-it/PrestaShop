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

class TransferCheckout extends Checkout
{
    final public function setCheckout()
    {
        parent::setCheckout();

        $this->customVars([
            'CustomerEmail' => $this->customer->email,
            'CustomerFirstName' => $this->invoice_address->firstname,
            'CustomerLastName' => $this->invoice_address->lastname,
            'SendMail' => ((int) Configuration::get('BUCKAROO_TRANSFER_SENDMAIL') == 1 ? 'TRUE' : 'FALSE'), // phpcs:ignore
            'DateDue' => date('Y-m-d', strtotime('now + ' . (int) Configuration::get('BUCKAROO_TRANSFER_DATEDUE') . ' day')), // phpcs:ignore
            'CustomerCountry' => Tools::strtoupper((new Country($this->invoice_address->id_country))->iso_code),
        ]);
    }

    public function isRedirectRequired()
    {
        return false;
    }

    public function isVerifyRequired()
    {
        return false;
    }

    public function startPayment()
    {
        $this->payment_response = $this->payment_request->pay($this->customVars);
    }

    protected function initialize()
    {
        $this->payment_request = PaymentRequestFactory::create(PaymentRequestFactory::REQUEST_TYPE_TRANSFER);
    }
}
