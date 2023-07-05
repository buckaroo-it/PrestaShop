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

class CapayableCheckout extends Checkout
{

    protected $customVars = array();
    final public function setCheckout()
    {
        parent::setCheckout();

        $this->addRequiredDescription();
        
        $this->customVars = [
            "customer" => $this->getCustomer(),
            "address" => $this->getAddress(),
            "articles" => $this->getArticles(),
            "phone" => $this->getPhone(),
            "email" => $this->customer->email,
            "articles" => $this->getArticles()
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
        $this->payment_request = PaymentRequestFactory::create(PaymentRequestFactory::REQUEST_TYPE_CAPAYABLE);
    }


    protected function addRequiredDescription()
    {
        if(empty($this->payment_request->description)) {
            $this->payment_request->description = $this->payment_request->invoiceId;
        }
    }

    /**
     * Get customer data
     *
     * @return array
     */
    protected function getCustomer()
    {
        return [
            "LastName" => $this->invoice_address->lastname,
            "Culture" =>  'nl-NL',
            "Initials" => initials($this->invoice_address->firstname),
            "BirthDate" =>date(
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
    protected function getAddress()
    {

        $address_components = $this->getAddressComponents($this->invoice_address->address1);

        $data = [
            "Street" => $address_components['street'],
            "HouseNumber" => $address_components['house_number'],
            "ZipCode" => $this->invoice_address->postcode,
            "City" => $this->invoice_address->city,
            "Country" => Tools::strtoupper(
                (new Country($this->invoice_address->id_country))->iso_code
            ),
        ];

        if(!empty($address_components['number_addition'])) {
            return array_merge(
                $data,
                ["HouseNumberSuffix" => $address_components['number_addition']]
            );
        }

        return $data;
    }

    /**
     * Get customer phone
     *
     * @return string
     */
    public function getPhone()
    {
        $phone = Tools::getValue("customer_phone");
        if(is_scalar($phone)) {
            return (string)$phone;
        }
        return '';
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
                "Name" => $item['name'],
                "Code" => $item['id_product'],
                "Quantity" => $item["quantity"],
                "Price" =>round($item["price_wt"], 2)
            ];

            $total+= round($item["price_wt"], 2);
        }

        $wrapping = $this->cart->getOrderTotal(true, CartCore::ONLY_WRAPPING);
        if ($wrapping > 0) {

            $products[] = [
                "Name" => 'Wrapping',
                "Code" => 'WRAP',
                "Quantity" => 1,
                "Price" =>round($wrapping, 2)
            ];
            $total+= round($wrapping, 2);

        }


        $discounts = $this->cart->getOrderTotal(true, CartCore::ONLY_DISCOUNTS);
        if ($discounts > 0) {

            $products[] = [
                "Name" => 'Discounts',
                "Code" => 'DISC',
                "Quantity" => 1,
                "Price" => - round($discounts, 2)
            ];
            $total-=round($discounts, 2);

        }

        $shipping = $this->cart->getOrderTotal(true, CartCore::ONLY_SHIPPING);
        if ($shipping > 0) {

            $products[] = [
                "Name" => 'Shipping',
                "Code" => 'SHIP',
                "Quantity" => 1,
                "Price" =>round($shipping, 2)
            ];
            $total+=round($shipping, 2);
        }

        if(abs($this->payment_request->amountDedit - $total) >= 0.01) {
            $products[] = [
                "Name" => 'Other fee/discount',
                "Code" => 'OFees',
                "Quantity" => 1,
                "Price" =>round($this->payment_request->amountDedit - $total, 2)
            ];
        }
        return $products;
    }

}
