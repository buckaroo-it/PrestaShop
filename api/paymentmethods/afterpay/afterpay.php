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

class AfterPay extends PaymentMethod
{
    public $BillingGender;
    public $BillingInitials;
    public $BillingFirstName;
    public $BillingLastName;
    public $BillingBirthDate;
    public $BillingStreet;
    public $BillingHouseNumber;
    public $BillingHouseNumberSuffix;
    public $BillingPostalCode;
    public $BillingCity;
    public $BillingCountry;
    public $BillingEmail;
    public $BillingPhoneNumber;
    public $BillingLanguage;
    public $IdentificationNumber;
    public $AddressesDiffer;
    public $ShippingGender;
    public $ShippingInitials;
    public $ShippingFirstName;
    public $ShippingLastName;
    public $ShippingBirthDate;
    public $ShippingStreet;
    public $ShippingHouseNumber;
    public $ShippingHouseNumberSuffix;
    public $ShippingPostalCode;
    public $ShippingCity;
    public $ShippingCountryCode;
    public $ShippingEmail;
    public $ShippingPhoneNumber;
    public $ShippingLanguage;
    public $ShippingCosts;
    public $ShippingCostsTax;
    public $CustomerIPAddress;
    public $Accept;
    public $CompanyCOCRegistration;
    public $CompanyName;
    public $CostCentre;
    public $VatNumber;

    public function __construct()
    {
        $this->type = "afterpay";
        $this->version = '1';
        $this->mode = Config::getMode('AFTERPAY');
    }

    // @codingStandardsIgnoreStart
    public function pay($customVars = Array())
    {
    // @codingStandardsIgnoreEnd
        return null;
    }

    public function payAfterpay($products = array(), $customVars = array())
    {
        $itemsTotalAmount = 0;

        $this->data['customVars'][$this->type]["Category"][0]["value"] = 'Person';
        $this->data['customVars'][$this->type]["Category"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]["Category"][1]["value"] = 'Person';
        $this->data['customVars'][$this->type]["Category"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["FirstName"][0]["value"] = $this->BillingFirstName;
        $this->data['customVars'][$this->type]["FirstName"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]['FirstName'][1]["value"] = !empty($this->ShippingFirstName) ? $this->ShippingFirstName : $this->BillingFirstName;
        $this->data['customVars'][$this->type]["FirstName"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["LastName"][0]["value"] = $this->BillingLastName;
        $this->data['customVars'][$this->type]["LastName"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]['LastName'][1]["value"] = !empty($this->ShippingLastName) ? $this->ShippingLastName : $this->BillingLastName;
        $this->data['customVars'][$this->type]["LastName"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["Street"][0]["value"] = $this->BillingStreet;
        $this->data['customVars'][$this->type]["Street"][0]["group"] = 'BillingCustomer';

        $this->data['customVars'][$this->type]['Street'][1]["value"] = !empty($this->ShippingStreet) ? $this->ShippingStreet : $this->BillingStreet;
        $this->data['customVars'][$this->type]["Street"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["StreetNumber"][0]["value"] = $this->BillingHouseNumber . ' ';
        $this->data['customVars'][$this->type]["StreetNumber"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]['StreetNumber'][1]["value"] = !empty($this->ShippingHouseNumber) ? $this->ShippingHouseNumber . ' ' : $this->BillingHouseNumber . ' ';
        $this->data['customVars'][$this->type]["StreetNumber"][1]["group"] = 'ShippingCustomer';

        if(!empty($this->BillingHouseNumberSuffix)){
            $this->data['customVars'][$this->type]["StreetNumberAdditional"][0]["value"] = $this->BillingHouseNumberSuffix;
            $this->data['customVars'][$this->type]["StreetNumberAdditional"][0]["group"] = 'BillingCustomer';
        }

        if(!empty($this->BillingHouseNumberSuffix) || !empty($this->ShippingHouseNumberSuffix)){
            $this->data['customVars'][$this->type]['StreetNumberAdditional'][1]["value"] = !empty($this->ShippingHouseNumberSuffix) ? $this->ShippingHouseNumberSuffix : $this->BillingHouseNumberSuffix;
            $this->data['customVars'][$this->type]["StreetNumberAdditional"][1]["group"] = 'ShippingCustomer';
        }

        $this->data['customVars'][$this->type]["PostalCode"][0]["value"] = $this->BillingPostalCode;
        $this->data['customVars'][$this->type]["PostalCode"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]['PostalCode'][1]["value"] = !empty($this->ShippingPostalCode) ? $this->ShippingPostalCode : $this->BillingPostalCode;
        $this->data['customVars'][$this->type]["PostalCode"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["City"][0]["value"] = $this->BillingCity;
        $this->data['customVars'][$this->type]["City"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]['City'][1]["value"] = !empty($this->ShippingCity) ? $this->ShippingCity : $this->BillingCity;
        $this->data['customVars'][$this->type]["City"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["Country"][0]["value"] = $this->BillingCountry;
        $this->data['customVars'][$this->type]["Country"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]['Country'][1]["value"] = !empty($this->ShippingCountryCode) ? $this->ShippingCountryCode : $this->BillingCountry;
        $this->data['customVars'][$this->type]["Country"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["Email"][0]["value"] = $this->BillingEmail;
        $this->data['customVars'][$this->type]["Email"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]["Email"][1]["value"] = $this->BillingEmail;
        $this->data['customVars'][$this->type]["Email"][1]["group"] = 'ShippingCustomer';


        if( (isset($this->ShippingCountryCode) && in_array($this->ShippingCountryCode, ['NL', 'BE'])) || ( !isset($this->ShippingCountryCode) && in_array($this->BillingCountry, ['NL', 'BE'])) ){

            // Send parameters (Salutation, BirthDate, MobilePhone and Phone) if shipping country is NL || BE.
            $this->data['customVars'][$this->type]["Salutation"][0]["value"] = ($this->BillingGender) == '1' ? 'Mr' : 'Mrs';
            $this->data['customVars'][$this->type]["Salutation"][0]["group"] = 'BillingCustomer';
            $this->data['customVars'][$this->type]["Salutation"][1]["value"] = ($this->ShippingGender) == '1' ? 'Mr' : 'Mrs';
            $this->data['customVars'][$this->type]["Salutation"][1]["group"] = 'ShippingCustomer';

            $this->data['customVars'][$this->type]["BirthDate"][0]["value"] = $this->BillingBirthDate;
            $this->data['customVars'][$this->type]["BirthDate"][0]["group"] = 'BillingCustomer';
            $this->data['customVars'][$this->type]["BirthDate"][1]["value"] = !empty($this->ShippingBirthDate) ? $this->ShippingBirthDate :  $this->BillingBirthDate;
            $this->data['customVars'][$this->type]["BirthDate"][1]["group"] = 'ShippingCustomer';

            $this->data['customVars'][$this->type]["MobilePhone"][0]["value"] = $this->BillingPhoneNumber;
            $this->data['customVars'][$this->type]["MobilePhone"][0]["group"] = 'BillingCustomer';
            $this->data['customVars'][$this->type]["MobilePhone"][1]["value"] = !empty($this->ShippingPhoneNumber) ? $this->ShippingPhoneNumber : $this->BillingPhoneNumber;
            $this->data['customVars'][$this->type]["MobilePhone"][1]["group"] = 'ShippingCustomer';

            $this->data['customVars'][$this->type]["Phone"][0]["value"] = $this->BillingPhoneNumber;
            $this->data['customVars'][$this->type]["Phone"][0]["group"] = 'BillingCustomer';
            $this->data['customVars'][$this->type]["Phone"][1]["value"] = !empty($this->ShippingPhoneNumber) ? $this->ShippingPhoneNumber : $this->BillingPhoneNumber;
            $this->data['customVars'][$this->type]["Phone"][1]["group"] = 'ShippingCustomer';
        }

        if( (isset($this->ShippingCountryCode) && ($this->ShippingCountryCode == "FI")) || (!isset($this->ShippingCountryCode) && ($this->BillingCountry == "FI"))) {
            // Send parameter IdentificationNumber if country equals FI.
            $this->data['customVars'][$this->type]["IdentificationNumber"][0]["value"] = $this->IdentificationNumber;
            $this->data['customVars'][$this->type]["IdentificationNumber"][0]["group"] = 'BillingCustomer';
            // Send parameter IdentificationNumber if country equals FI.
            $this->data['customVars'][$this->type]["IdentificationNumber"][1]["value"] = $this->IdentificationNumber;
            $this->data['customVars'][$this->type]["IdentificationNumber"][1]["group"] = 'ShippingCustomer';
        }


        // Merge products with same SKU 

        $mergedProducts = array();
        foreach ($products as $product) {
            if (! isset($mergedProducts[$product['ArticleId']])) {
                $mergedProducts[$product['ArticleId']] = $product;
            } else {
                $mergedProducts[$product['ArticleId']]["ArticleQuantity"] += 1;
            }
        }

        $products = $mergedProducts;

        $i = 1;
        foreach($products as $p) {
            $this->data['customVars'][$this->type]["Description"][$i - 1]["value"] = $p["ArticleDescription"];
            $this->data['customVars'][$this->type]["Description"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["Identifier"][$i - 1]["value"] = $p["ArticleId"];
            $this->data['customVars'][$this->type]["Identifier"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["Quantity"][$i - 1]["value"] = $p["ArticleQuantity"];
            $this->data['customVars'][$this->type]["Quantity"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["GrossUnitprice"][$i - 1]["value"] = $p["ArticleUnitprice"];
            $this->data['customVars'][$this->type]["GrossUnitprice"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["VatPercentage"][$i - 1]["value"] = isset($p["ArticleVatcategory"]) ? $p["ArticleVatcategory"] : 0;
            $this->data['customVars'][$this->type]["VatPercentage"][$i - 1]["group"] = 'Article';
            $itemsTotalAmount += $this->data['customVars'][$this->type]["GrossUnitprice"][$i - 1]["value"] * $p["ArticleQuantity"];
            $i++;
        }

        $this->data['customVars'][$this->type]["Description"][$i]["value"] = 'Shipping Cost';
        $this->data['customVars'][$this->type]["Description"][$i]["group"] = 'Article';
        $this->data['customVars'][$this->type]["Identifier"][$i]["value"] = 'shipping';
        $this->data['customVars'][$this->type]["Identifier"][$i]["group"] = 'Article';
        $this->data['customVars'][$this->type]["Quantity"][$i]["value"] = '1';
        $this->data['customVars'][$this->type]["Quantity"][$i]["group"] = 'Article';
        $this->data['customVars'][$this->type]["GrossUnitprice"][$i]["value"] = (!empty($this->ShippingCosts) ? $this->ShippingCosts : '0');
        $itemsTotalAmount += $this->data['customVars'][$this->type]["GrossUnitprice"][$i]["value"];
        $this->data['customVars'][$this->type]["GrossUnitprice"][$i]["group"] = 'Article';
        $this->data['customVars'][$this->type]["VatPercentage"][$i]["value"] = (!empty($this->ShippingCostsTax) ? $this->ShippingCostsTax : '0');
        $this->data['customVars'][$this->type]["VatPercentage"][$i]["group"] = 'Article';

        if ($this->usenotification && !empty($customVars['Customeremail'])) {
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

        if($this->amountDedit != $itemsTotalAmount){
            $diff = $this->amountDedit - $itemsTotalAmount;

            $this->data['customVars'][$this->type]["Description"][$i - 1]["value"] = 'Discount/Fee';
            $this->data['customVars'][$this->type]["Description"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["Identifier"][$i - 1]["value"] = '1';
            $this->data['customVars'][$this->type]["Identifier"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["Quantity"][$i - 1]["value"] = 1;
            $this->data['customVars'][$this->type]["Quantity"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["GrossUnitprice"][$i - 1]["value"] = $diff;
            $this->data['customVars'][$this->type]["GrossUnitprice"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["VatPercentage"][$i - 1]["value"] = 0;
            $this->data['customVars'][$this->type]["VatPercentage"][$i - 1]["group"] = 'Article';

        }
        return parent::pay();
    }

}
