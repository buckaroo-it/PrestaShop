<?php
/**
* 2014-2015 Buckaroo.nl
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
*  @copyright 2014-2015 Buckaroo.nl
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/

require_once(dirname(__FILE__) . '/../paymentmethod.php');

class PayPerEmail extends PaymentMethod
{
    public $customeraccountname;
    public $CustomerBIC;
    public $CustomerIBAN;

    public function __construct()
    {
        $this->type = "payperemail";
        $this->version = '1';
        $this->mode = Config::getMode('SDD');
    }

    public function pay($customVars = Array())
    {
        return null;
    }

    public function paymentInvitation($customVars)
    {
        $this->data['services'][$this->type]['action'] = 'PaymentInvitation';
        $this->data['services'][$this->type]['version'] = $this->version;

        $this->data['customVars'][$this->type]['customergender'] = $customVars['Customergender'];
        $this->data['customVars'][$this->type]['CustomerEmail'] = $customVars['Customeremail'];
        $this->data['customVars'][$this->type]['CustomerFirstName'] = $customVars['CustomerFirstName'];
        $this->data['customVars'][$this->type]['CustomerLastName'] = $customVars['CustomerLastName'];
        $this->data['customVars'][$this->type]['MerchantSendsEmail'] = 'false';
        if (!empty($customVars['PaymentMethodsAllowed'])) {
            $this->data['customVars'][$this->type]['PaymentMethodsAllowed'] = $customVars['PaymentMethodsAllowed'];
        }

        $this->data['currency'] = $this->currency;
        $this->data['amountDebit'] = $this->amountDedit;
        $this->data['amountCredit'] = $this->amountCredit;
        $this->data['invoice'] = $this->invoiceId;
        $this->data['order'] = $this->orderId;
        $this->data['description'] = $this->description;
        $this->data['returnUrl'] = $this->returnUrl;
        $this->data['mode'] = $this->mode;

        if ($this->usecreditmanagment) {

            $this->data['services']['creditmanagement']['action'] = 'Invoice';
            $this->data['services']['creditmanagement']['version'] = '1';
            $this->data['customVars']['creditmanagement']['MaxReminderLevel'] = $customVars['MaxReminderLevel'];
            $this->data['customVars']['creditmanagement']['DateDue'] = $customVars['DateDue'];
            $this->data['customVars']['creditmanagement']['InvoiceDate'] = $customVars['InvoiceDate'];
            if (Tools::getIsset($customVars['CustomerCode'])) {
                $this->data['customVars']['creditmanagement']['CustomerCode'] = $customVars['CustomerCode'];
            }
            if (!empty($customVars['CompanyName'])) {
                $this->data['customVars']['creditmanagement']['CompanyName'] = $customVars['CompanyName'];
            }
            $this->data['customVars']['creditmanagement']['CustomerFirstName'] = $customVars['CustomerFirstName'];
            $this->data['customVars']['creditmanagement']['CustomerLastName'] = $customVars['CustomerLastName'];
            $this->data['customVars']['creditmanagement']['CustomerInitials'] = $customVars['CustomerInitials'];
            $this->data['customVars']['creditmanagement']['Customergender'] = $customVars['Customergender'];
            $this->data['customVars']['creditmanagement']['Customeremail'] = $customVars['Customeremail'];

            if (!empty($customVars['PaymentMethodsAllowed'])) {
                $this->data['customVars']['creditmanagement']['PaymentMethodsAllowed'] = $customVars['PaymentMethodsAllowed'];
            }

            if (Tools::getIsset($customVars['MobilePhoneNumber'])) {
                $this->data['customVars']['creditmanagement']['MobilePhoneNumber'] = $customVars['MobilePhoneNumber'];
                $this->data['customVars']['creditmanagement']['PhoneNumber'] = $customVars['MobilePhoneNumber'];
            }
            if (Tools::getIsset($customVars['PhoneNumber'])) {
                $this->data['customVars']['creditmanagement']['PhoneNumber'] = $customVars['PhoneNumber'];
            }
            if (Tools::getIsset($customVars['CustomerBirthDate'])) {
                $this->data['customVars']['creditmanagement']['CustomerBirthDate'] = $customVars['CustomerBirthDate'];
            }

            $this->data['customVars']['creditmanagement']['CustomerType'] = '0';
            $this->data['customVars']['creditmanagement']['AmountVat'] = $customVars['AmountVat'];

            foreach ($customVars['ADDRESS'] as $key => $adress) {
                foreach ($adress as $key2 => $value) {
                    $this->data['customVars']['creditmanagement'][$key2][$key]['value'] = $value;
                    $this->data['customVars']['creditmanagement'][$key2][$key]['group'] = 'address';
                }
            }
        }

        $soap = new Soap($this->data);

        return ResponseFactory::getResponse($soap->transactionRequest());
    }
}
