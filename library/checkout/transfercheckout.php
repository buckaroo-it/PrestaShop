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
    public function __construct($cart)
    {
        parent::__construct($cart);
    }

    final public function setCheckout()
    {
        parent::setCheckout();

        $sendMail = $this->buckarooConfigService->getConfigValue('transfer', 'send_instruction_email');
        $dueDate = $this->buckarooConfigService->getConfigValue('transfer', 'due_days');


        $this->customVars = [
            'customer' => [
                'firstName' => $this->invoice_address->firstname,
                'lastName' => $this->invoice_address->lastname,
            ],
            'email' => $this->customer->email,
            'country' => Tools::strtoupper((new Country($this->invoice_address->id_country))->iso_code),
            'dateDue' => date('Y-m-d', strtotime('now + ' . (int) $dueDate . ' day')),
            'sendMail' => ((int) $sendMail == 1 ? 'TRUE' : 'FALSE')
        ];
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
