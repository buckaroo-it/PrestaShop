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

require_once(dirname(__FILE__) . '/../paymentmethod.php');

class Tinka extends PaymentMethod
{
    public function __construct()
    {
        $this->type = "tinka";
        $this->mode = Config::getMode($this->type);
    }

    public function getPayload($data)
    {
        $payload = [
            'description' => 'This is a test order',
            'paymentMethod' => 'Credit',
            'deliveryMethod' => 'Locker',
            'deliveryDate' => date('Y-m-d'),
            'articles' => $data['articles'],
            'customer' => $data['customer'],
            'billing' => [
                'recipient' => [
                    'lastNamePrefix' => 'the',
                ],
                'email' => $data['email'],
                'phone' => [
                    'mobile' => $data['billing']['phone']
                ],
                'address' => $data['billing']['address']
            ],
            'shipping' => [
                'recipient' => [
                    'lastNamePrefix' => 'the',
                ],
                'email' => $data['email'],
                'phone' => [
                    'mobile' => $data['shipping']['phone'],
                ],
                'address' => $data['shipping']['address']
            ],
        ];
        return $payload;
    }

    public function pay($customVars = array())
    {
        $this->payload = $this->getPayload($customVars);
        return parent::pay();
    }
}