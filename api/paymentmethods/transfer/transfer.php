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

class Transfer extends PaymentMethod
{
    //put your code here
    public function __construct()
    {
        $this->type = "transfer";
        $this->version = 1;
        $this->mode = Config::getMode($this->type);
    }

    public function pay($customVars = array())
    {
        $this->payload = $this->getPayload($customVars);
        return parent::pay();
    }

    public function getPayload($data)
    {

        $payload = [
                'customer' => [
                    'firstName' => $data['CustomerFirstName'],
                    'lastName'  => $data['CustomerLastName']
                ],
                'email'    => $data['CustomerEmail'],
                'country'  => $data['CustomerCountry'],
                'dateDue'  => $data['DateDue'],
                'sendMail' => $data['SendMail']
        ];
        return $payload;        
    }
}
