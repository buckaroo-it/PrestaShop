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

if (!defined('_PS_VERSION_')) {
    exit;
}

class TrustlyCheckout extends Checkout
{
    protected $customVars = [];

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    final public function setCheckout()
    {
        parent::setCheckout();

        $this->customVars = [
            'customer' => $this->getCustomer(),
            'country' => Tools::strtoupper((new Country($this->invoice_address->id_country))->iso_code),
        ];
    }

    /**
     * Get customer data
     *
     * @return array
     */
    protected function getCustomer(): array
    {
        // 1. Make sure we really have a Customer object if the cart is linked
        if ((!isset($this->customer) || !$this->customer->id) && $this->context->cart->id_customer) {
            $this->customer = new \Customer((int) $this->context->cart->id_customer);
        }

        // 2. Derive the e‑mail (customer first, cookie second)
        $email = '';
        if (isset($this->customer->email) && \Validate::isEmail($this->customer->email)) {
            $email = $this->customer->email;
        } elseif (!empty($this->context->cookie->email) && \Validate::isEmail($this->context->cookie->email)) {
            $email = $this->context->cookie->email;
        }

        return [
            'firstName' => $this->invoice_address->firstname,
            'lastName'  => $this->invoice_address->lastname,
            'email'     => $email,
        ];
    }

    public function startPayment()
    {
        $this->payment_response = $this->payment_request->pay($this->customVars);
    }

    public function isRedirectRequired()
    {
        return true;
    }

    public function isVerifyRequired()
    {
        return false;
    }

    protected function initialize()
    {
        $this->payment_request = PaymentRequestFactory::create(PaymentRequestFactory::REQUEST_TYPE_TRUSTLY);
    }
}
