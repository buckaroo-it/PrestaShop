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

class AfterPayCheckout extends Checkout
{

    protected $customVars = array();

    final public function setCheckout()
    {
        parent::setCheckout();

        $phone = '';
        if (!empty($this->invoice_address->phone_mobile)) {
            $phone = $this->invoice_address->phone_mobile;
        }
        if (empty($phone) && !empty($this->invoice_address->phone)) {
            $phone = $this->invoice_address->phone;
        }

        $ShippingCost = $this->cart->getOrderTotal(true, CartCore::ONLY_SHIPPING);
        if ($ShippingCost > 0) {
            $this->payment_request->ShippingCosts = round($ShippingCost, 2);
        }

        $language = Language::getIsoById((int) $this->cart->id_lang);

        $this->payment_request->BillingGender    = Tools::getValue("bpe_afterpay_invoice_person_gender");
        $this->payment_request->BillingFirstName = $this->invoice_address->firstname;
        $this->payment_request->BillingLastName  = $this->invoice_address->lastname;
        $this->payment_request->BillingBirthDate = date(
            'Y-m-d',
            strtotime(
                Tools::getValue("customerbirthdate_y_billing") . "-" . Tools::getValue(
                    "customerbirthdate_m_billing"
                ) . "-" . Tools::getValue("customerbirthdate_d_billing")
            )
        );
        $address_components = $this->getAddressComponents($this->invoice_address->address1);//phpcs:ignore
        if(empty($address_components['house_number'])){
            $address_components['house_number'] = $this->invoice_address->address2;
        }
        $this->payment_request->BillingStreet            = $address_components['street'];
        $this->payment_request->BillingHouseNumber       = $address_components['house_number'];
        $this->payment_request->BillingHouseNumberSuffix = $address_components['number_addition'];
        $this->payment_request->BillingPostalCode        = $this->invoice_address->postcode;
        $this->payment_request->BillingCity              = $this->invoice_address->city;
        $country                                         = new Country($this->invoice_address->id_country);
        $this->payment_request->BillingCountry           = Tools::strtoupper($country->iso_code);
        $this->payment_request->BillingEmail             = !empty($this->customer->email) ? $this->customer->email : '';
        $this->payment_request->BillingLanguage          = $language;
        $this->payment_request->BillingPhoneNumber       = $phone;
        $Discount                                        = $this->cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS);
        if ($Discount > 0) {
            $this->payment_request->Discount = round($Discount, 2);
        }

        $this->payment_request->AddressesDiffer = 'FALSE';
        if (!empty($this->shipping_address)) {
            $shippingGender = Tools::getValue("bpe_afterpay_shipping_person_gender");
            if (!$shippingGender) {
                $shippingGender = Tools::getValue("bpe_afterpay_invoice_person_gender");
            }
            $ShippingBirthDate = date(
                'Y-m-d',
                strtotime(
                    Tools::getValue("customerbirthdate_y_shipping") . "-" . Tools::getValue(
                        "customerbirthdate_m_shipping"
                    ) . "-" . Tools::getValue("customerbirthdate_d_shipping")
                )
            );
            if (!$ShippingBirthDate) {
                $ShippingBirthDate = date(
                    'Y-m-d',
                    strtotime(
                        Tools::getValue("customerbirthdate_y_billing") . "-" . Tools::getValue(
                            "customerbirthdate_m_billing"
                        ) . "-" . Tools::getValue("customerbirthdate_d_billing")
                    )
                );
            }

            $this->payment_request->AddressesDiffer           = 'TRUE';
            $this->payment_request->ShippingGender            = $shippingGender;
            $this->payment_request->ShippingInitials          = initials($this->shipping_address->firstname);
            $this->payment_request->ShippingFirstName          = $this->shipping_address->firstname;
            $this->payment_request->ShippingLastName          = $this->shipping_address->lastname;
            $this->payment_request->ShippingBirthDate         = $ShippingBirthDate;
            $address_components = $this->getAddressComponents($this->shipping_address->address1);//phpcs:ignore
            $this->payment_request->ShippingStreet            = $address_components['street'];
            $this->payment_request->ShippingHouseNumber       = $address_components['house_number'];
            $this->payment_request->ShippingHouseNumberSuffix = $address_components['number_addition'];
            $this->payment_request->ShippingPostalCode        = $this->shipping_address->postcode;
            $this->payment_request->ShippingCity              = $this->shipping_address->city;
            $country                                          = new Country($this->shipping_address->id_country);
            $this->payment_request->ShippingCountryCode       = Tools::strtoupper($country->iso_code);
            $this->payment_request->ShippingEmail             = Tools::getIsset(
                $this->customer->email
            ) ? $this->customer->email : '';
            $this->payment_request->ShippingLanguage = $language;
            $phone                                   = '';
            if (!empty($this->shipping_address->phone_mobile)) {
                $phone = $this->shipping_address->phone_mobile;
            }
            if (empty($phone) && !empty($this->shipping_address->phone)) {
                $phone = $this->shipping_address->phone;
            }
            $this->payment_request->ShippingPhoneNumber = $phone;
        }

        $carrier = new Carrier((int) $this->cart->id_carrier, Configuration::get('PS_LANG_DEFAULT'));

        $this->payment_request->ShippingCostsTax = $carrier->getTaxesRate();

        if($carrier->external_module_name == 'sendcloud'){
            $service_point = SendcloudServicePoint::getFromCart($this->cart->id);
            $point = $service_point->getDetails();
            $this->payment_request->ShippingStreet            = $point->street;
            $this->payment_request->ShippingHouseNumber       = $point->house_number;
            $this->payment_request->ShippingHouseNumberSuffix = '';
            $this->payment_request->ShippingPostalCode        = $point->postal_code;
            $this->payment_request->ShippingCity              = $point->city;
            $country                                          = $point->country;
        }

        $customerIdentificationNumber = Tools::getValue("customerIdentificationNumber");
        if (!empty($customerIdentificationNumber)) {
            $this->payment_request->IdentificationNumber = $customerIdentificationNumber;
        }

        $this->payment_request->CustomerIPAddress = $_SERVER["REMOTE_ADDR"];
        $this->payment_request->Accept            = 'TRUE';
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
        $products  = array();
        $taxvalues = Configuration::get('BUCKAROO_AFTERPAY_TAXRATE');
        if (!$taxvalues) {
            $taxvalues = array();
        } else {
            $taxvalues = unserialize($taxvalues);
        }
        foreach ($this->products as $item) {
            $tmp                       = array();
            $tmp["ArticleDescription"] = $item['name'];
            $tmp["ArticleId"]          = $item['id_product'];
            $tmp["ArticleQuantity"]    = $item["quantity"];
            $tmp["ArticleUnitprice"]   = round($item["price_wt"], 2);
            $taxId                     = TaxCore::getTaxIdByName($item["tax_name"]);

            $tmp["ArticleVatcategory"] = $item["rate"];
/*            if (Tools::getIsset($taxvalues[$taxId])) {
                $tmp["ArticleVatcategory"] = $taxvalues[$taxId];
            } else {
                $tmp["ArticleVatcategory"] = Configuration::get('BUCKAROO_AFTERPAY_DEFAULT_VAT');
            }*/
            $products[] = $tmp;
        }

        $Wrapping = $this->cart->getOrderTotal(true, CartCore::ONLY_WRAPPING);
        if ($Wrapping > 0) {
            $tmp                       = array();
            $tmp["ArticleDescription"] = 'Wrapping';
            $tmp["ArticleId"]          = '0';
            $tmp["ArticleQuantity"]    = '1';
            $tmp["ArticleUnitprice"]   = $Wrapping;
            $tmp["ArticleVatcategory"] = Configuration::get('BUCKAROO_AFTERPAY_WRAPPING_VAT');
            $products[]                = $tmp;
        }
        $this->payment_response = $this->payment_request->payAfterpay($products, $this->customVars);
    }

    protected function initialize()
    {
        $this->payment_request = PaymentRequestFactory::create(PaymentRequestFactory::REQUEST_TYPE_AFTERPAY);
    }
}
