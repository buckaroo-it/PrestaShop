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
                    'initials'              => $this->billingInitials,
                    'firstName'             => $this->billingFirstName,
                    'lastName'              => $this->billingLastName,
                    'birthDate'             => $this->billingBirthDate,
                    'customerNumber'        => $this->customerNumber,
                    'phone'                 => $this->billingPhoneNumber,
                    'country'               => $this->billingCountry,
                ],
                'address' => [
                    'street'                => $this->billingStreet,
                    'houseNumber'           => $this->billingHouseNumber,
                    'zipcode'               => $this->billingPostalCode,
                    'city'                  => $this->billingCity,
                    'country'               => $this->billingCountry,
                ],
                'phone' => [
                    'phone' => $this->billingPhoneNumber,
                ],
                'email' => $this->billingEmail,
            ],
            'articles' => $data['articles'],
        ];
        if (isset($this->billingHouseNumberSuffix)) {
            $payload['billing']['address']['houseNumberAdditional'] = $this->billingHouseNumberSuffix;
        }
        // Add shipping address if is different
        if ($this->addShippingIfDifferent()) {
            $payload['shipping'] = $this->addShippingIfDifferent();
        }

        return $payload;
    }

    private function addShippingIfDifferent()
    {
        if ($this->addressesDiffer) {
            $payload = [
                'address' => [
                    'street' => $this->shippingStreet,
                    'houseNumber' => $this->shippingHouseNumber,
                    'zipcode' => $this->shippingPostalCode,
                    'city' => $this->shippingCity,
                    'country' => $this->shippingCountryCode,
                ],
            ];

            if ($this->billingHouseNumberSuffix) {
                $payload['address']['houseNumberAdditional'] = $this->shippingHouseNumberSuffix;
            }

            return $payload;
        }
    }
}
