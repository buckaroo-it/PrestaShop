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

use Buckaroo\PrestaShop\Src\Service\BuckarooConfigService;

class PayPerEmail extends PaymentMethod
{
    /**
     * @var BuckarooConfigService
     */
    protected $buckarooConfigService;

    public function __construct()
    {
        $this->type = 'payperemail';
        $this->version = '1';
        $this->mode = Config::getMode($this->type);
        $this->buckarooConfigService = new BuckarooConfigService();
    }

    public function pay($customVars = [])
    {
        $this->payload = $this->getPayload($customVars);

        return parent::executeCustomPayAction('paymentInvitation');
    }

    public function getPayload($data)
    {
        $paymentMethodsAllowed = $this->buckarooConfigService->getSpecificValueFromConfig('payperemail', 'allowed_payments');
        $dueDays = $this->buckarooConfigService->getSpecificValueFromConfig('payperemail', 'due_days');
        $sendInstructionEmail = $this->buckarooConfigService->getSpecificValueFromConfig('payperemail', 'send_instruction_email');

        $payload = [
            'customer' => [
                'gender' => $data['gender'],
                'firstName' => $data['first_name'],
                'lastName' => $data['last_name'],
            ],
            'email' => $data['email'],
            'merchantSendsEmail' => $sendInstructionEmail,
            'expirationDate' => date('Y-m-d', strtotime('+' . (int) $dueDays . 'day')),
            'paymentMethodsAllowed' => $paymentMethodsAllowed,
            'attachment' => '',
        ];

        return $payload;
    }
}
