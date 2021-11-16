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

class Klarna extends PaymentMethod
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
        $this->type = "klarna";
        $this->version = '0';
        $this->mode = Config::getMode('KLARNA');
    }

    // @codingStandardsIgnoreStart
    public function pay($customVars = Array())
    {
    // @codingStandardsIgnoreEnd
        return null;
    }

    // @codingStandardsIgnoreStart
    public function payKlarna($products = array(), $customVars = array())
    {
        // @codingStandardsIgnoreEnd
        $itemsTotalAmount = 0;
        $business = unserialize(Configuration::get('BUCKAROO_KLARNA_BUSINESS'));
        $this->data['customVars'][$this->type]["Category"][0]["value"] = $business;
        $this->data['customVars'][$this->type]["Category"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]["Category"][1]["value"] = $business;
        $this->data['customVars'][$this->type]["Category"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["FirstName"][0]["value"] = $this->BillingFirstName;
        $this->data['customVars'][$this->type]["FirstName"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]['FirstName'][1]["value"] =
            !empty($this->ShippingFirstName) ? $this->ShippingFirstName : $this->BillingFirstName;
        $this->data['customVars'][$this->type]["FirstName"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["LastName"][0]["value"] = $this->BillingLastName;
        $this->data['customVars'][$this->type]["LastName"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]['LastName'][1]["value"] =
            !empty($this->ShippingLastName) ? $this->ShippingLastName : $this->BillingLastName;
        $this->data['customVars'][$this->type]["LastName"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["Street"][0]["value"] = $this->BillingStreet;
        $this->data['customVars'][$this->type]["Street"][0]["group"] = 'BillingCustomer';

        $this->data['customVars'][$this->type]['Street'][1]["value"] =
            !empty($this->ShippingStreet) ? $this->ShippingStreet : $this->BillingStreet;
        $this->data['customVars'][$this->type]["Street"][1]["group"] = 'ShippingCustomer';

        $this->BillingHouseNumber = $this->BillingHouseNumber ? $this->BillingHouseNumber : 1;
        $this->data['customVars'][$this->type]["StreetNumber"][0]["value"] = $this->BillingHouseNumber . ' ';
        $this->data['customVars'][$this->type]["StreetNumber"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]['StreetNumber'][1]["value"] =
            !empty($this->ShippingHouseNumber) ? $this->ShippingHouseNumber . ' ' : $this->BillingHouseNumber . ' ';
        $this->data['customVars'][$this->type]["StreetNumber"][1]["group"] = 'ShippingCustomer';

        if (!empty($this->BillingHouseNumberSuffix)) {
            $this->data['customVars'][$this->type]["StreetNumberAdditional"][0]["value"] =
                $this->BillingHouseNumberSuffix;
            $this->data['customVars'][$this->type]["StreetNumberAdditional"][0]["group"] = 'BillingCustomer';
        }

        if (!empty($this->BillingHouseNumberSuffix) || !empty($this->ShippingHouseNumberSuffix)) {
            $this->data['customVars'][$this->type]['StreetNumberAdditional'][1]["value"] =
                !empty($this->ShippingHouseNumberSuffix) ?
                    $this->ShippingHouseNumberSuffix : $this->BillingHouseNumberSuffix;
            $this->data['customVars'][$this->type]["StreetNumberAdditional"][1]["group"] = 'ShippingCustomer';
        }

        $this->data['customVars'][$this->type]["PostalCode"][0]["value"] = $this->BillingPostalCode;
        $this->data['customVars'][$this->type]["PostalCode"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]['PostalCode'][1]["value"] =
            !empty($this->ShippingPostalCode) ? $this->ShippingPostalCode : $this->BillingPostalCode;
        $this->data['customVars'][$this->type]["PostalCode"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["City"][0]["value"] = $this->BillingCity;
        $this->data['customVars'][$this->type]["City"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]['City'][1]["value"] =
            !empty($this->ShippingCity) ? $this->ShippingCity : $this->BillingCity;
        $this->data['customVars'][$this->type]["City"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["Country"][0]["value"] = $this->BillingCountry;
        $this->data['customVars'][$this->type]["Country"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]['Country'][1]["value"] =
            !empty($this->ShippingCountryCode) ? $this->ShippingCountryCode : $this->BillingCountry;
        $this->data['customVars'][$this->type]["Country"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["Email"][0]["value"] = $this->BillingEmail;
        $this->data['customVars'][$this->type]["Email"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]["Email"][1]["value"] = $this->BillingEmail;
        $this->data['customVars'][$this->type]["Email"][1]["group"] = 'ShippingCustomer';

        $this->data['customVars'][$this->type]["Gender"][0]["value"] =
            ($this->BillingGender) == '1' ? 'male' : 'female';
        $this->data['customVars'][$this->type]["Gender"][0]["group"] = 'BillingCustomer';
        $this->data['customVars'][$this->type]["Gender"][1]["value"] =
            ($this->ShippingGender) == '1' ? 'male' : 'female';
        $this->data['customVars'][$this->type]["Gender"][1]["group"] = 'ShippingCustomer';

        if (!empty($this->BillingPhoneNumber)) {
            $this->data['customVars'][$this->type]["Phone"][0]["value"] = $this->BillingPhoneNumber;
            $this->data['customVars'][$this->type]["Phone"][0]["group"] = 'BillingCustomer';
        }

        if (!empty($this->ShippingPhoneNumber) || !empty($this->BillingPhoneNumber)) {
            $this->data['customVars'][$this->type]["Phone"][1]["value"] =
                !empty($this->ShippingPhoneNumber) ? $this->ShippingPhoneNumber : $this->BillingPhoneNumber;
            $this->data['customVars'][$this->type]["Phone"][1]["group"] = 'ShippingCustomer';
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
        foreach ($products as $p) {
            $this->data['customVars'][$this->type]["Description"][$i - 1]["value"] = $p["ArticleDescription"];
            $this->data['customVars'][$this->type]["Description"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["Identifier"][$i - 1]["value"] = $p["ArticleId"];
            $this->data['customVars'][$this->type]["Identifier"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["Quantity"][$i - 1]["value"] = $p["ArticleQuantity"];
            $this->data['customVars'][$this->type]["Quantity"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["GrossUnitPrice"][$i - 1]["value"] = $p["ArticleUnitprice"];
            $this->data['customVars'][$this->type]["GrossUnitPrice"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["VatPercentage"][$i - 1]["value"] =
                isset($p["ArticleVatcategory"]) ? $p["ArticleVatcategory"] : 0;
            $this->data['customVars'][$this->type]["VatPercentage"][$i - 1]["group"] = 'Article';
            $itemsTotalAmount +=
                $this->data['customVars'][$this->type]["GrossUnitPrice"][$i - 1]["value"] * $p["ArticleQuantity"];
            $i++;
        }

        if (!empty($this->ShippingCosts) && $this->ShippingCosts > 0) {
            $this->data['customVars'][$this->type]["Description"][$i]["value"] = 'Shipping Cost';
            $this->data['customVars'][$this->type]["Description"][$i]["group"] = 'Article';
            $this->data['customVars'][$this->type]["Identifier"][$i]["value"] = 'shipping';
            $this->data['customVars'][$this->type]["Identifier"][$i]["group"] = 'Article';
            $this->data['customVars'][$this->type]["Quantity"][$i]["value"] = '1';
            $this->data['customVars'][$this->type]["Quantity"][$i]["group"] = 'Article';
            $this->data['customVars'][$this->type]["GrossUnitPrice"][$i]["value"] =
                (!empty($this->ShippingCosts) ? $this->ShippingCosts : '0');
            $itemsTotalAmount += $this->data['customVars'][$this->type]["GrossUnitPrice"][$i]["value"];
            $this->data['customVars'][$this->type]["GrossUnitPrice"][$i]["group"] = 'Article';
            $this->data['customVars'][$this->type]["VatPercentage"][$i]["value"] =
                (!empty($this->ShippingCostsTax) ? $this->ShippingCostsTax : '0');
            $this->data['customVars'][$this->type]["VatPercentage"][$i]["group"] = 'Article';
        }

        if ($this->amountDedit != $itemsTotalAmount) {
            $diff = $this->amountDedit - $itemsTotalAmount;

            $this->data['customVars'][$this->type]["Description"][$i - 1]["value"] = 'Discount/Fee';
            $this->data['customVars'][$this->type]["Description"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["Identifier"][$i - 1]["value"] = '1';
            $this->data['customVars'][$this->type]["Identifier"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["Quantity"][$i - 1]["value"] = 1;
            $this->data['customVars'][$this->type]["Quantity"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["GrossUnitPrice"][$i - 1]["value"] = $diff;
            $this->data['customVars'][$this->type]["GrossUnitPrice"][$i - 1]["group"] = 'Article';
            $this->data['customVars'][$this->type]["VatPercentage"][$i - 1]["value"] = 0;
            $this->data['customVars'][$this->type]["VatPercentage"][$i - 1]["group"] = 'Article';
        }
        return parent::pay();
    }
}
