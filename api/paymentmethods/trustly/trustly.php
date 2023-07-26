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

class Trustly extends PaymentMethod
{
    public function __construct()
    {
        $this->type = 'trustly';
        $this->mode = Config::getMode($this->type);
    }

    public function getPayload($data)
    {
        $payload = [
            'customer' => [
                'firstName' => $data['first_name'],
                'lastName' => $data['last_name'],
            ],
            'country' => $data['country'],
        ];

        return $payload;
    }

    // @codingStandardsIgnoreStart
    public function pay($customVars = [])
    {
        $this->payload = $this->getPayload($customVars);

        return parent::pay();
    }
}
