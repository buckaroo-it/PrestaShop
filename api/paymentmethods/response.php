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

require_once dirname(__FILE__) . '/../../library/logger.php';
require_once dirname(__FILE__) . '/../abstract.php';

abstract class Response extends BuckarooAbstract
{
    //false if not received response
    private $received = false;
    //true if validated and securety checked
    private $validated = false;
    //request is test?
    private $test = true;
    private $signature;
    private $isPost;
    //payment key
    public $payment;
    //paypal, ideal...
    public $payment_method;
    public $statuscode;
    public $statuscode_detail;
    public $status;
    public $statusmessage;
    public $message;
    public $invoice;
    public $invoicenumber;
    public $amount_credit;
    public $amount;
    public $currency;
    public $timestamp;
    public $ChannelError;
    public $brq_transaction_type;
    public $brq_relatedtransaction_partialpayment;
    public $brq_relatedtransaction_refund;
    //transaction key
    public $transactions;
    //if is errors, othervise = null
    public $parameterError = null;
    /*     * **************************************************** */
    protected $responseXML = '';
    protected $response    = '';

    public function __construct($data = null)
    {
        $logger = new Logger(Logger::INFO, 'response');
        $logger->logInfo("\n\n\n\n***************** Response ***********************");
        if ($this->isHttpRequest()) {
            $logger->logInfo("Type: HTTP");
            $logger->logInfo("POST", print_r($_POST, true));
        } else {
            $logger->logInfo("Type: SOAP");
            if (!is_null($data)) {
                if ($data[0] != false) {
                    $logger->logInfo("Data[0]: ", print_r($data[0], true));
                }
                if ($data[1] != false) {
                    $logger->logInfo("Data[1]: ", $data[1]->saveHTML());
                }
                if ($data[2] != false) {
                    $logger->logInfo("Data[2]: ", $data[2]->saveHTML());
                }
            }
        }

        $this->isPost   = $this->isHttpRequest();
        $this->received = false;

        if ($this->isPost) {
            //HTTP
            $this->parsePostResponse();
            $this->parsePostResponseChild();
            $this->received = true;
        } else {
            if (!is_null($data) && $data[0] != false) {
                //if valid SOAP response
                $this->setResponse($data[0]);
                $this->setResponseXML($data[1]);
                $this->parseSoapResponse();
                $this->parseSoapResponseChild();
                $this->received = true;
            } else {
                $this->status = self::REQUEST_ERROR;
            }
        }
    }

    //Determine if response is HTTP or SOAP
    private function isHttpRequest()
    {
        if (Tools::getValue('brq_statuscode')) {
            return true;
        }
        return false;
    }

    public function isTest()
    {
        return $this->test;
    }

    public function isValid()
    {
        if (!$this->validated) {
            if ($this->isPost) {
                $this->validated = $this->canProcessPush();
            } else {
                $this->validated = $this->verifyResponse();
            }
        }
        return $this->validated;
    }

    public function isReceived()
    {
        return $this->received;
    }

    public function hasSucceeded()
    {
        //if isValid false return false
        if ($this->isValid() && $this->isReceived()) {
            // if ($this->status === self::BUCKAROO_SUCCESS)
            if ($this->status === self::BUCKAROO_PENDING_PAYMENT || $this->status === self::BUCKAROO_SUCCESS) {
                return true;
            }
        }
        return false;
    }

    public function isRedirectRequired()
    {
        if (!empty($this->response->RequiredAction->Name)
            && isset($this->response->RequiredAction->Type)) {
            if ($this->response->RequiredAction->Name == 'Redirect'
                && $this->response->RequiredAction->Type == 'Redirect') {
                return true;
            }
        }
        return false;
    }

    public function getRedirectUrl()
    {
        if (!empty($this->response->RequiredAction->RedirectURL)) {
            return $this->response->RequiredAction->RedirectURL;
        } else {
            return false;
        }
    }

    private function setResponseXML($xml)
    {
        $this->responseXML = $xml;
    }

    private function getResponseXML()
    {
        return $this->responseXML;
    }

    private function setResponse($response)
    {
        $this->response = $response;
    }

    public function getResponse()
    {
        return $this->response;
    }

    private function parseSoapResponse()
    {
        $this->payment = '';
        if (!empty($this->response->ServiceCode)) {
            $this->payment_method = $this->response->ServiceCode;
        }
        $this->transactions = $this->response->Key;
        $this->statuscode   = $this->response->Status->Code->Code;
        if (!empty($this->response->Status->SubCode->_)) {
            $this->statusmessage = $this->response->Status->SubCode->_;
        }
        $this->statuscode_detail = '';
        if (!empty($this->response->Invoice)) {
            $this->invoice = $this->response->Invoice;
        }
        if (!empty($this->response->Order)) {
            $this->order         = $this->response->Order;
        }
        $this->invoicenumber = $this->invoice;
        $this->amount        = 0;
        if (!empty($this->response->AmountDebit)) {
            $this->amount = $this->response->AmountDebit;
        }
        $this->amount_credit = 0;
        if (!empty($this->response->AmountCredit)) {
            $this->amount        = $this->response->AmountCredit;
            $this->amount_credit = $this->response->AmountCredit;
        }
        if (!empty($this->response->Currency)) {
            $this->currency  = $this->response->Currency;
        }
        $this->test     = ($this->response->IsTest == 1) ? true : false;
        $this->timestamp = $this->response->Status->DateTime;
        if (!empty($this->response->RequestErrors->ChannelError->_)) {
            $this->ChannelError = $this->response->RequestErrors->ChannelError->_;
        }
        if (!empty($this->response->Status->Code->_) && empty($this->ChannelError)) {
            $this->ChannelError = $this->response->Status->Code->_;
            if (!empty($this->response->Status->SubCode->_)) {
                $this->ChannelError = $this->ChannelError . ': ' . $this->response->Status->SubCode->_;
            }
        }

        $responseArray = $this->responseCodes[(int) $this->statuscode];
        $this->status  = $responseArray['status'];
        $this->message = $responseArray['message'];

        if (!empty($this->response->RequestErrors->ParameterError)) {
            $this->ParameterError = $this->response->RequestErrors->ParameterError;
        }
    }

    abstract protected function parseSoapResponseChild();

    private function setPostVariable($key)
    {
        if (Tools::getValue($key)) {
            return Tools::getValue($key);
        } else {
            return null;
        }
    }

    private function parsePostResponse()
    {
        $this->payment = $this->setPostVariable('brq_payment');
        if (Tools::getValue('brq_payment_method')) {
            $this->payment_method = Tools::getValue('brq_payment_method');
        } elseif (Tools::getValue('brq_transaction_method')) {
            $this->payment_method = Tools::getValue('brq_transaction_method');
        }

        $this->statuscode                            = $this->setPostVariable('brq_statuscode');
        $this->statusmessage                         = $this->setPostVariable('brq_statusmessage');
        $this->statuscode_detail                     = $this->setPostVariable('brq_statuscode_detail');
        $this->brq_relatedtransaction_partialpayment = $this->setPostVariable('brq_relatedtransaction_partialpayment');
        $this->brq_transaction_type                  = $this->setPostVariable('brq_transaction_type');
        $this->brq_relatedtransaction_refund         = $this->setPostVariable('brq_relatedtransaction_refund');
        $this->invoice                               = $this->setPostVariable('brq_invoicenumber');
        $this->invoicenumber                         = $this->setPostVariable('brq_invoicenumber');
        $this->amount                                = $this->setPostVariable('brq_amount');
        if (Tools::getValue('brq_amount_credit')) {
            $this->amount_credit = Tools::getValue('brq_amount_credit');
        }

        $this->currency     = $this->setPostVariable('brq_currency');
        $this->test        = $this->setPostVariable('brq_test');
        $this->timestamp    = $this->setPostVariable('brq_timestamp');
        $this->transactions = $this->setPostVariable('brq_transactions');
        $this->signature   = $this->setPostVariable('brq_signature');

        if (!empty($this->statuscode)) {
            $responseArray = $this->responseCodes[(int) $this->statuscode];
            $this->status  = $responseArray['status'];
            $this->message = $responseArray['message'];
        }
    }

    abstract protected function parsePostResponseChild();

    protected function verifyResponse()
    {
        $verified = false;
        if ($this->isReceived()) {
            $verifiedSignature = $this->verifySignature();
            $verifiedDigest    = $this->verifyDigest();

            if ($verifiedSignature === true && $verifiedDigest === true) {
                $verified = true;
            }
        };
        return $verified;
    }

    protected function verifySignature()
    {
        $verified = false;

        //save response XML to string
        $responseDomDoc = $this->responseXML;
        $responseString = $responseDomDoc->saveXML();

        //retrieve the signature value
        $sigatureRegex  = "#<SignatureValue>(.*)</SignatureValue>#ims";
        $signatureArray = array();
        preg_match_all($sigatureRegex, $responseString, $signatureArray);

        //decode the signature
        $signature  = $signatureArray[1][0];
        $sigDecoded = mb_convert_encoding($signature, "UTF-8", "BASE64");

        $xPath = new DOMXPath($responseDomDoc);

        //register namespaces to use in xpath query's
        $xPath->registerNamespace(
            'wsse',
            'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd'
        );
        $xPath->registerNamespace('sig', 'http://www.w3.org/2000/09/xmldsig#');
        $xPath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');

        //Get the SignedInfo nodeset
        $SignedInfoQuery        = '//wsse:Security/sig:Signature/sig:SignedInfo';
        $SignedInfoQueryNodeSet = $xPath->query($SignedInfoQuery);
        $SignedInfoNodeSet      = $SignedInfoQueryNodeSet->item(0);

        //Canonicalize nodeset
        $signedInfo = $SignedInfoNodeSet->C14N(true, false);

        $certificatesDir = dirname(__FILE__) . '/../../' . Config::CERTIFICATE_PATH;

        $keyIdentifier = '//wsse:Security/sig:Signature/sig:KeyInfo/wsse:SecurityTokenReference/wsse:KeyIdentifier';
        $keyIdentifierList = $xPath->query($keyIdentifier);

        if ($keyIdentifierList && $keyIdentifierList->item(0) && $keyIdentifierList->item(0)->nodeValue) {
            $certificatePath = $certificatesDir . 'Buckaroo' . $keyIdentifierList->item(0)->nodeValue . '.pem';
            if (!file_exists($certificatePath)) {
                $certificatePath = $certificatesDir . 'Checkout.pem';
            }
        }
        //get the public key
        if (!file_exists($certificatePath)) {
            $logger = new Logger(1);
            $logger->logForUser($certificatePath . ' do not exists');
        }
        $pubKey = openssl_get_publickey(openssl_x509_read(Tools::file_get_contents($certificatePath)));

        //verify the signature
        $sigVerify = openssl_verify($signedInfo, $sigDecoded, $pubKey);

        if ($sigVerify === 1) {
            $verified = true;
        }

        // workaround
        if (!$verified) {
            $keyDetails = openssl_pkey_get_details($pubKey);
            if (!empty($keyDetails["key"])) {
                $sigVerify = openssl_verify($signedInfo, $sigDecoded, $keyDetails["key"]);
                if ($sigVerify === 1) {
                    $verified = true;
                }
            }
        }

        return $verified;
    }

    protected function verifyDigest()
    {
        $verified = false;

        //save response XML to string
        $responseDomDoc = $this->responseXML;
        $responseString = $responseDomDoc->saveXML();

        //retrieve the signature value
        $digestRegex = "#<DigestValue>(.*?)</DigestValue>#ims";
        $digestArray = array();
        preg_match_all($digestRegex, $responseString, $digestArray);

        $digestValues = array();
        foreach ($digestArray[1] as $digest) {
            $digestValues[] = $digest;
        }

        $xPath = new DOMXPath($responseDomDoc);

        //register namespaces to use in xpath query's
        $xPath->registerNamespace(
            'wsse',
            'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd'
        );
        $xPath->registerNamespace('sig', 'http://www.w3.org/2000/09/xmldsig#');
        $xPath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');

        $controlHashReference = $xPath->query('//*[@Id="_control"]')->item(0);
        $controlHashCanonical = $controlHashReference->C14N(true, false);
        $controlHash          = mb_convert_encoding(pack('H*', sha1($controlHashCanonical)), "BASE64", "UTF-8");

        $bodyHashReference = $xPath->query('//*[@Id="_body"]')->item(0);
        $bodyHashCanonical = $bodyHashReference->C14N(true, false);
        $bodyHash          = mb_convert_encoding(pack('H*', sha1($bodyHashCanonical)), "BASE64", "UTF-8");

        if (in_array($controlHash, $digestValues) === true && in_array($bodyHash, $digestValues) === true) {
            $verified = true;
        }

        return $verified;
    }

    /**
     * Checks if the post recieved is valid by checking its signature field.
     * This field is unique for every payment and every store.
     * Also calls method that checks if an order is able to be updated further.
     * Canceled, completed, holded etc. orders are not able to be updated
     */
    protected function canProcessPush()
    {
        $correctSignature = false;
        //   $canUpdate = false;
        $signature = $this->calculateSignature();
        if ($signature === Tools::getValue('brq_signature')) {
            $correctSignature = true;
        }
        /*
        //check if the order can recieve further status updates
        if ($correctSignature === true) {
        $canUpdate = $this->canUpdate();
        }

        $return = array(
        (bool) $correctSignature,
        (bool) $canUpdate,
        );
         *
         */
        return $correctSignature; //$return;
    }

    /**
     * Checks if the order can be updated by checking if its state and status is not
     * complete, closed, cancelled or holded and the order can be invoiced
     *
     * @return boolean $return
     */
    protected function canUpdate()
    {
        $return = false;

        // Get successful state and status
        $completedStateAndStatus = array('complete', 'complete');
        $cancelledStateAndStatus = array('canceled', 'canceled');
        $holdedStateAndStatus    = array('holded', 'holded');
        $closedStateAndStatus    = array('closed', 'closed');

        $currentStateAndStatus = array($this->_order->getState(), $this->_order->getStatus());

        //prevent completed orders from recieving further updates
        if ($completedStateAndStatus != $currentStateAndStatus
            && $cancelledStateAndStatus != $currentStateAndStatus
            && $holdedStateAndStatus != $currentStateAndStatus
            && $closedStateAndStatus != $currentStateAndStatus
        ) {
            $return = true;
        } else {
            $logger = new Logger(Logger::INFO, 'response');
            $logger->logWarn("\nOrder already has succes, complete, closed, or holded state \n\n");
        }

        return $return;
    }

    /**
     * Determines the signature using array sorting and the SHA1 hash algorithm
     *
     * @return string $signature
     */
    protected function calculateSignature()
    {
        $origArray = $_POST;
        unset($origArray['brq_signature']);

        //sort the array
        $sortableArray = $this->buckarooSort($origArray);

        //turn into string and add the secret key to the end
        $signatureString = '';
        foreach ($sortableArray as $key => $value) {
            $value = $this->decodePushValue($key, $value);
            $signatureString .= $key . '=' . $value;
        }
        $signatureString .= Config::get('BUCKAROO_SECRET_KEY');
        //return the SHA1 encoded string for comparison
        $signature = SHA1($signatureString);

        return $signature;
    }

    public function getCartIdAndReferenceId($show = false)
    {
        $e = explode("_", urldecode($this->invoicenumber));
        if (!empty($e[1])) {
            list($reference, $cartId) = $e;
        } else {
            $cartId    = 0;
            $reference = $this->invoicenumber;
        }
        if ($show == 'cartId') {
            return (int) $cartId;
        }
        return $reference;
    }

    public function getCartId()
    {
        return $this->getCartIdAndReferenceId('cartId');
    }

    public function getReferenceId()
    {
        return $this->getCartIdAndReferenceId('reference');
    }

    /**
     * @param string $brq_key
     * @param string $brq_value
     *
     * @return string
     */
    private function decodePushValue($brq_key, $brq_value)
    {
        switch (strtolower($brq_key)) {
            case 'brq_customer_name':
            case 'brq_service_ideal_consumername':
            case 'brq_service_transfer_consumername':
            case 'brq_service_payconiq_payconiqandroidurl':
            case 'brq_service_paypal_payeremail':
            case 'brq_service_paypal_payerfirstname':
            case 'brq_service_paypal_payerlastname':
            case 'brq_service_payconiq_payconiqiosurl':
            case 'brq_service_payconiq_payconiqurl':
            case 'brq_service_payconiq_qrurl':
            case 'brq_service_masterpass_customerphonenumber':
            case 'brq_service_masterpass_shippingrecipientphonenumber':
            case 'brq_invoicedate':
            case 'brq_duedate':
            case 'brq_previousstepdatetime':
            case 'brq_eventdatetime':
            case 'brq_service_transfer_accountholdername':
                $decodedValue = $brq_value;
                break;
            default:
                $decodedValue = urldecode($brq_value);
        }

        return $decodedValue;
    }
}
