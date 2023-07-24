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

class In3 extends PaymentMethod
{
    public function __construct()
    {
        $this->type = "in3";
        $this->mode = Config::getMode($this->type);
    }

    public function pay($customVars = array())
    {
        $this->payload = $this->getPayload($customVars);
        return parent::executeCustomPayAction('payInInstallments');
    }

    public function getPayload($data)
    {
        $payload = [
            'version'           => $this->version,
            'description'       => $data['description'],
            'invoiceDate'       => date("d-m-Y"),
            'customerType'      => 'Debtor',
            'email'             => $data['email'],
            'phone'             => [ 'mobile' => $data['phone'] ],
            'articles'          => $data['articles'],
            'customer'          => $data['customer'],
            'address'           => $data['address'],
        ];
        return $payload;
    }
}
