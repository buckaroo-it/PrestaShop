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

    public function pay()
    {
        return null;
    }

    public function payTransfer($customVars)
    {
        $this->data['services'][$this->type]['action'] = 'Pay';
        $this->data['services'][$this->type]['version'] = $this->version;

        if (!empty($customVars['CustomerGender'])) {
            $this->data['customVars'][$this->type]['customergender'] = $customVars['CustomerGender'];
        }
        if (!empty($customVars['CustomerFirstName'])) {
            $this->data['customVars'][$this->type]['customerFirstName'] = $customVars['CustomerFirstName'];
        }
        if (!empty($customVars['CustomerLastName'])) {
            $this->data['customVars'][$this->type]['customerLastName'] = $customVars['CustomerLastName'];
        }
        if (!empty($customVars['CustomerEmail'])) {
            $this->data['customVars'][$this->type]['customeremail'] = $customVars['CustomerEmail'];
        }
        if (!empty($customVars['DateDue'])) {
            $this->data['customVars'][$this->type]['DateDue'] = $customVars['DateDue'];
        }
        if (!empty($customVars['CustomerCountry'])) {
            $this->data['customVars'][$this->type]['customercountry'] = $customVars['CustomerCountry'];
        }
        $this->data['customVars'][$this->type]['SendMail'] = $customVars['SendMail'];

        return parent::pay($customVars);
    }
}
