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
 * @author    Buckaroo.nl <plugins@buckaroo.nl>
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
require_once _PS_MODULE_DIR_ . 'buckaroo3/config.php';
require_once _PS_MODULE_DIR_ . 'buckaroo3/vendor/autoload.php';
class CarrierHandler
{
    private $carrier;
    private $cart;

    public function __construct($cart)
    {
        $this->cart = $cart;
        $this->initializeCarrier();
    }

    private function initializeCarrier()
    {
        $this->carrier = new Carrier((int)$this->cart->id_carrier, Configuration::get('PS_LANG_DEFAULT'));
    }

    public function handleSendCloud()
    {
        if ($this->carrier->external_module_name !== 'sendcloud') {
            return null;
        }

        $service_point = SendcloudServicePoint::getFromCart($this->cart->id);
        $point = $service_point->getDetails();

        return [
            'street' => $point->street,
            'houseNumber' => $point->house_number,
            'houseNumberSuffix' => '',
            'zipcode' => $point->postal_code,
            'city' => $point->city,
            'country' => $point->country,
        ];
    }
}
