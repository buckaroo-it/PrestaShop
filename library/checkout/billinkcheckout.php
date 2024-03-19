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

class BillinkCheckout extends Checkout
{
    protected $customVars = [];
    protected $customerType;
    public const CUSTOMER_TYPE_B2C = 'B2C';
    public const CUSTOMER_TYPE_B2B = 'B2B';
    public const CUSTOMER_TYPE_BOTH = 'both';

    final public function setCheckout()
    {
        parent::setCheckout();

        $this->customerType = $this->buckarooConfigService->getConfigValue('billink', 'customer_type');

        $this->customVars = [
            'vATNumber' => $this->invoice_address->vat_number,
            'billing' => $this->getBillingAddress(),
            'articles' => $this->getArticles(),
            'shipping' => $this->getShippingAddress(),
        ];
    }

    public function isRedirectRequired()
    {
        return false;
    }

    public function isVerifyRequired()
    {
        return false;
    }

    public function startPayment()
    {
        $this->payment_response = $this->payment_request->payBillink($this->customVars);
    }

    protected function initialize()
    {
        $this->payment_request = PaymentRequestFactory::create(PaymentRequestFactory::REQUEST_TYPE_BILLINK);
    }

    public function getBillingAddress()
    {
        $birthDate = $this->getBirthDate();

        $address_components = $this->getAddressComponents($this->invoice_address->address1); // phpcs:ignore
        if (empty($address_components['house_number'])) {
            $address_components['house_number'] = $this->invoice_address->address2;
        }
        $country = new Country($this->invoice_address->id_country);

        $payload = [
            'recipient' => [
                'category' => $this->getRecipientCategory(),
                'careOf' => $this->invoice_address->firstname . ' ' . $this->invoice_address->lastname,
                'firstName' => $this->invoice_address->firstname,
                'lastName' => $this->invoice_address->lastname,
                'birthDate' => $birthDate,
                'title' => Tools::getValue('bpe_billink_person_gender'),
                'initials' => $this->initials($this->invoice_address->firstname . ' ' . $this->invoice_address->lastname),
            ],
            'address' => [
                'street' => $address_components['street'],
                'houseNumber' => $address_components['house_number'],
                'houseNumberAdditional' => $address_components['number_addition'],
                'zipcode' => $this->invoice_address->postcode,
                'city' => $this->invoice_address->city,
                'country' => Tools::strtoupper($country->iso_code),
            ],
            'phone' => [
                'mobile' => $this->getPhone($this->invoice_address) ?: $this->getPhone($this->shipping_address),
            ],
            'email' => !empty($this->customer->email) ? $this->customer->email : '',
        ];

        if (self::CUSTOMER_TYPE_B2C != $this->customerType
            && $this->companyExists($this->invoice_address->company) ? $this->invoice_address->company : null) {
            $payload['recipient']['careOf'] = $this->invoice_address->company;
            $payload['recipient']['chamberOfCommerce'] = Tools::getValue('customerbillink-coc');
        }

        return $payload;
    }

    public function getArticles()
    {
        $products = $this->prepareProductArticles();
        $wrappingVat = $this->buckarooConfigService->getConfigValue('billink', 'wrapping_vat') ?? 21;

        $additionalArticles = [
            $this->prepareWrappingArticle($wrappingVat),
            $this->prepareBuckarooFeeArticle($wrappingVat),
            $this->prepareShippingCostArticle(),
        ];

        foreach ($additionalArticles as $article) {
            if (!empty($article)) {
                $products[] = $article;
            }
        }

        return $this->mergeProductsBySKU($products);
    }

    public function getRecipientCategory()
    {
        $category = self::CUSTOMER_TYPE_B2C;
        if ($this->customerType == self::CUSTOMER_TYPE_B2B ||
            ($this->customerType == self::CUSTOMER_TYPE_BOTH && $this->companyExists($this->invoice_address->company))) {
            $category = self::CUSTOMER_TYPE_B2B;
        }
        return $category;
    }

    protected function prepareProductArticles()
    {
        $articles = [];
        foreach ($this->products as $item) {
            $tmp = [];
            $tmp['identifier'] = $item['id_product'];
            $tmp['quantity'] = $item['quantity'];
            $tmp['price'] = round($item['price_with_reduction'], 2);
            $tmp['priceExcl'] = round($item['price_with_reduction_without_tax'], 2);
            $tmp['vatPercentage'] = $item['rate'];
            $tmp['description'] = $item['name'];
            $articles[] = $tmp;
        }

        return $articles;
    }

    protected function prepareWrappingArticle($wrappingVat)
    {
        $wrappingCost = $this->cart->getOrderTotal(true, CartCore::ONLY_WRAPPING);

        return $wrappingCost > 0 ? [
            'identifier' => '0',
            'quantity' => '1',
            'price' => $wrappingCost,
            'priceExcl' => $wrappingCost,
            'vatPercentage' => $wrappingVat,
            'description' => 'Wrapping',
        ] : [];
    }

    private function prepareBuckarooFeeArticle($wrappingVat)
    {
        $buckarooFee = $this->getBuckarooFee();

        return $buckarooFee > 0 ? [
            'identifier' => '0',
            'quantity' => '1',
            'price' => round($buckarooFee, 2),
            'priceExcl' => round($buckarooFee, 2),
            'vatPercentage' => $wrappingVat,
            'description' => 'buckaroo_fee',
        ] : [];
    }

    protected function prepareShippingCostArticle()
    {
        $shippingCost = round($this->cart->getOrderTotal(true, CartCore::ONLY_SHIPPING), 2);
        if ($shippingCost <= 0) {
            return null;
        }

        $carrier = new Carrier((int) $this->cart->id_carrier, Configuration::get('PS_LANG_DEFAULT'));

        $shippingCostsTax = (version_compare(_PS_VERSION_, '1.7.6.0', '<='))
            ? $carrier->getTaxesRate(Address::initialize())
            : $carrier->getTaxesRate();

        return [
            'identifier' => 'shipping',
            'quantity' => 1,
            'price' => $shippingCost,
            'priceExcl' => $shippingCost,
            'vatPercentage' => $shippingCostsTax,
            'description' => 'Shipping Costs',
        ];
    }

    public function getBirthDate()
    {
        return date(
            'd-m-Y',
            strtotime(
                Tools::getValue('customerbirthdate_y_billing_billink') . '-' . Tools::getValue(
                    'customerbirthdate_m_billing_billink'
                ) . '-' . Tools::getValue('customerbirthdate_d_billing_billink')
            )
        );
    }

    public function getShippingAddress()
    {
        if (!empty($this->shipping_address)) {
            $country = new Country($this->shipping_address->id_country);

            $address_components = $this->getAddressComponents($this->shipping_address->address1); // phpcs:ignore
            $street = $address_components['street'];
            if (empty($address_components['house_number'])) {
                $houseNumber = $this->invoice_address->address2;
            } else {
                $houseNumber = $address_components['house_number'];
            }
            $houseNumberSuffix = $address_components['number_addition'];
            $birthDate = $this->getBirthDate();
            $zipcode = $this->shipping_address->postcode;
            $city = $this->shipping_address->city;

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
                    'category' => $this->getRecipientCategory(),
                    'careOf' => $this->shipping_address->firstname . ' ' . $this->shipping_address->lastname,
                    'firstName' => $this->shipping_address->firstname,
                    'lastName' => $this->shipping_address->lastname,
                    'birthDate' => $birthDate,
                    'title' => Tools::getValue('bpe_billink_person_gender'),
                    'initials' => $this->initials($this->shipping_address->firstname . ' ' . $this->shipping_address->lastname),
                ],
                'address' => [
                    'street' => $street,
                    'houseNumber' => $houseNumber,
                    'houseNumberAdditional' => $houseNumberSuffix,
                    'zipcode' => $zipcode,
                    'city' => $city,
                    'country' => Tools::strtoupper($country->iso_code),
                ],
            ];

            if (self::CUSTOMER_TYPE_B2C != $this->customerType
                && ($this->companyExists($this->shipping_address->company) ? $this->shipping_address->company : null)) {
                $payload['recipient']['careOf'] = $this->shipping_address->company;
                $payload['recipient']['category'] = 'B2B';
            }

            return $payload;
        }

        return null;
    }
}
