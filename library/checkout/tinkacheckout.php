<?php
/**
 *
 *
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

class TinkaCheckout extends Checkout
{

    protected $customVars = array();

    final public function setCheckout()
    {
        parent::setCheckout();
        $this->customVars = [
            'customer' => $this->getCustomer(),
            'email'    => $this->customer->email,
            'billing'  => $this->getAddress('billing'),
            'shipping' => $this->getAddress('shipping'),
            'articles' => $this->getArticles()
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
        $this->payment_request = PaymentRequestFactory::create(PaymentRequestFactory::REQUEST_TYPE_TINKA);
    }

    /**
     * Get customer data
     *
     * @return array
     */
    protected function getCustomer()
    {
        return [
            "firstName" => $this->invoice_address->firstname,
            "lastName" => $this->invoice_address->lastname,
            "initials" => initials($this->invoice_address->firstname.' '.$this->invoice_address->lastname),
            "birthDate" => date(
                'Y-m-d',
                strtotime(
                    Tools::getValue("customerbirthdate_y_billing") . "-" .
                    Tools::getValue( "customerbirthdate_m_billing") . "-" .
                    Tools::getValue("customerbirthdate_d_billing")
                )
            )
        ];
    }

    /**
     * Get customer address
     *
     * @return array
     */
    protected function getAddress($type)
    {
        $addressType = 'invoice_address';

        if ($type == 'shipping' && isset($this->shipping_address)) {

            $addressType = 'shipping_address';
        }
        
        $address_components = $this->getAddressComponents($this->$addressType->address1);

        if (empty($address_components['house_number'])) {
            $address_components['house_number'] = $this->$addressType->address2;
        }
        $data = [
            "street" => $address_components['street'],
            "houseNumber" => $address_components['house_number'],
            "zipcode" => $this->$addressType->postcode,
            "city" => $this->$addressType->city,
            "country" => Tools::strtoupper(
                (new Country($this->$addressType->id_country))->iso_code
            ),
        ];

        if(!empty($address_components['number_addition'])) {
            return array_merge(
                $data,
                ["houseNumberAdditional" => $address_components['number_addition']]
            );
        }

        return ['phone' => (isset($this->$addressType->phone_mobile)) ? $this->$addressType->phone_mobile : $this->addressType->phone,
                'address' => $data
               ];
    }

    /**
     * Get order articles
     *
     * @return array
     */
    protected function getArticles()
    {
        $total = 0;
        $products = [];
        foreach ($this->products as $item) {
            $products[] = [
                "type" => 1,
                "description" => $item['name'],
                "unitCode" => $item['id_product'],
                "quantity" => $item["quantity"],
                "price" =>round($item["price_wt"], 2)
            ];
        }

        $wrapping = $this->cart->getOrderTotal(true, CartCore::ONLY_WRAPPING);
        if ($wrapping > 0) {

            $products[] = [
                "type" => 1,
                "description" => 'Wrapping',
                "unitCode" => 'WRAP',
                "quantity" => 1,
                "price" =>round($wrapping, 2)
            ];
        }


        $discounts = $this->cart->getOrderTotal(true, CartCore::ONLY_DISCOUNTS);
        if ($discounts > 0) {

            $products[] = [
                "type" => 1,
                "description" => 'Discounts',
                "unitCode" => 'DISC',
                "quantity" => 1,
                "price" => - round($discounts, 2)
            ];
        }

        $shipping = $this->cart->getOrderTotal(true, CartCore::ONLY_SHIPPING);
        if ($shipping > 0) {

            $products[] = [
                "type" => 1,
                "description" => 'Shipping',
                "unitCode" => 'SHIP',
                "quantity" => 1,
                "price" =>round($shipping, 2)
            ];
        }
        return $products;
    }
}
