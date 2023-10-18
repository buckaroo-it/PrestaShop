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

class PayPerEmailCheckout extends Checkout
{
    protected $customVars = [];

    final public function setCheckout()
    {
        parent::setCheckout();

        $paymentMethodsAllowed = $this->buckarooConfigService->getSpecificValueFromConfig('payperemail', 'allowed_payments');
        $dueDays = $this->buckarooConfigService->getSpecificValueFromConfig('payperemail', 'due_days');
        $sendInstructionEmail = $this->buckarooConfigService->getSpecificValueFromConfig('payperemail', 'send_instruction_email');

        $this->customVars = [
            'customer' => [
                'gender' => Tools::getValue('bpe_payperemail_person_gender'),
                'firstName' => $this->invoice_address->firstname,
                'lastName' => $this->invoice_address->lastname,
            ],
            'email' => $this->customer->email,
            'merchantSendsEmail' => $sendInstructionEmail,
            'expirationDate' => date('Y-m-d', strtotime('+' . (int) $dueDays . 'day')),
            'paymentMethodsAllowed' => $paymentMethodsAllowed,
            'attachment' => '',
        ];
    }

    public function startPayment()
    {
        $this->payment_response = $this->payment_request->pay($this->customVars);
    }

    public function isRedirectRequired()
    {
        return false;
    }

    public function isVerifyRequired()
    {
        return false;
    }

    protected function initialize()
    {
        $this->payment_request = PaymentRequestFactory::create(PaymentRequestFactory::REQUEST_TYPE_PAYPEREMAIL);
    }
}
