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

if (!defined('_PS_VERSION_')) {
    exit;
}

class KlarnaCheckout extends Checkout
{
    protected $customVars = [];

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    final public function setCheckout()
    {
        parent::setCheckout();

        $country = new Country($this->invoice_address->id_country);

        $this->customVars = [
            'operatingCountry' => Tools::strtoupper($country->iso_code),
            'billing' => $this->getBillingAddress(),
            'articles' => $this->getArticles(),
            'shipping' => $this->getShippingAddress(),
        ];
    }

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getBillingAddress()
    {
        return $this->getAddress((array) $this->invoice_address);
    }

    protected function getAddress(array $address): array
    {
        $address_components = $this->getAddressComponents($address['address1']); // phpcs:ignore
        $address = array_merge($address, $address_components);

        return [
            'recipient' => [
                'firstName' => $address['firstname'],
                'lastName' => $address['lastname'],
                'gender' => Tools::getValue('bpe_klarna_invoice_person_gender') === '1' ? 'male' : 'female',
                'category' => 'B2C',
            ],
            'address' => [
                'street' => $address['street'],
                'houseNumber' => $address['house_number'],
                'houseNumberAdditional' => $address['address2'],
                'zipcode' => $address['zipcode'] ?? $address['postcode'],
                'city' => $address['city'],
                'country' => Tools::strtoupper((new Country($address['id_country']))->iso_code),
            ],
            'email' => $this->customer->email,
        ];
    }

    public function getShippingAddress()
    {
        $carrierHandler = new CarrierHandler($this->cart);
        $sendCloudData = $carrierHandler->handleSendCloud() ?? [];

        return $this->getAddress(array_merge((array) $this->shipping_address, $sendCloudData));
    }

    public function getArticles()
    {
        $products = $this->prepareProductArticles();
        $wrappingVat = $this->buckarooConfigService->getConfigValue('klarna', 'wrapping_vat');

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
        $this->payment_response = $this->payment_request->pay($this->customVars);
    }

    protected function initialize()
    {
        $this->payment_request = PaymentRequestFactory::create(PaymentRequestFactory::REQUEST_TYPE_KLARNA);
    }
}
