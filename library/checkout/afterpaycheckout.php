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
include_once _PS_MODULE_DIR_ . 'buckaroo3/library/checkout/checkout.php';
include_once _PS_MODULE_DIR_ . 'buckaroo3/classes/CarrierHandler.php';

use Buckaroo\Resources\Constants\RecipientCategory;

if (!defined('_PS_VERSION_')) {
    exit;
}

class AfterPayCheckout extends Checkout
{
    public const CUSTOMER_TYPE_B2C = 'B2C';
    public const CUSTOMER_TYPE_B2B = 'B2B';
    public const CUSTOMER_TYPE_BOTH = 'both';

    protected $customVars = [];
    protected $customerType;

    final public function setCheckout()
    {
        parent::setCheckout();

        $this->customerType = $this->buckarooConfigService->getConfigValue('afterpay', 'customer_type');

        $this->customVars = [
            'clientIP' => $_SERVER['REMOTE_ADDR'],
            'billing' => $this->getBillingAddress(),
            'articles' => $this->getArticles(),
            'shipping' => $this->getShippingAddress(),
        ];
    }

    public function getCocNumber()
    {
        $customerIdentificationNumber = Tools::getValue('customerIdentificationNumber');

        if (!empty($customerIdentificationNumber)) {
            return $customerIdentificationNumber;
        }

        $cocNumber = Tools::getValue('customerafterpaynew-coc');

        if (!empty($cocNumber) && strlen(trim($cocNumber)) !== 0) {
            return $cocNumber;
        }

        return '';
    }

    public function isRedirectRequired()
    {
        return false;
    }

    public function isVerifyRequired()
    {
        return false;
    }

    /**
     * @throws PrestaShopException
     * @throws PrestaShopDatabaseException
     */
    public function getBillingAddress()
    {
        $country = new Country($this->invoice_address->id_country);

        $address_components = $this->getAddressComponents($this->invoice_address->address1); // phpcs:ignore
        if (empty($address_components['house_number'])) {
            $address_components['house_number'] = $this->invoice_address->address2;
        }

        $category = ($this->customerType == self::CUSTOMER_TYPE_B2C) ? RecipientCategory::PERSON
            : (($this->customerType == self::CUSTOMER_TYPE_B2B) ? RecipientCategory::COMPANY
                : ($this->companyExists($this->invoice_address->company) ? self::CUSTOMER_TYPE_B2B : RecipientCategory::PERSON));

        $payload = [
            'recipient' => [
                'category' => $category,
                'conversationLanguage' => Tools::strtoupper($country->iso_code),
                'careOf' => $this->invoice_address->firstname . ' ' . $this->invoice_address->lastname,
                'firstName' => $this->invoice_address->firstname,
                'lastName' => $this->invoice_address->lastname,
                'birthDate' => date(
                    'Y-m-d',
                    strtotime(
                        Tools::getValue('customerbirthdate_y_billing') . '-' . Tools::getValue(
                            'customerbirthdate_m_billing'
                        ) . '-' . Tools::getValue('customerbirthdate_d_billing')
                    )
                ),
            ],
            'phone' => [
                'mobile' => $this->getPhone($this->invoice_address),
            ],
            'address' => [
                'street' => $address_components['street'],
                'houseNumber' => $address_components['house_number'],
                'houseNumberAdditional' => $address_components['number_addition'],
                'zipcode' => $this->invoice_address->postcode,
                'city' => $this->invoice_address->city,
                'country' => Tools::strtoupper($country->iso_code),
            ],
            'email' => !empty($this->customer->email) ? $this->customer->email : '',
        ];

        if (self::CUSTOMER_TYPE_B2C != $this->customerType) {
            if ($this->companyExists($this->invoice_address->company) ? $this->invoice_address->company : null) {
                $payload['recipient']['companyName'] = $this->invoice_address->company;
                $payload['recipient']['chamberOfCommerce'] = $this->getCocNumber();
            }
        }

        return $payload;
    }

    public function getArticles()
    {
        $products = $this->prepareProductArticles();

        $wrappingVat = $this->buckarooConfigService->getConfigValue('afterpay', 'wrapping_vat');

        if ($wrappingVat == null) {
            $wrappingVat = 2;
        }

        $products = array_merge($products, $this->prepareWrappingArticle($wrappingVat));
        $products = array_merge($products, $this->prepareBuckarooFeeArticle($wrappingVat));
        $mergedProducts = $this->mergeProductsBySKU($products);

        $shippingCostArticle = $this->prepareShippingCostArticle();
        if ($shippingCostArticle) {
            $mergedProducts[] = $shippingCostArticle;
        }

        return $mergedProducts;
    }

    private function prepareBuckarooFeeArticle($wrappingVat)
    {
        $buckarooFee = $this->getBuckarooFee();
        if ($buckarooFee <= 0) {
            return [];
        }

        return [
            'identifier' => '0',
            'quantity' => '1',
            'price' => round($buckarooFee, 2),
            'vatPercentage' => $wrappingVat,
            'description' => 'buckaroo_fee',
        ];
    }

    public function getShippingAddress()
    {
        if (!empty($this->shipping_address)) {
            $country = new Country($this->invoice_address->id_country);

            $address_components = $this->getAddressComponents($this->shipping_address->address1); // phpcs:ignore
            $street = $address_components['street'];
            if (empty($address_components['house_number'])) {
                $houseNumber = $this->invoice_address->address2;
            } else {
                $houseNumber = $address_components['house_number'];
            }
            $houseNumberSuffix = $address_components['number_addition'];
            $zipcode = $this->shipping_address->postcode;
            $city = $this->shipping_address->city;

            $phone = $this->getPhone($this->shipping_address);

            $carrierHandler = new CarrierHandler($this->cart);
            $sendCloudData = $carrierHandler->handleSendCloud();

            if ($sendCloudData) {
                $street = $sendCloudData['street'];
                $houseNumber = $sendCloudData['houseNumber'];
                $houseNumberSuffix = $sendCloudData['houseNumberSuffix'];
                $zipcode = $sendCloudData['zipcode'];
                $city = $sendCloudData['city'];
                $country = $sendCloudData['country'];
            }

            $payload = [
                'recipient' => [
                    'category' => (self::CUSTOMER_TYPE_B2C == $this->customerType) ? RecipientCategory::PERSON : RecipientCategory::COMPANY,
                    'conversationLanguage' => Tools::strtoupper($country->iso_code),
                    'careOf' => $this->shipping_address->firstname . ' ' . $this->shipping_address->lastname,
                    'firstName' => $this->shipping_address->firstname,
                    'lastName' => $this->shipping_address->lastname,
                    'birthDate' => $this->getBirthDate(),
                ],
                'address' => [
                    'street' => $street,
                    'houseNumber' => $houseNumber,
                    'houseNumberAdditional' => $houseNumberSuffix,
                    'zipcode' => $zipcode,
                    'city' => $city,
                    'country' => Tools::strtoupper($country->iso_code),
                ],
                'phone' => [
                    'mobile' => $phone,
                ],
                'email' => !empty($this->customer->email) ? $this->customer->email : '',
            ];

            if (self::CUSTOMER_TYPE_B2C != $this->customerType) {
                if ($this->companyExists($this->invoice_address->company) ? $this->invoice_address->company : null) {
                    $payload['recipient']['companyName'] = $this->invoice_address->company;
                    $payload['recipient']['category'] = RecipientCategory::COMPANY;
                }
            }

            return $payload;
        }

        return null;
    }

    public function getBirthDate()
    {
        return date(
            'd-m-Y',
            strtotime(
                Tools::getValue('customerbirthdate_y_billing') . '-' . Tools::getValue(
                    'customerbirthdate_m_billing'
                ) . '-' . Tools::getValue('customerbirthdate_d_billing')
            )
        );
    }

    public function getPhone($address)
    {
        // First check if 'phone_afterpay_billing' value is available.
        $phone = Tools::getValue('phone_afterpay_billing');

        // If it's not available, then check for 'phone_mobile' in the address.
        if (empty($phone) && !empty($address->phone_mobile)) {
            $phone = $address->phone_mobile;
        }

        // If both above are not available, then check for 'phone' in the address.
        if (empty($phone) && !empty($address->phone)) {
            $phone = $address->phone;
        }

        return $phone;
    }

    public function startPayment()
    {
        $this->payment_response = $this->payment_request->payAfterpay($this->customVars);
    }

    protected function initialize()
    {
        $this->payment_request = PaymentRequestFactory::create(PaymentRequestFactory::REQUEST_TYPE_AFTERPAY);
    }
}
