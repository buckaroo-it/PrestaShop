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
        return null;
    }

    public function getPayload($customVars)
    {

        $payload = array(
                'customer' => [
                    'firstName' => $customVars['CustomerFirstName'],
                    'lastName' => $customVars['CustomerLastName']
                ],
                'email' => $customVars['CustomerEmail'],
                'country' => $customVars['CustomerCountry'],
                'dateDue' =>  $customVars['DateDue'],
                'sendMail' => $customVars['SendMail']
        );

        return $payload;
        
    }

    public function payTransfer($customVars)
    {
        $this->payload = $this->getPayload($customVars);
        return parent::pay();
    }
}
