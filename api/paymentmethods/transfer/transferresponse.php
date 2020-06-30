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

require_once(dirname(__FILE__) . '/../response.php');

class TransferResponse extends Response
{
    public $BIC = '';
    public $IBAN = '';
    public $accountHolderName = '';
    public $accountHolderCountry = '';
    public $paymentReference = '';
    public $consumerMessage = array(
        'MustRead' => '',
        'CultureName' => '',
        'Title' => '',
        'PlainText' => '',
        'HtmlText' => ''
    );

    protected function _parseSoapResponseChild()
    {
        if (!empty(
            $this->_response->Services->Service->ResponseParameter
        ) && !empty($this->_response->Services->Service->Name)
        ) {
            if ($this->_response->Services->Service->Name == 'transfer' && $this->_response->Services->Service->ResponseParameter[5]->Name == 'PaymentReference') {
                $this->BIC = $this->_response->Services->Service->ResponseParameter[0]->_;
                $this->IBAN = $this->_response->Services->Service->ResponseParameter[1]->_;
                $this->accountHolderName = $this->_response->Services->Service->ResponseParameter[2]->_;
                $this->accountHolderCity = $this->_response->Services->Service->ResponseParameter[3]->_;
                $this->accountHolderCountry = $this->_response->Services->Service->ResponseParameter[4]->_;
                $this->paymentReference = $this->_response->Services->Service->ResponseParameter[5]->_;
            }
        }
        if (!empty($this->_response->ConsumerMessage)) {
            if (!empty($this->_response->ConsumerMessage->MustRead)) {
                $this->consumerMessage['MustRead'] = $this->_response->ConsumerMessage->MustRead;
            }
            if (!empty($this->_response->ConsumerMessage->CultureName)) {
                $this->consumerMessage['CultureName'] = $this->_response->ConsumerMessage->CultureName;
            }
            if (!empty($this->_response->ConsumerMessage->Title)) {
                $this->consumerMessage['Title'] = $this->_response->ConsumerMessage->Title;
            }
            if (!empty($this->_response->ConsumerMessage->PlainText)) {
                $this->consumerMessage['PlainText'] = $this->_response->ConsumerMessage->PlainText;
            }
            if (!empty($this->_response->ConsumerMessage->HtmlText)) {
                $this->consumerMessage['HtmlText'] = $this->_response->ConsumerMessage->HtmlText;
            }
        }
    }

    protected function _parsePostResponseChild()
    {

    }
}
