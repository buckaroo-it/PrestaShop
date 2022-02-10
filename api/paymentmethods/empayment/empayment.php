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

class Empayment extends PaymentMethod
{

    public function __construct()
    {
        $this->type = "empayment";
        $this->version = 1;
        $this->mode = Config::getMode('EMPAYMENT');
        //$this->returnUrl = 'http://localhost/trunk/buckarooTest/response.php';
    }

    public function pay($customVars = array())
    {
        return null;
    }

    public function emPay($customVars)
    {
        $this->data['customVars'][$this->type]['reference'] = $this->invoiceId;
        $this->data['customVars'][$this->type]['emailAddress'] = $customVars['emailAddress'];
        $this->data['customVars'][$this->type]['FirstName']['value'] = $customVars['FirstName'];
        $this->data['customVars'][$this->type]['FirstName']['group'] = 'person';
        $this->data['customVars'][$this->type]['LastName']['value'] = $customVars['LastName'];
        $this->data['customVars'][$this->type]['LastName']['group'] = 'person';
        $this->data['customVars'][$this->type]['Initials']['value'] = $customVars['Initials'];
        $this->data['customVars'][$this->type]['Initials']['group'] = 'person';
        $this->data['customVars'][$this->type]['browserAgent']['value'] = $_SERVER['HTTP_USER_AGENT'];
        $this->data['customVars'][$this->type]['browserAgent']['group'] = 'clientInfo';

        $this->data['customVars'][$this->type]['Type']['value'] = 'DOM';
        $this->data['customVars'][$this->type]['Type']['group'] = 'bankaccount';
        /*
        $this->data['customVars'][$this->type]['DomesticAccountHolderName']['value'] = $customVars['AccountHolder'];
        $this->data['customVars'][$this->type]['DomesticAccountHolderName']['group'] = 'bankaccount';
         */
        $this->data['customVars'][$this->type]['DomesticCountry']['value'] = '528';
        $this->data['customVars'][$this->type]['DomesticCountry']['group'] = 'bankaccount';
        /*
        $this->data['customVars'][$this->type]['DomesticBankIdentifier']['value'] = $customVars['BankIdentifier'];
        $this->data['customVars'][$this->type]['DomesticBankIdentifier']['group'] = 'bankaccount';
        $this->data['customVars'][$this->type]['DomesticAccountNumber']['value'] = $customVars['AccountNumber'];
        $this->data['customVars'][$this->type]['DomesticAccountNumber']['group'] = 'bankaccount';
         */
        $this->data['customVars'][$this->type]['Collect']['value'] = '1';
        $this->data['customVars'][$this->type]['Collect']['group'] = 'bankaccount';

        foreach ($customVars['ADDRESS'] as $key => $adress) {
            foreach ($adress as $key2 => $value) {
                $this->data['customVars'][$this->type][$key2][$key]['value'] = $value;
                $this->data['customVars'][$this->type][$key2][$key]['group'] = 'address';
            }
        }

        return parent::pay();
    }
}
