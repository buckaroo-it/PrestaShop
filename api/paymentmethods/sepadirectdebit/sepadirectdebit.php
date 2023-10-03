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

class SepaDirectDebit extends PaymentMethod
{
    public function __construct()
    {
        $this->type = 'sepadirectdebit';
        $this->version = '1';
        $this->mode = Config::getMode($this->type);
    }

    public function pay($customVars = [])
    {
        return null;
    }

    public function getPayload($customVars)
    {
        $payload = [
            'iban' => $customVars['iban'],
            'bic' => $customVars['bic'],
            'collectdate' => date('d-m-Y'),
            'customer' => $customVars['customer'],
        ];

        return $payload;
    }

    public function payDirectDebit($customVars)
    {
        $this->payload = $this->getPayload($customVars);

        return parent::pay();
    }
}
