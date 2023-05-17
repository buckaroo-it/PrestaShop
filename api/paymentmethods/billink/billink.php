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

use Buckaroo\Resources\Constants\RecipientCategory;

class Billink extends PaymentMethod
{
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
    public $BillingGender;
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
    public $ShippingCosts;
    public $ShippingCostsTax;
    public $CompanyCOCRegistration;
    public $CompanyName;
    public $VatNumber;
    public $ShippingCompanyName;
    public $BillingCompanyName;
    public $CustomerType;

    public const CUSTOMER_TYPE_B2C = 'b2c';
    public const CUSTOMER_TYPE_B2B = 'b2b';
    public const CUSTOMER_TYPE_BOTH = 'both';

    public function __construct()
    {
        $this->type = "billink";
        $this->version = '1';
        $this->mode = Config::getMode('BILLINK');
    }

    // @codingStandardsIgnoreStart
    public function pay($customVars = Array())
    {
    // @codingStandardsIgnoreEnd
        return null;
    }

    public function payBillink($products, $customVars = array())
    {
        $this->payload = $this->getPayload($products);
        return parent::pay();
    }

    public function getPayload($products)
    {        
        $payload = array(
            'vATNumber' => $this->VatNumber,
            'billing'       => [
                'recipient'        => [
                    'category'              => (self::CUSTOMER_TYPE_B2B == $this->CustomerType) ? 'B2B' : 'B2C',
                    'careOf'                => $this->BillingFirstName . ' ' . $this->BillingLastName,
                    'firstName'             => $this->BillingFirstName,
                    'lastName'              => $this->BillingLastName,
                    'birthDate'             => ($this->BillingBirthDate) ? $this->BillingBirthDate : null,
                    'title'                 => $this->BillingGender,
                    'initials'              => $this->BillingInitials,                    
                ],
                'address'       => [
                    'street'                => $this->BillingStreet,
                    'houseNumber'           => $this->BillingHouseNumber,
                    'houseNumberAdditional' => $this->BillingHouseNumberSuffix,
                    'zipcode'               => $this->BillingPostalCode,
                    'city'                  => $this->BillingCity,
                    'country'               => $this->BillingCountry,
                ],
                'phone'         => [
                    'mobile'        => ($this->BillingPhoneNumber) ? $this->BillingPhoneNumber : $this->ShippingPhoneNumber,
                ],
                'email'         => $this->BillingEmail,
            ],
            
            'articles'          => $this->getArticles($products)
        );

        //Add shipping address if is different
        if ($this->addShippingIfDifferent()) {
            $payload['shipping'] = $this->addShippingIfDifferent();
        }

        //Add company name if b2b enabled
        if (self::CUSTOMER_TYPE_B2C != $this->CustomerType) {
            if($this->BillingCompanyName){
                $payload['billing']['recipient']['careOf'] = $this->BillingCompanyName;
                $payload['billing']['recipient']['chamberOfCommerce'] = $this->CompanyCOCRegistration;

                if (isset($payload['shipping'])){
                    $payload['shipping']['recipient']['careOf'] = $this->ShippingCompanyName;
                    $payload['shipping']['recipient']['category'] = 'B2B';
                }
            }
        }
        return $payload;        
    }

    private function getArticles($products)
    {
        // Merge products with same SKU
        $mergedProducts = array();
        foreach ($products as $product) {
            if (!isset($mergedProducts[$product['ArticleId']])) {
                $mergedProducts[$product['ArticleId']] = $product;
            } else {
                $mergedProducts[$product['ArticleId']]["ArticleQuantity"] += 1;
            }
        }

        $products = $mergedProducts;

        foreach($products as $item)
        {
            $productsArr[] = [
                'identifier'    => $item['ArticleId'],
                'description'   => $item['ArticleDescription'],
                'vatPercentage' => isset($item["ArticleVatcategory"]) ? $item["ArticleVatcategory"] : 0,
                'quantity'      => $item['ArticleQuantity'],
                'price'         => $item['ArticleUnitPriceIncl'],
                'priceExcl'     => $item['ArticleUnitPriceExcl']
            ];
        }


        //Add shipping costs
        if ($this->ShippingCosts > 0) {
            $productsArr[] = [                
                'identifier'    => 'shipping',
                'description'   => 'Shipping Costs',
                'vatPercentage' => $this->ShippingCostsTax,
                'quantity'      => 1,
                'price'         => $this->ShippingCosts,
                'priceExcl'     => $this->ShippingCosts,
            ];
        }

        return $productsArr;        
    }

    private function addShippingIfDifferent()
    {
        if($this->AddressesDiffer == 'TRUE')
        {
            return [
                'recipient' => [
                    'category' => RecipientCategory::PERSON,
                    'careOf'                => $this->ShippingFirstName . ' ' . $this->ShippingFirstName,
                    'firstName'             => $this->ShippingFirstName,
                    'lastName'              => $this->ShippingLastName,
                    'birthDate'             => $this->ShippingBirthDate,
                    'title'                 => $this->ShippingGender,
                    'initials'              => $this->ShippingInitials
                ],
                'address' => [
                    'street'                => $this->ShippingStreet,
                    'houseNumber'           => $this->ShippingHouseNumber,
                    'houseNumberAdditional' => $this->ShippingHouseNumberSuffix,
                    'zipcode'               => $this->ShippingPostalCode,
                    'city'                  => $this->ShippingCity,
                    'country'               => $this->ShippingCountryCode
                ],
            ];
        }
    }
}
