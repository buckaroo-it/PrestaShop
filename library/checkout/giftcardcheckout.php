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

class GiftCardCheckout extends Checkout
{
    /** @var bool Whether card number and PIN were submitted directly */
    private $hasCardDetails = false;

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    final public function setCheckout()
    {
        parent::setCheckout();

        $cardNumber = Tools::getValue('giftcard_card_number');
        $securityCode = Tools::getValue('giftcard_security_code');

        if (!empty($cardNumber) && !empty($securityCode)) {
            $this->hasCardDetails = true;
            $this->customVars = [
                'cardNumber' => $cardNumber,
                'pin'        => $securityCode,
            ];

            $cardCode = Tools::getValue('cardCode');
            if (!empty($cardCode)) {
                $this->customVars['name'] = $cardCode;
            }
        } else {
            $this->customVars = [
                'servicesSelectableByClient' => Configuration::get('BUCKAROO_GIFTCARD_ALLOWED_CARDS'),
                'continueOnIncomplete'       => '1',
            ];
        }

        if (!empty($this->customer->email) && Validate::isEmail($this->customer->email)) {
            $this->customVars['additionalParameters'] = [
                'email' => $this->customer->email,
            ];
        }
    }

    public function startPayment()
    {
        if ($this->hasCardDetails) {
            $this->payment_response = $this->payment_request->payDirect($this->customVars);
        } else {
            $this->payment_response = $this->payment_request->pay($this->customVars);
        }
    }

    public function isRedirectRequired()
    {
        return !$this->hasCardDetails;
    }

    public function isVerifyRequired()
    {
        return false;
    }

    protected function initialize()
    {
        $this->payment_request = PaymentRequestFactory::create(PaymentRequestFactory::REQUEST_TYPE_GIFTCARD);
    }
}
