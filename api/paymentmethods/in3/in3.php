<?php
/**
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
require_once dirname(__FILE__) . '/../paymentmethod.php';

class In3 extends PaymentMethod
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
    public $AddressesDiffer;
    public $ShippingStreet;
    public $ShippingHouseNumber;
    public $ShippingHouseNumberSuffix;
    public $ShippingPostalCode;
    public $ShippingCity;
    public $ShippingCountryCode;
    public $ShippingPhoneNumber;
    public $CustomerNumber;

    public function __construct()
    {
        $this->type = 'in3';
        $this->mode = Config::getMode($this->type);
    }

    public function pay($customVars = [])
    {
        $this->payload = $this->getPayload($customVars);

        return parent::executeCustomPayAction('pay');
    }

    public function getPayload($data)
    {
        $payload = [
            'description' => $this->description,
            'invoiceDate' => date('d-m-Y'),
            'version' => $this->version,
            'billing' => [
                'recipient' => [
                    'category'              => 'B2C',
                    'initials'              => $this->BillingInitials,
                    'firstName'             => $this->BillingFirstName,
                    'lastName'              => $this->BillingLastName,
                    'birthDate'             => $this->BillingBirthDate,
                    'customerNumber'        => $this->CustomerNumber,
                    'phone'                 => $this->BillingPhoneNumber,
                    'country'               => $this->BillingCountry,
                ],
                'address' => [
                    'street'                => $this->BillingStreet,
                    'houseNumber'           => $this->BillingHouseNumber,
                    'zipcode'               => $this->BillingPostalCode,
                    'city'                  => $this->BillingCity,
                    'country'               => $this->BillingCountry,
                ],
                'phone' => [
                    'phone' => $this->BillingPhoneNumber,
                ],
                'email' => $this->BillingEmail,
            ],
            'articles' => $data['articles'],
        ];
        if ($this->BillingHouseNumberSuffix) {
            $payload['billing']['address']['houseNumberAdditional'] = $this->BillingHouseNumberSuffix;
        }
        // Add shipping address if is different
        if ($this->addShippingIfDifferent()) {
            $payload['shipping'] = $this->addShippingIfDifferent();
        }

        return $payload;
    }

    private function addShippingIfDifferent()
    {
        if ($this->AddressesDiffer) {
            $payload = [
                'address' => [
                    'street' => $this->ShippingStreet,
                    'houseNumber' => $this->ShippingHouseNumber,
                    'zipcode' => $this->ShippingPostalCode,
                    'city' => $this->ShippingCity,
                    'country' => $this->ShippingCountryCode,
                ],
            ];

            if ($this->BillingHouseNumberSuffix) {
                $payload['address']['houseNumberAdditional'] = $this->ShippingHouseNumberSuffix;
            }

            return $payload;
        }
    }
}
