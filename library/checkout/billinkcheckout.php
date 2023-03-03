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
require_once dirname(__FILE__) . '../../logger.php';

class BillinkCheckout extends Checkout
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
        $birthDate = date(
                            'd-m-Y',
                            strtotime(
                                Tools::getValue("customerbirthdate_y_billing_billink") . "-" . Tools::getValue(
                                    "customerbirthdate_m_billing_billink"
                                ) . "-" . Tools::getValue("customerbirthdate_d_billing_billink")
                            )
                        );
        $this->payment_request->VatNumber        = $this->invoice_address->vat_number;
        $this->payment_request->BillingInitials  = initials($this->invoice_address->firstname .' '. $this->invoice_address->lastname);
        $this->payment_request->BillingFirstName = $this->invoice_address->firstname;
        $this->payment_request->BillingLastName  = $this->invoice_address->lastname;
        $this->payment_request->BillingBirthDate = $birthDate;
        $this->payment_request->BillingGender            = Tools::getValue("bpe_billink_person_gender");
        $address_components = $this->getAddressComponents($this->invoice_address->address1);//phpcs:ignore
        if (empty($address_components['house_number'])) {
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
        $this->payment_request->BillingPhoneNumber       = $phone;
        $this->payment_request->BillingCompanyName       = $this->companyExists($this->invoice_address->company) ? $this->invoice_address->company : null;
        $this->payment_request->CustomerType             = Config::get('BUCKAROO_BILLINK_CUSTOMER_TYPE');
        $Discount                                        = $this->cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS);
        if ($Discount > 0) {
            $this->payment_request->Discount = round($Discount, 2);
        }

        $this->payment_request->AddressesDiffer = 'FALSE';
        if (!empty($this->shipping_address)) {
            $this->payment_request->AddressesDiffer           = 'TRUE';
            $this->payment_request->ShippingInitials          = initials($this->shipping_address->firstname .' '. $this->shipping_address->lastname);
            $this->payment_request->ShippingInitials          = initials($this->shipping_address->firstname);
            $this->payment_request->ShippingFirstName         = $this->shipping_address->firstname;
            $this->payment_request->ShippingLastName          = $this->shipping_address->lastname;
            $this->payment_request->ShippingCompanyName       = $this->companyExists($this->shipping_address->company) ? $this->shipping_address->company : null;
            $this->payment_request->ShippingBirthDate         = $birthDate;
            $this->payment_request->ShippingGender            = Tools::getValue("bpe_billink_person_gender");
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

        if ($carrier->external_module_name == 'sendcloud') {
            $sendCloudClassName = 'SendcloudServicePoint';
            $service_point = $sendCloudClassName::getFromCart($this->cart->id);
            $point = $service_point->getDetails();
            $this->payment_request->ShippingStreet            = $point->street;
            $this->payment_request->ShippingHouseNumber       = $point->house_number;
            $this->payment_request->ShippingHouseNumberSuffix = '';
            $this->payment_request->ShippingPostalCode        = $point->postal_code;
            $this->payment_request->ShippingCity              = $point->city;
            $country                                          = $point->country;
        }

        $cocNumber =  Tools::getValue("customerbillink-coc");

        if (!empty($cocNumber) && strlen(trim($cocNumber)) !== 0) {
            $this->payment_request->CompanyCOCRegistration = $cocNumber;
        }
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
        $logger = new Logger(Logger::INFO, 'Billink');
        $logger->logInfo("Products", print_r($this->products));

        $products  = array();
        $taxvalues = Configuration::get('BUCKAROO_BILLINK_TAXRATE');
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
            $tmp["ArticleUnitPriceIncl"]   = round($item["price_with_reduction"], 2);
            $tmp["ArticleUnitPriceExcl"]   = round($item["price_with_reduction_without_tax"], 2);
            $tmp["ArticleVatcategory"] = $item["rate"];
            $products[] = $tmp;
        }

        $Wrapping = $this->cart->getOrderTotal(true, CartCore::ONLY_WRAPPING);
        if ($Wrapping > 0) {
            $tmp                       = array();
            $tmp["ArticleDescription"] = 'Wrapping';
            $tmp["ArticleId"]          = '0';
            $tmp["ArticleQuantity"]    = '1';
            $tmp["ArticleUnitPriceIncl"]   = $Wrapping;
            $tmp["ArticleUnitPriceExcl"]   = $Wrapping;
            $tmp["ArticleVatcategory"] = Configuration::get('BUCKAROO_BILLINK_WRAPPING_VAT');
            $products[]                = $tmp;
        }
        $this->payment_response = $this->payment_request->payBillink($products, $this->customVars);
    }

    protected function initialize()
    {
        $this->payment_request = PaymentRequestFactory::create(PaymentRequestFactory::REQUEST_TYPE_BILLINK);
    }
    /**
     * Check if company exists
     *
     * @param mixed $company
     *
     * @return bool
     */
    protected function companyExists($company)
    {
        if (!is_string($company)) {
            return false;
        }
        return strlen(trim($company)) !== 0;
    }
}
