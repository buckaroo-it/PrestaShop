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

if (!class_exists('SoapClient')) {
    $logger = new Logger(1);
    $logger->logForUser(
        'SoapClient is not installed. Please ask your hosting provider to install SoapClient <a style="text-decoration: underline; color: #0000FF" href="http://<?php.net/manual/en/soap.installation.php">http://<?php.net/manual/en/soap.installation.php</a>'//phpcs:ignore
    );
}

require_once dirname(__FILE__) . '/SoapClientWSSEC.php';
require_once dirname(__FILE__) . '/apiclasses/Body.php';
require_once dirname(__FILE__) . '/apiclasses/CanonicalizationMethodType.php';
require_once dirname(__FILE__) . '/apiclasses/DigestMethodType.php';
require_once dirname(__FILE__) . '/apiclasses/Header.php';
require_once dirname(__FILE__) . '/apiclasses/IPAddress.php';
require_once dirname(__FILE__) . '/apiclasses/MessageControlBlock.php';
require_once dirname(__FILE__) . '/apiclasses/ReferenceType.php';
require_once dirname(__FILE__) . '/apiclasses/RequestParameter.php';
require_once dirname(__FILE__) . '/apiclasses/SecurityType.php';
require_once dirname(__FILE__) . '/apiclasses/Service.php';
require_once dirname(__FILE__) . '/apiclasses/Services.php';
require_once dirname(__FILE__) . '/apiclasses/SignatureMethodType.php';
require_once dirname(__FILE__) . '/apiclasses/SignatureType.php';
require_once dirname(__FILE__) . '/apiclasses/SignedInfoType.php';
require_once dirname(__FILE__) . '/apiclasses/TransformType.php';

final class Soap extends BuckarooAbstract
{
    // @codingStandardsIgnoreStart
    private $_vars;
    // @codingStandardsIgnoreEnd

    public function setVars($vars = array())
    {
        $this->_vars = $vars;
    }

    public function __construct($data)
    {
        $this->setVars($data);
    }

    public function transactionRequest()
    {
        try {
            //first attempt: use the cached WSDL
            $client = new SoapClientWSSEC(
                Config::WSDL_URL,
                array(
                    'trace'      => 1,
                    'cache_wsdl' => WSDL_CACHE_DISK,
                )
            );
        } catch (Exception $e) {
            //(SoapFault $e) {
            try {
                //second attempt: use an uncached WSDL
                ini_set('soap.wsdl_cache_ttl', 1);
                $client = new SoapClientWSSEC(
                    Config::WSDL_URL,
                    array(
                        'trace'      => 1,
                        'cache_wsdl' => WSDL_CACHE_NONE,
                    )
                );
            } catch (Exception $e) {
                //(SoapFault $e) {
                try {
                    //third and final attempt: use the supplied wsdl found in the lib folder
                    $client = new SoapClientWSSEC(
                        dirname(__FILE__) . Config::WSDL_FILE,
                        array(
                            'trace'      => 1,
                            'cache_wsdl' => WSDL_CACHE_NONE,
                        )
                    );
                } catch (Exception $e) {
                    //(SoapFault $e) {
                    return $this->error($e);
                }
            }
        }

        $client->thumbprint = Config::get('BUCKAROO_CERTIFICATE_THUMBPRINT');
        $client->privateKey = Config::get('BUCKAROO_CERTIFICATE_PATH');

        $search                       = array(",", " ");
        $replace                      = array(".", "");
        $TransactionRequest           = new Body();
        $TransactionRequest->Currency = $this->_vars['currency'];

        $debit                            = round($this->_vars['amountDebit'], 2);
        $credit                           = round($this->_vars['amountCredit'], 2);
        $TransactionRequest->AmountDebit  = str_replace($search, $replace, $debit);
        $TransactionRequest->AmountCredit = str_replace($search, $replace, $credit);
        $TransactionRequest->Invoice      = $this->_vars['invoice'];
        $TransactionRequest->Order        = $this->_vars['order'];
        $TransactionRequest->Description  = $this->_vars['description'];
        $TransactionRequest->ReturnURL    = $this->_vars['returnUrl'];
        if (!empty($this->_vars['OriginalTransactionKey'])) {
            $TransactionRequest->OriginalTransactionKey = $this->_vars['OriginalTransactionKey'];
        }
        $TransactionRequest->StartRecurrent = false;

        if (!empty($this->_vars['customVars']['servicesSelectableByClient']) && !empty(
            $this->_vars['customVars']['continueOnIncomplete']
        )
        ) {
            $TransactionRequest->ServicesSelectableByClient = $this->_vars['customVars']['servicesSelectableByClient'];
            $TransactionRequest->ContinueOnIncomplete       = $this->_vars['customVars']['continueOnIncomplete'];
            $TransactionRequest->ServicesExcludedForClient  = null;
        }
        /*
        if (array_key_exists('OriginalTransactionKey', $this->_vars)) {
        $TransactionRequest->OriginalTransactionKey = $this->_vars['OriginalTransactionKey'];
        }
         */
        if (!empty($this->_vars['customParameters'])) {
            $TransactionRequest = $this->addCustomParameters($TransactionRequest);
        }

        $TransactionRequest->Services = new Services();
        $this->addServices($TransactionRequest);

        /*
        $TransactionRequest->Services = new Services();

        $TransactionRequest->Services->Service = new Service();
        $TransactionRequest->Services->Service->Name= $this->_vars['service']['type'];
        $TransactionRequest->Services->Service->Action = $this->_vars['service']['action'];;
        $TransactionRequest->Services->Service->Version = $this->_vars['service']['version'];;
         */
        $TransactionRequest->ClientIP       = new IPAddress();
        $TransactionRequest->ClientIP->Type = 'IPv4';
        $TransactionRequest->ClientIP->_    = $_SERVER['REMOTE_ADDR'];

        foreach ($TransactionRequest->Services->Service as $key => $service) {
            if(property_exists($service, 'Action') && $service->Action === "extraInfo") {
                continue;
            }
            $this->addCustomFields($TransactionRequest, $key, $service->Name);
        }

        $Header                                  = new Header();
        $Header->MessageControlBlock             = new MessageControlBlock();
        $Header->MessageControlBlock->Id         = '_control';
        $Header->MessageControlBlock->WebsiteKey = Config::get('BUCKAROO_MERCHANT_KEY');
        $Header->MessageControlBlock->Culture    = Config::get('CULTURE');
        $Header->MessageControlBlock->TimeStamp  = time();
        $Header->MessageControlBlock->Channel    = Config::CHANNEL;
        $Header->MessageControlBlock->Software   = Config::getSoftware();
        $Header->Security                        = new SecurityType();
        $Header->Security->Signature             = new SignatureType();

        $Header->Security->Signature->SignedInfo                                    = new SignedInfoType();
        $Header->Security->Signature->SignedInfo->CanonicalizationMethod            = new CanonicalizationMethodType();
        $Header->Security->Signature->SignedInfo->CanonicalizationMethod->Algorithm = 'http://www.w3.org/2001/10/xml-exc-c14n#';//phpcs:ignore
        $Header->Security->Signature->SignedInfo->SignatureMethod                   = new SignatureMethodType();
        $Header->Security->Signature->SignedInfo->SignatureMethod->Algorithm        = 'http://www.w3.org/2000/09/xmldsig#rsa-sha1';//phpcs:ignore

        $Reference             = new ReferenceType();
        $Reference->URI        = '#_body';
        $Transform             = new TransformType();
        $Transform->Algorithm  = 'http://www.w3.org/2001/10/xml-exc-c14n#';
        $Reference->Transforms = array($Transform);

        $Reference->DigestMethod            = new DigestMethodType();
        $Reference->DigestMethod->Algorithm = 'http://www.w3.org/2000/09/xmldsig#sha1';
        $Reference->DigestValue             = '';

        $Transform2                                = new TransformType();
        $Transform2->Algorithm                     = 'http://www.w3.org/2001/10/xml-exc-c14n#';
        $ReferenceControl                          = new ReferenceType();
        $ReferenceControl->URI                     = '#_control';
        $ReferenceControl->DigestMethod            = new DigestMethodType();
        $ReferenceControl->DigestMethod->Algorithm = 'http://www.w3.org/2000/09/xmldsig#sha1';
        $ReferenceControl->DigestValue             = '';
        $ReferenceControl->Transforms              = array($Transform2);

        $Header->Security->Signature->SignedInfo->Reference = array($Reference, $ReferenceControl);
        $Header->Security->Signature->SignatureValue        = '';

        $soapHeaders   = array();
        $soapHeaders[] = new SOAPHeader('https://checkout.buckaroo.nl/PaymentEngine/', 'MessageControlBlock', $Header->MessageControlBlock);//phpcs:ignore
        $soapHeaders[] = new SOAPHeader('http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd', 'Security', $Header->Security);//phpcs:ignore
        $client->__setSoapHeaders($soapHeaders);

        if ($this->_vars['mode'] == 'test') {
            //$location = 'http://localhost:8080/';
            $location = Config::LOCATION_TEST;
        } else {
            $location = Config::LOCATION;
        }

        $client->__SetLocation($location);

        try {
            $response = $client->TransactionRequest($TransactionRequest);
        } catch (SoapFault $e) {
            $logger = new Logger(1);
            $logger->logForUser($e->getMessage());
            //$this->logException($e->getMessage());
            return $this->error($client);
        } catch (Exception $e) {
            $logger = new Logger(1);
            $logger->logForUser($e->getMessage());
            //$this->logException($e->getMessage());
            return $this->error($client);
        }

        if (is_null($response)) {
            $response = false;
        }

        $responseXML = $client->__getLastResponse();
        $requestXML  = $client->__getLastRequest();

        $responseDomDOC = new DOMDocument();
        $responseDomDOC->loadXML($responseXML);
        $responseDomDOC->preserveWhiteSpace = false;
        $responseDomDOC->formatOutput       = true;

        $requestDomDOC = new DOMDocument();
        $requestDomDOC->loadXML($requestXML);
        $requestDomDOC->preserveWhiteSpace = false;
        $requestDomDOC->formatOutput       = true;

        return array($response, $responseDomDOC, $requestDomDOC);
    }

    public function dataRequest()
    {
        try {
            //first attempt: use the cached WSDL
            $client = new SoapClientWSSEC(
                Config::WSDL_URL,
                array(
                    'trace'      => 1,
                    'cache_wsdl' => WSDL_CACHE_DISK,
                )
            );
        } catch (Exception $e) {
            //(SoapFault $e) {
            try {
                //second attempt: use an uncached WSDL
                ini_set('soap.wsdl_cache_ttl', 1);
                $client = new SoapClientWSSEC(
                    Config::WSDL_URL,
                    array(
                        'trace'      => 1,
                        'cache_wsdl' => WSDL_CACHE_NONE,
                    )
                );
            } catch (Exception $e) {
                //(SoapFault $e) {
                try {
                    //third and final attempt: use the supplied wsdl found in the lib folder
                    $client = new SoapClientWSSEC(
                        dirname(__FILE__) . Config::WSDL_FILE,
                        array(
                            'trace'      => 1,
                            'cache_wsdl' => WSDL_CACHE_NONE,
                        )
                    );
                } catch (Exception $e) {
                    //(SoapFault $e) {
                    return $this->error($e);
                }
            }
        }

        $client->thumbprint = Config::get('BUCKAROO_CERTIFICATE_THUMBPRINT');
        $client->privateKey = Config::get('BUCKAROO_CERTIFICATE_PATH');

        $DataRequest                  = new Body();
        $DataRequest->ReturnURL    = $this->_vars['returnUrl'];
        $DataRequest->StartRecurrent = false;

        if (!empty($this->_vars['customVars']['servicesSelectableByClient']) && !empty(
            $this->_vars['customVars']['continueOnIncomplete']
        )
        ) {
            $DataRequest->ServicesSelectableByClient = $this->_vars['customVars']['servicesSelectableByClient'];
            $DataRequest->ContinueOnIncomplete       = $this->_vars['customVars']['continueOnIncomplete'];
            $DataRequest->ServicesExcludedForClient  = null;
        }

        if (!empty($this->_vars['customParameters'])) {
            $DataRequest = $this->addCustomParameters($DataRequest);
        }

        $DataRequest->Services = new Services();
        $this->addServices($DataRequest);

        $DataRequest->ClientIP       = new IPAddress();
        $DataRequest->ClientIP->Type = 'IPv4';
        $DataRequest->ClientIP->_    = $_SERVER['REMOTE_ADDR'];

        foreach ($DataRequest->Services->Service as $key => $service) {
            $this->addCustomFields($DataRequest, $key, $service->Name);
        }

        $Header                                  = new Header();
        $Header->MessageControlBlock             = new MessageControlBlock();
        $Header->MessageControlBlock->Id         = '_control';
        $Header->MessageControlBlock->WebsiteKey = Config::get('BUCKAROO_MERCHANT_KEY');
        $Header->MessageControlBlock->Culture    = Config::get('CULTURE');
        $Header->MessageControlBlock->TimeStamp  = time();
        $Header->MessageControlBlock->Channel    = Config::CHANNEL;
        $Header->MessageControlBlock->Software   = Config::getSoftware();
        $Header->Security                        = new SecurityType();
        $Header->Security->Signature             = new SignatureType();

        $Header->Security->Signature->SignedInfo                                    = new SignedInfoType();
        $Header->Security->Signature->SignedInfo->CanonicalizationMethod            = new CanonicalizationMethodType();
        $Header->Security->Signature->SignedInfo->CanonicalizationMethod->Algorithm = 'http://www.w3.org/2001/10/xml-exc-c14n#';//phpcs:ignore
        $Header->Security->Signature->SignedInfo->SignatureMethod                   = new SignatureMethodType();
        $Header->Security->Signature->SignedInfo->SignatureMethod->Algorithm        = 'http://www.w3.org/2000/09/xmldsig#rsa-sha1';//phpcs:ignore

        $Reference             = new ReferenceType();
        $Reference->URI        = '#_body';
        $Transform             = new TransformType();
        $Transform->Algorithm  = 'http://www.w3.org/2001/10/xml-exc-c14n#';
        $Reference->Transforms = array($Transform);

        $Reference->DigestMethod            = new DigestMethodType();
        $Reference->DigestMethod->Algorithm = 'http://www.w3.org/2000/09/xmldsig#sha1';
        $Reference->DigestValue             = '';

        $Transform2                                = new TransformType();
        $Transform2->Algorithm                     = 'http://www.w3.org/2001/10/xml-exc-c14n#';
        $ReferenceControl                          = new ReferenceType();
        $ReferenceControl->URI                     = '#_control';
        $ReferenceControl->DigestMethod            = new DigestMethodType();
        $ReferenceControl->DigestMethod->Algorithm = 'http://www.w3.org/2000/09/xmldsig#sha1';
        $ReferenceControl->DigestValue             = '';
        $ReferenceControl->Transforms              = array($Transform2);

        $Header->Security->Signature->SignedInfo->Reference = array($Reference, $ReferenceControl);
        $Header->Security->Signature->SignatureValue        = '';

        $soapHeaders   = array();
        $soapHeaders[] = new SOAPHeader('https://checkout.buckaroo.nl/PaymentEngine/', 'MessageControlBlock', $Header->MessageControlBlock);//phpcs:ignore
        $soapHeaders[] = new SOAPHeader('http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd', 'Security', $Header->Security);//phpcs:ignore
        $client->__setSoapHeaders($soapHeaders);

        if ($this->_vars['mode'] == 'test') {
            $location = Config::LOCATION_TEST;
        } else {
            $location = Config::LOCATION;
        }

        $client->__SetLocation($location);

        try {
            $response = $client->DataRequest($DataRequest);
        } catch (SoapFault $e) {
            $logger = new Logger(1);
            $logger->logForUser($e->getMessage());
            return $this->error($client);
        } catch (Exception $e) {
            $logger = new Logger(1);
            $logger->logForUser($e->getMessage());
            return $this->error($client);
        }

        if (is_null($response)) {
            $response = false;
        }

        $responseXML = $client->__getLastResponse();
        $requestXML  = $client->__getLastRequest();

        $responseDomDOC = new DOMDocument();
        $responseDomDOC->loadXML($responseXML);
        $responseDomDOC->preserveWhiteSpace = false;
        $responseDomDOC->formatOutput       = true;

        $requestDomDOC = new DOMDocument();
        $requestDomDOC->loadXML($requestXML);
        $requestDomDOC->preserveWhiteSpace = false;
        $requestDomDOC->formatOutput       = true;

        return array($response, $responseDomDOC, $requestDomDOC);
    }

    protected function addServices(&$TransactionRequest)
    {
        $services = array();
        foreach ($this->_vars['services'] as $fieldName => $value) {
            if (empty($value)) {
                continue;
            }

            if(isset($value['action2']) && !empty($value['action2'])) {
                $service          = new Service();
                $service->Name    = $fieldName;
                $service->Action  = $value['action2'];
                $service->Version = $value['version2'];
            
                $services[] = $service;
            }

            $service          = new Service();
            $service->Name    = $fieldName;
            $service->Action  = $value['action'];
            $service->Version = $value['version'];

            $services[] = $service;
        }

        $TransactionRequest->Services->Service = $services;
    }

    protected function addCustomFields(&$TransactionRequest, $key, $name)
    {
        if (empty($this->_vars['customVars']) || empty($this->_vars['customVars'][$name])) {
            unset($TransactionRequest->Services->Service->RequestParameter);
            return;
        }

        $requestParameters = array();
        foreach ($this->_vars['customVars'][$name] as $fieldName => $value) {
            if ((is_null($value) || $value === '')
                || (is_array($value) && (!empty($value['value'])
                    && (is_null($value['value'])
                        || $value['value'] === '')))) {
                continue;
            }

            if (is_array($value)) {
                if (isset($value[0]) && is_array($value[0])) {
                    foreach ($value as $k => $val) {
                        $requestParameter          = new RequestParameter();
                        $requestParameter->Name    = $fieldName;
                        $requestParameter->Group   = $val['group'];
                        $requestParameter->GroupID = $k + 1;
                        $requestParameter->_       = $val['value'];
                        $requestParameters[]       = $requestParameter;
                    }
                } else {
                    $requestParameter        = new RequestParameter();
                    $requestParameter->Name  = $fieldName;
                    $requestParameter->Group = $value['group'];
                    $requestParameter->_     = $value['value'];
                    $requestParameters[]     = $requestParameter;
                };
            } else {
                $requestParameter       = new RequestParameter();
                $requestParameter->Name = $fieldName;
                $requestParameter->_    = $value;
                $requestParameters[]    = $requestParameter;
            }
        }

        if (empty($requestParameters)) {
            unset($TransactionRequest->Services->Service->RequestParameter);
            return;
        } else {
            $TransactionRequest->Services->Service[$key]->RequestParameter = $requestParameters;
        }
    }

    protected function addCustomParameters(&$TransactionRequest)
    {
        $requestParameters = array();
        foreach ($this->_vars['customParameters'] as $fieldName => $value) {
            if ((is_null($value) || $value === '')
                || (is_array($value) && (is_null($value['value'])
                    || $value['value'] === ''))) {
                continue;
            }

            $requestParameter       = new RequestParameter();
            $requestParameter->Name = $fieldName;
            if (is_array($value)) {
                $requestParameter->Group = $value['group'];
                $requestParameter->_     = $value['value'];
            } else {
                $requestParameter->_ = $value;
            }

            $requestParameters[] = $requestParameter;
        }

        if (empty($requestParameters)) {
            unset($TransactionRequest->AdditionalParameters);
            return false;
        } else {
            $TransactionRequest->AdditionalParameters = $requestParameters;
        }

        return $TransactionRequest;
    }

    protected function error($client = false)
    {
        $response = false;

        $responseDomDOC = new DOMDocument();
        $requestDomDOC  = new DOMDocument();
        if ($client) {
            $responseXML = $client->__getLastResponse();
            $requestXML  = $client->__getLastRequest();

            if (!empty($responseXML)) {
                $responseDomDOC->loadXML($responseXML);
                $responseDomDOC->preserveWhiteSpace = false;
                $responseDomDOC->formatOutput       = true;
            }

            if (!empty($requestXML)) {
                $requestDomDOC->loadXML($requestXML);
                $requestDomDOC->preserveWhiteSpace = false;
                $requestDomDOC->formatOutput       = true;
            }
        }

        return array($response, $responseDomDOC, $requestDomDOC);
    }
}
