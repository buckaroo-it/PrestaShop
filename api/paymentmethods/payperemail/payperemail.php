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
require_once dirname(__FILE__) . '/../paymentmethod.php';

class PayPerEmail extends PaymentMethod
{
    public function __construct()
    {
        $this->type = 'payperemail';
        $this->version = '1';
        $this->mode = Config::getMode($this->type);
    }

    public function pay($customVars = [])
    {
        $this->payload = $this->getPayload($customVars);

        return parent::executeCustomPayAction('paymentInvitation');
    }

    public function getPayload($data)
    {
        $payload = [
            'customer' => [
                'gender' => $data['gender'],
                'firstName' => $data['first_name'],
                'lastName' => $data['last_name'],
            ],
            'email' => $data['email'],
            'merchantSendsEmail' => Config::get('BUCKAROO_PAYPEREMAIL_SEND_EMAIL'),
            'expirationDate' => date('Y-m-d', strtotime('+' . (int) Config::get('BUCKAROO_PAYPEREMAIL_EXPIRE_DAYS') . 'day')),
            'paymentMethodsAllowed' => Config::get('BUCKAROO_PAYPEREMAIL_ALLOWED_METHODS'), // 'ideal,mastercard,paypal',
            'attachment' => '',
        ];

        return $payload;
    }
}
