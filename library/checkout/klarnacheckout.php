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
            'articles' => $this->prepareKlarnaArticles($this->getArticles()),
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

        $phone = !empty($address['phone_mobile']) ? $address['phone_mobile'] : ($address['phone'] ?? '');

        $payload = [
            'recipient' => [
                'firstName' => $address['firstname'],
                'lastName' => $address['lastname'],
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

        if (!empty($phone)) {
            $payload['phone'] = [
                'mobile' => $phone,
            ];
        }

        return $payload;
    }

    public function getShippingAddress()
    {
        $carrierHandler = new CarrierHandler($this->cart);
        $sendCloudData = $carrierHandler->handleSendCloud() ?? [];

        return $this->getAddress(array_merge((array) $this->shipping_address, $sendCloudData));
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
        $this->payment_response = $this->payment_request->reserve($this->customVars);
    }

    protected function initialize()
    {
        $this->payment_request = PaymentRequestFactory::create(PaymentRequestFactory::REQUEST_TYPE_KLARNA);
    }

    protected function prepareKlarnaArticles(array $articles): array
    {
        foreach ($articles as $key => $article) {
            if (empty($article['type'])) {
                $articles[$key]['type'] = $this->resolveKlarnaArticleType($article);
            }
        }

        return $articles;
    }

    protected function resolveKlarnaArticleType(array $article): string
    {
        $identifier = isset($article['identifier']) ? (string) $article['identifier'] : '';
        $price = isset($article['price']) ? (float) $article['price'] : 0.0;
        $description = isset($article['description']) ? Tools::strtolower((string) $article['description']) : '';

        if ($identifier === 'shipping' || $description === 'shipping costs') {
            return 'shipping_fee';
        }

        if ($price < 0 || $description === 'discount') {
            return 'discount';
        }

        if (in_array($identifier, ['0', 'buckaroo_fee'], true) || in_array($description, ['wrapping', 'buckaroo_fee'], true)) {
            return 'surcharge';
        }

        return 'physical';
    }
}
