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

class PayGarant extends PaymentMethod
{
    public function __construct()
    {
        $this->type = "paymentguarantee";
        $this->version = '1';
        $this->mode = Config::getMode('PAYGARANT');
    }

    public function pay()
    {
        return null;
    }

    public function paymentInvitation($customVars)
    {
        $this->data['services'][$this->type]['action'] = 'Paymentinvitation';
        $this->data['services'][$this->type]['version'] = $this->version;

        if ($this->usenotification && !empty($customVars['Customeremail'])){
            $this->data['services']['notification']['action'] = 'ExtraInfo';
            $this->data['services']['notification']['version'] = '1';
            $this->data['customVars']['notification']['NotificationType'] = $customVars['Notificationtype'];
            $this->data['customVars']['notification']['CommunicationMethod'] = 'email';
            $this->data['customVars']['notification']['RecipientEmail'] = $customVars['Customeremail'];
            $this->data['customVars']['notification']['RecipientFirstName'] = $customVars['CustomerFirstName'];
            $this->data['customVars']['notification']['RecipientLastName'] = $customVars['CustomerLastName'];
            $this->data['customVars']['notification']['RecipientGender'] = $customVars['Customergender'];
            if (!empty($customVars['Notificationdelay'])) {
                $this->data['customVars']['notification']['SendDatetime'] = $customVars['Notificationdelay'];
            }
        }

        $this->data['currency'] = $this->currency;
        $this->data['amountDebit'] = $this->amountDedit;
        $this->data['amountCredit'] = $this->amountCredit;
        $this->data['invoice'] = $this->invoiceId;
        $this->data['order'] = $this->orderId;
        $this->data['description'] = $this->description;
        $this->data['returnUrl'] = $this->returnUrl;
        $this->data['mode'] = $this->mode;

        if (!empty($customVars['CustomerCode'])) {
            $this->data['customVars'][$this->type]['CustomerCode'] = $customVars['CustomerCode'];
        }
        $this->data['customVars'][$this->type]['CustomerFirstName'] = $customVars['CustomerFirstName'];
        $this->data['customVars'][$this->type]['CustomerLastName'] = $customVars['CustomerLastName'];
        $this->data['customVars'][$this->type]['CustomerInitials'] = $customVars['CustomerInitials'];
        if (substr($customVars['CustomerBirthDate'], 0, 1) != '-') {
            $this->data['customVars'][$this->type]['CustomerBirthDate'] = $customVars['CustomerBirthDate'];
        } else {
            $this->data['customVars'][$this->type]['CustomerBirthDate'] = '1990-01-01';
        }
        if (!empty($customVars['CustomerGender'])) {
            $this->data['customVars'][$this->type]['CustomerGender'] = $customVars['CustomerGender'];
        }
        $this->data['customVars'][$this->type]['CustomerEmail'] = $customVars['CustomerEmail'];

        foreach ($customVars['ADDRESS'] as $key => $adress) {
            foreach ($adress as $key2 => $value) {
                $this->data['customVars'][$this->type][$key2][$key]['value'] = $value;
                $this->data['customVars'][$this->type][$key2][$key]['group'] = 'address';
            }
        }
        if (!empty($customVars['PhoneNumber'])) {
            $this->data['customVars'][$this->type]['PhoneNumber'] = $customVars['PhoneNumber'];
        }

        if (!empty($customVars['MobilePhoneNumber'])) {
            $this->data['customVars'][$this->type]['MobilePhoneNumber'] = $customVars['MobilePhoneNumber'];
        }

        $this->data['customVars'][$this->type]['DateDue'] = $customVars['DateDue'];
        $this->data['customVars'][$this->type]['InvoiceDate'] = $customVars['InvoiceDate'];
        $this->data['customVars'][$this->type]['AmountVat'] = $customVars['AmountVat'];

        if (!empty($customVars['PaymentMethodsAllowed'])) {
            $this->data['customVars'][$this->type]['PaymentMethodsAllowed'] = $customVars['PaymentMethodsAllowed'];
        }
        if (PaymentMethod::isIBAN($customVars['CustomerAccountNumber'])) {
            $this->data['customVars'][$this->type]['CustomerIBAN'] = $customVars['CustomerAccountNumber'];
        } else {
            $this->data['customVars'][$this->type]['CustomerAccountNumber'] = $customVars['CustomerAccountNumber'];
        }
        $this->data['customVars'][$this->type]['SendMail'] = $customVars['SendMail'];

        $soap = new Soap($this->data);

        return ResponseFactory::getResponse($soap->transactionRequest());

    }

    public function creditNote()
    {

    }
}
