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

namespace Buckaroo\PrestaShop\Src\Service;

require_once _PS_MODULE_DIR_ . 'buckaroo3/library/logger.php';
require_once dirname(__FILE__) . '/../../library/checkout/billinkcheckout.php';
require_once dirname(__FILE__) . '/../../library/checkout/afterpaycheckout.php';

use Buckaroo\PrestaShop\Classes\CapayableIn3;
use Buckaroo\PrestaShop\Src\Repository\OrderingRepository;
use Buckaroo\PrestaShop\Src\Repository\PaymentMethodRepository;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

class BuckarooPaymentService
{
    private $orderingRepository;

    private $paymentMethodRepository;
    private $context;
    private BuckarooConfigService $buckarooConfigService;
    private BuckarooFeeService $buckarooFeeService;
    protected $logger;
    private $issuersPayByBank;
    private $capayableIn3;
    private $module;

    public function __construct(
        $buckarooConfigService,
        $buckarooFeeService,
        $issuersPayByBank,
        $logger,
        $context,
        $module
    ) {
        $this->orderingRepository = new OrderingRepository();
        $this->paymentMethodRepository = new PaymentMethodRepository();
        $this->buckarooConfigService = $buckarooConfigService;
        $this->buckarooFeeService = $buckarooFeeService;
        $this->issuersPayByBank = $issuersPayByBank;
        $this->capayableIn3 = new CapayableIn3();
        $this->logger = $logger;
        $this->context = $context;
        $this->module = $module;
    }

    public function getPaymentOptions($cart)
    {
        $payment_options = [];
        libxml_use_internal_errors(true);
        $paymentMethods = $this->paymentMethodRepository->fetchAllPaymentMethods();

        $countryId = $this->context->country->id;
        $positions = $this->orderingRepository->getPositionByCountryId($countryId);
        $positions = array_flip($positions);

        foreach ($paymentMethods as $details) {
            $method = $details['name'];

            $isMethodValid = $this->isPaymentModeActive($method)
                && $this->isPaymentMethodAvailable($cart, $method)
                && isset($positions[$method])
                && !$this->isMethodUnavailableBySpecificConditions($cart, $method);

            if ($method == 'in3') {
                $method = $this->capayableIn3->getMethod();
            }

            if ($isMethodValid) {
                $payment_options[] = $this->createPaymentOption($method, $details);
            }
        }

        usort($payment_options, function ($a, $b) use ($positions) {
            $positionA = isset($positions[$a->getModuleName()]) ? $positions[$a->getModuleName()] : 0;
            $positionB = isset($positions[$b->getModuleName()]) ? $positions[$b->getModuleName()] : 0;

            return $positionA - $positionB;
        });

        return $payment_options;
    }

    private function isMethodUnavailableBySpecificConditions($cart, $method)
    {
        switch ($method) {
            case 'in3':
                return !$this->isIn3Available($cart);
            case 'afterpay':
                return !$this->isAfterpayAvailable($cart);
            default:
                return false;
        }
    }

    /**
     * Check if afterpay available
     *
     * @param Cart $cart
     *
     * @return bool
     */
    protected function isAfterpayAvailable($cart)
    {
        $idAddressInvoice = $cart->id_address_invoice !== 0 ? $cart->id_address_invoice : $cart->id_address_delivery;
        $billingAddress = $this->getAddressById($idAddressInvoice);
        $billingCountry = null;
        if ($billingAddress !== null) {
            $billingCountry = \Country::getIsoById($billingAddress->id_country);
        }

        $shippingAddress = $this->getAddressById($cart->id_address_delivery);
        $shippingCountry = null;
        if ($shippingAddress !== null) {
            $shippingCountry = \Country::getIsoById($shippingAddress->id_country);
        }

        $customerType = $this->buckarooConfigService->getSpecificValueFromConfig('afterpay', 'customer_type');

        if (\AfterPayCheckout::CUSTOMER_TYPE_B2C !== $customerType) {
            $nlCompanyExists =
                ($this->companyExists($shippingAddress) && $shippingCountry === 'NL')
                || ($this->companyExists($billingAddress) && $billingCountry === 'NL');
            if (\AfterPayCheckout::CUSTOMER_TYPE_B2B === $customerType) {
                return $this->isAvailableByAmount($cart->getOrderTotal(true, 3), 'AFTERPAY') && $nlCompanyExists;
            }

            // both customer types & a company is filled show if available b2b by amount
            if ($nlCompanyExists) {
                return $this->isAvailableByAmount($cart->getOrderTotal(true, 3), 'AFTERPAY');
            }
        }

        return true;
    }

    /**
     * Check if company exists
     *
     * @param Address|null $address
     *
     * @return bool
     */
    protected function companyExists($address)
    {
        if ($address === null) {
            return false;
        }

        return strlen(trim($address->company)) !== 0;
    }

    /**
     * Check if payment method available
     *
     * @param Cart $cart
     *
     * @return bool
     */
    protected function isPaymentMethodAvailable($cart, $paymentMethod)
    {
        // Check if payment method is available by amount
        return $this->isAvailableByAmount($cart->getOrderTotal(true, 3), $paymentMethod);
    }

    /**
     * Check if payment is available by amount
     *
     * @param float  $cartTotal
     * @param string $paymentMethod
     *
     * @return bool
     */
    public function isAvailableByAmount(float $cartTotal, $paymentMethod)
    {
        $configArray = $this->buckarooConfigService->getConfigArrayForMethod($paymentMethod);

        if ($configArray === null) {
            return false;
        }

        $minAmount = isset($configArray['min_order_amount']) ? (float) $configArray['min_order_amount'] : 0;
        $maxAmount = isset($configArray['max_order_amount']) ? (float) $configArray['max_order_amount'] : 0;

        if ($minAmount == 0 && $maxAmount == 0) {
            return true;
        }

        return ($minAmount == 0 || $cartTotal >= $minAmount) && ($maxAmount == 0 || $cartTotal <= $maxAmount);
    }

    private function createPaymentOption($method, $details)
    {
        $newOption = new PaymentOption();
        $newOption->setCallToActionText($this->getBuckarooLabel($method, $details['label']))
            ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => $method]))
            ->setModuleName($method);

        // If template is set, use setForm, otherwise set inputs
        if (!empty($details['template'])) {
            $newOption->setForm($this->context->smarty->fetch('module:buckaroo3/views/templates/hook/' . $details['template']));
        } else {
            $newOption->setInputs($this->buckarooFeeService->getBuckarooFeeInputs($method));
        }

        // Custom conditions for specific payment methods
        switch ($method) {
            case 'paybybank':
                $logoPath = '/modules/buckaroo3/views/img/buckaroo/Payment methods/SVG/' . $this->issuersPayByBank->getSelectedIssuerLogo();
                break;
            case 'in3':
                $logoPath = '/modules/buckaroo3/views/img/buckaroo/Payment methods/SVG/' . $this->capayableIn3->getLogo();
                break;
            default:
                $logoPath = '/modules/buckaroo3/views/img/buckaroo/Payment methods/SVG/' . $details['icon'];
                break;
        }

        $newOption->setLogo($logoPath);

        return $newOption;
    }

    public function getBuckarooLabel($method, $label)
    {
        $configArray = $this->buckarooConfigService->getConfigArrayForMethod($method);
        if ($configArray === null) {
            $this->logError('JSON decode error: ' . json_last_error_msg());

            return null;
        }

        $label = $this->getLabel($configArray, $label);
        $feeLabel = $this->getFeeLabel($configArray);

        return $this->module->l($label . $feeLabel);
    }

    private function getLabel($configArray, $defaultLabel)
    {
        return (isset($configArray['frontend_label']) && $configArray['frontend_label'] !== '') ? $configArray['frontend_label'] : $defaultLabel;
    }

    private function getFeeLabel($configArray)
    {
        if (isset($configArray['payment_fee']) && $configArray['payment_fee'] > 0) {
            return ' + ' . Tools::displayPrice($configArray['payment_fee'], $this->context->currency->id);
        }

        return '';
    }

    private function logError($message)
    {
        $this->logger->logInfo($message, 'error');
    }

    public function isPaymentModeActive($method)
    {
        $configArray = $this->buckarooConfigService->getConfigArrayForMethod($method);
        if ($configArray === null) {
            return false;
        }

        return isset($configArray['mode']) && in_array($configArray['mode'], ['live', 'test']);
    }

    /**
     * Is in3 available
     *
     * @param Cart $cart
     *
     * @return bool
     */
    protected function isIn3Available($cart)
    {
        return $this->getBillingCountryIso($cart) === 'NL';
    }

    /**
     * Get billing country iso from cart
     *
     * @param Cart $cart
     *
     * @return string|null
     */
    protected function getBillingCountryIso($cart)
    {
        $idAddressInvoice = $cart->id_address_invoice !== 0 ? $cart->id_address_invoice : $cart->id_address_delivery;
        $billingAddress = $this->getAddressById((int) $idAddressInvoice);

        if ($billingAddress !== null) {
            return \Country::getIsoById($billingAddress->id_country);
        }
    }

    public function showAfterpayCoc($cart)
    {
        $afterpay_customer_type = $this->buckarooConfigService->getSpecificValueFromConfig('afterpay', 'customer_type');

        return $this->shouldShowCoc($cart, $afterpay_customer_type, \AfterPayCheckout::CUSTOMER_TYPE_B2B, \AfterPayCheckout::CUSTOMER_TYPE_B2C);
    }

    public function showBillinkCoc($cart)
    {
        $billink_customer_type = $this->buckarooConfigService->getSpecificValueFromConfig('billink', 'customer_type');

        return $this->shouldShowCoc($cart, $billink_customer_type, \BillinkCheckout::CUSTOMER_TYPE_B2B, \BillinkCheckout::CUSTOMER_TYPE_B2C);
    }

    private function shouldShowCoc($cart, $customer_type, $typeB2B, $typeB2C)
    {
        list($billingAddress, $billingCountry, $shippingAddress, $shippingCountry) = $this->getAddressDetails($cart);

        return $typeB2B === $customer_type
            || (
                $typeB2C !== $customer_type
                && (
                    ($this->companyExists($shippingAddress) && $shippingCountry === 'NL')
                    || ($this->companyExists($billingAddress) && $billingCountry === 'NL')
                )
            );
    }

    private function getAddressDetails($cart)
    {
        $idAddressInvoice = $cart->id_address_invoice !== 0 ? $cart->id_address_invoice : $cart->id_address_delivery;
        $billingAddress = $this->getAddressById($idAddressInvoice);
        $billingCountry = $billingAddress ? \Country::getIsoById($billingAddress->id_country) : null;

        $shippingAddress = $this->getAddressById($cart->id_address_delivery);
        $shippingCountry = $shippingAddress ? \Country::getIsoById($shippingAddress->id_country) : null;

        return [$billingAddress, $billingCountry, $shippingAddress, $shippingCountry];
    }

    /**
     * Get address by id
     *
     * @param mixed $id
     *
     * @return \Address|null
     */
    protected function getAddressById($id)
    {
        if (!is_int($id)) {
            return;
        }

        return new \Address($id);
    }
}