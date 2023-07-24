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

class Klarna extends PaymentMethod
{
    public $BillingGender;
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
    public $CustomerIPAddress;
    public $CompanyCOCRegistration;
    public $CompanyName;
    public $VatNumber;

    public function __construct()
    {
        $this->type = "klarnakp";
        $this->version = '0';
        $this->mode = Config::getMode('KLARNA');
    }

    // @codingStandardsIgnoreStart
    public function pay($customVars = array())
    {
        // @codingStandardsIgnoreEnd
        return null;
    }

    public function getPayload($products)
    {
        $payload = array(
                        'gender'            => $this->BillingGender,
                        'operatingCountry'  => $this->BillingCountry,
                        'billing'           => [
                            'recipient'        => [
                                'firstName'             => $this->BillingFirstName,
                                'lastName'              => $this->BillingLastName,
                            ],
                            'address'       => [
                                'street'                => $this->BillingStreet,
                                'houseNumber'           => $this->BillingHouseNumber,
                                'zipcode'               => $this->BillingPostalCode,
                                'city'                  => $this->BillingCity,
                                'country'               => $this->BillingCountry,
                            ],
                            'phone'         => [
                                'mobile'        => ($this->BillingPhoneNumber) ? $this->BillingPhoneNumber : $this->ShippingPhoneNumber
                            ],
                            'email'         => $this->BillingEmail
                        ],
                        'articles'          => $this->getArticles($products)
                    );
        //Add shipping address if is different
        if ($this->addShippingIfDifferent()) {
            $payload['shipping'] = $this->addShippingIfDifferent();
        }
        return $payload;
    }

    private function getArticles($products)
    {
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

        foreach($products as $item) {
            $productsArr[] = [
                'identifier'    => $item['ArticleId'],
                'description'   => $item['ArticleDescription'],
                'vatPercentage' => isset($item["ArticleVatcategory"]) ? $item["ArticleVatcategory"] : 0,
                'quantity'      => $item['ArticleQuantity'],
                'price'         => $item['ArticleUnitprice']
            ];
        }


        //Add shipping costs
        if ($this->ShippingCosts > 0) {
            $productsArr[] = [
                'identifier'    => 'shipping',
                'description'   => 'Shipping Costs',
                'vatPercentage' => $this->ShippingCostsTax,
                'quantity'      => 1,
                'price'         => $this->ShippingCosts
            ];
        }

        return $productsArr;
    }

    private function addShippingIfDifferent()
    {
        if($this->AddressesDiffer == 'TRUE') {
            return [
                'recipient'        => [
                    'firstName'             => $this->ShippingFirstName,
                    'lastName'              => $this->ShippingLastName
                ],
                'address'       => [
                    'street'                => $this->ShippingStreet,
                    'houseNumber'           => $this->ShippingHouseNumber,
                    'zipcode'               => $this->ShippingPostalCode,
                    'city'                  => $this->ShippingCity,
                    'country'               => $this->ShippingCountryCode
                ],
                'email'         => $this->ShippingEmail
            ];
        }
    }
    // @codingStandardsIgnoreStart
    public function payKlarna($products = array(), $customVars = array())
    {
        $this->payload = $this->getPayload($products);
        return parent::executeCustomPayAction('reserve');
    }
}
