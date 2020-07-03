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

class SoapClientWSSEC extends SoapClient
{
    /**
     * Contains the request XML
     * @var DOMDocument
     */
    private $document;

    /**
     * Path to the privateKey file
     * @var string
     */
    public $privateKey = '';

    /**
     * Password for the privatekey
     * @var string
     */
    public $privateKeyPassword = '';

    /**
     * Thumbprint from Payment Plaza
     * @var type
     */
    public $thumbprint = '';

    public function __doRequest($request, $location, $action, $version, $one_way = 0)
    {
        // Add code to inspect/dissect/debug/adjust the XML given in $request here
        $domDOC = new DOMDocument();
        $domDOC->preserveWhiteSpace = false;
        $domDOC->formatOutput = true;
        $domDOC->loadXML($request);

        //Sign the document
        $domDOC = $this->SignDomDocument($domDOC);

        // Uncomment the following line, if you actually want to do the request
        return parent::__doRequest($domDOC->saveXML($domDOC->documentElement), $location, $action, $version, $one_way);
    }

    //Get nodeset based on xpath and ID
    private function getReference($ID, $xPath)
    {
        $query = '//*[@Id="'.$ID.'"]';
        $nodeset = $xPath->query($query);
        return $nodeset->item(0);
    }

    //Canonicalize nodeset
    private function getCanonical($Object)
    {
        return $Object->C14N(true, false);
    }

    //Calculate digest value (sha1 hash)
    private function calculateDigestValue($input)
    {
        return mb_convert_encoding(pack('H*', sha1($input)), "BASE64", "UTF-8");
    }

    private function signDomDocument($domDocument)
    {
        //create xPath
        $xPath = new DOMXPath($domDocument);

        //register namespaces to use in xpath query's
        $xPath->registerNamespace('wsse', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd');//phpcs:ignore
        $xPath->registerNamespace('sig', 'http://www.w3.org/2000/09/xmldsig#');
        $xPath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');

        //Set id on soap body to easily extract the body later.
        $bodyNodeList = $xPath->query('/soap:Envelope/soap:Body');
        $bodyNode = $bodyNodeList->item(0);
        $bodyNode->setAttribute('Id', '_body');

        //Get the digest values
        $controlHash = $this->CalculateDigestValue($this->GetCanonical($this->GetReference('_control', $xPath)));
        $bodyHash = $this->CalculateDigestValue($this->GetCanonical($this->GetReference('_body', $xPath)));

        //Set the digest value for the control reference
        $Control = '#_control';
        $controlHashQuery = '//*[@URI="'.$Control.'"]/sig:DigestValue';
        $controlHashQueryNodeset = $xPath->query($controlHashQuery);
        $controlHashNode = $controlHashQueryNodeset->item(0);
        $controlHashNode->nodeValue = $controlHash;

        //Set the digest value for the body reference
        $Body = '#_body';
        $bodyHashQuery = '//*[@URI="'.$Body.'"]/sig:DigestValue';
        $bodyHashQueryNodeset = $xPath->query($bodyHashQuery);
        $bodyHashNode = $bodyHashQueryNodeset->item(0);
        $bodyHashNode->nodeValue = $bodyHash;

        //Get the SignedInfo nodeset
        $SignedInfoQuery = '//wsse:Security/sig:Signature/sig:SignedInfo';
        $SignedInfoQueryNodeSet = $xPath->query($SignedInfoQuery);
        $SignedInfoNodeSet = $SignedInfoQueryNodeSet->item(0);

        //Canonicalize nodeset
        $signedINFO = $this->GetCanonical($SignedInfoNodeSet);

        if (!file_exists($this->privateKey)) {
            $logger = new Logger(1);
            $logger->logForUser($this->privateKey.' do not exists');
        }
        $fp = fopen($this->privateKey, "r");
        $priv_key = fread($fp, 8192);
        fclose($fp);

        if ($priv_key === false) {
            throw new Exception('Unable to read certificate.');
        }

        $pkeyid = openssl_get_privatekey($priv_key, '');
        if ($pkeyid === false) {
            throw new Exception('Unable to retrieve private key from certificate.');
        }

        //Sign signedinfo with privatekey
        $signature2 = null;
        openssl_sign($signedINFO, $signature2, $pkeyid);

        //Add signature value to xml document
        $sigValQuery = '//wsse:Security/sig:Signature/sig:SignatureValue';
        $sigValQueryNodeset = $xPath->query($sigValQuery);
        $sigValNodeSet = $sigValQueryNodeset->item(0);
        $sigValNodeSet->nodeValue = mb_convert_encoding($signature2, "BASE64", "UTF-8");

        //Get signature node
        $sigQuery = '//wsse:Security/sig:Signature';
        $sigQueryNodeset = $xPath->query($sigQuery);
        $sigNodeSet = $sigQueryNodeset->item(0);

        //Create keyinfo element and Add public key to KeyIdentifier element
        $KeyTypeNode = $domDocument->createElementNS("http://www.w3.org/2000/09/xmldsig#", "KeyInfo");
        $SecurityTokenReference = $domDocument->createElementNS('http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd', 'SecurityTokenReference');//phpcs:ignore
        $KeyIdentifier = $domDocument->createElement("KeyIdentifier");
        $KeyIdentifier->nodeValue = $this->thumbprint;
        $KeyIdentifier->setAttribute('ValueType', 'http://docs.oasis-open.org/wss/oasis-wss-soap-message-security-1.1#ThumbPrintSHA1');//phpcs:ignore
        $SecurityTokenReference->appendChild($KeyIdentifier);
        $KeyTypeNode->appendChild($SecurityTokenReference);
        $sigNodeSet->appendChild($KeyTypeNode);

        return $domDocument;
    }
}
