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

class KlarnaCheckout extends Checkout
{
    protected $customVars = [];

    final public function setCheckout()
    {
        parent::setCheckout();

        $country = new Country($this->invoice_address->id_country);

        $this->customVars = [
            'gender' => Tools::getValue('bpe_klarna_invoice_person_gender'),
            'operatingCountry' => Tools::strtoupper($country->iso_code),
            'billing' => $this->getBillingAddress(),
            'articles' => $this->getArticles(),
            'shipping' => $this->getShippingAddress(),
        ];
    }

    public function getBillingAddress()
    {
        return [
            'recipient' => [
                'firstName' => $this->invoice_address->firstname,
                'lastName' => $this->invoice_address->lastname,
            ],
            'address' => [
                'street' => $this->invoice_address->address1,
                'houseNumber' => $this->invoice_address->address2,
                'zipcode' => $this->invoice_address->postcode,
                'city' => $this->invoice_address->city,
                'country' => Tools::strtoupper(
                    (new Country($this->invoice_address->id_country))->iso_code
                ),
            ],
            'phone' => [
                'mobile' => $this->getPhone($this->invoice_address) ?: $this->getPhone($this->shipping_address)
            ],
            'email' => $this->customer->email,
        ];

    }

    public function getShippingAddress()
    {
        if (!empty($this->shipping_address)) {
            $country = new Country($this->invoice_address->id_country);
            $carrier = new Carrier((int) $this->cart->id_carrier, Configuration::get('PS_LANG_DEFAULT'));

            $address_components = $this->getAddressComponents($this->shipping_address->address1); // phpcs:ignore
            $street = $address_components['street'];
            if (empty($address_components['house_number'])) {
                $houseNumber = $this->invoice_address->address2;
            } else {
                $houseNumber = $address_components['house_number'];
            }
            $zipcode = $this->shipping_address->postcode;
            $city = $this->shipping_address->city;


            if ($carrier->external_module_name == 'sendcloud') {
                $sendCloudClassName = 'SendcloudServicePoint';
                $service_point = $sendCloudClassName::getFromCart($this->cart->id);
                $point = $service_point->getDetails();
                $street = $point->street;
                $houseNumber = $point->house_number;
                $zipcode = $point->postal_code;
                $city = $point->city;
                $country = $point->country;
            }

            return [
                'recipient' => [
                    'firstName' => $this->shipping_address->firstname,
                    'lastName' => $this->shipping_address->lastname,
                ],
                'address' => [
                    'street' => $street,
                    'houseNumber' => $houseNumber,
                    'zipcode' => $zipcode,
                    'city' => $city,
                    'country' => Tools::strtoupper($country->iso_code),
                ],
                'email' => $this->customer->email,
            ];
        }

        return null;
    }

    public function getArticles()
    {
        $products = $this->prepareProductArticles();
        $products = array_merge($products, $this->prepareWrappingArticle());
        $products = array_merge($products, $this->prepareBuckarooFeeArticle());
        $mergedProducts = $this->mergeProductsBySKU($products);

        $shippingCostArticle = $this->prepareShippingCostArticle();
        if ($shippingCostArticle) {
            $mergedProducts[] = $shippingCostArticle;
        }

        return $mergedProducts;
    }

    private function prepareBuckarooFeeArticle()
    {
        $buckarooFee = $this->getBuckarooFee();
        if ($buckarooFee <= 0) {
            return [];
        }

        return [
            'ArticleDescription' => 'buckaroo_fee',
            'ArticleId' => '0',
            'ArticleQuantity' => '1',
            'ArticleUnitprice' => round($buckarooFee, 2),
            'ArticleVatcategory' => Configuration::get('BUCKAROO_KLARNA_WRAPPING_VAT')
        ];
    }

    public function isRedirectRequired()
    {
        return true;
    }

    public function isVerifyRequired()
    {
        return false;
    }

    public function startPayment()
    {
        $this->payment_response = $this->payment_request->payKlarna($this->customVars);
    }

    protected function initialize()
    {
        $this->payment_request = PaymentRequestFactory::create(PaymentRequestFactory::REQUEST_TYPE_KLARNA);
    }
}
