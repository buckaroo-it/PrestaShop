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

require_once dirname(__FILE__) . '/../../library/checkout/billinkcheckout.php';
require_once dirname(__FILE__) . '/../../library/checkout/afterpaycheckout.php';

use Buckaroo\PrestaShop\Src\Config\Config;
use Buckaroo\PrestaShop\Src\Entity\BkPaymentMethods;
use Doctrine\ORM\EntityManager;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

class BuckarooPaymentService
{
    private $orderingService;
    private $paymentMethodRepository;
    private $context;
    private BuckarooConfigService $buckarooConfigService;
    private BuckarooFeeService $buckarooFeeService;
    protected $logger;
    private $issuersPayByBank;
    private $capayableIn3;
    private $module;

    public function __construct(
        EntityManager $entityManager,
        $module,
        $buckarooConfigService,
        $issuersPayByBank,
        $logger,
        $context,
        $capayableIn3
    ) {
        $this->module = $module;
        $this->buckarooConfigService = $buckarooConfigService;
        $this->issuersPayByBank = $issuersPayByBank;
        $this->logger = $logger;
        $this->context = $context;
        $this->orderingService = $this->module->getService(BuckarooOrderingService::class);
        $this->capayableIn3 = $capayableIn3;
        $this->buckarooFeeService = $this->module->getService(BuckarooFeeService::class);
        $this->paymentMethodRepository = $entityManager->getRepository(BkPaymentMethods::class);
    }

    public function getPaymentOptions($cart)
    {
        $payment_options = [];
        libxml_use_internal_errors(true);
        $paymentMethods = $this->paymentMethodRepository->findAllPaymentMethods();

        $countryId = $this->context->country->id;
        $positions = $this->orderingService->getPositionByCountryId($countryId);

        $positions = array_flip($positions);

        foreach ($paymentMethods as $details) {
            $method = $details->getName();
            $isMethodValid = $this->module->isPaymentModeActive($method)
                && $this->isPaymentMethodAvailable($cart, $method)
                && isset($positions[$method])
                && !$this->isMethodUnavailableBySpecificConditions($cart, $method);

            if ($method == 'in3') {
                $method = $this->capayableIn3->getMethod();
            }

            if($method == 'idin') {
                if ($this->module->isIdinCheckout($cart)) {
                    if ($this->isCustomerIdinValid($cart)) {
                        continue;
                    }
                }else{
                    continue;
                }
            }

            if ($isMethodValid) {
                $payment_options[] = $this->createPaymentOption($method, $details);
            }
        }

        usort($payment_options, function ($a, $b) use ($positions) {
            $moduleNameA = $a->getModuleName() === 'in3Old' ? 'in3' : $a->getModuleName();
            $moduleNameB = $b->getModuleName() === 'in3Old' ? 'in3' : $b->getModuleName();

            $positionA = isset($positions[$moduleNameA]) ? $positions[$moduleNameA] : 0;
            $positionB = isset($positions[$moduleNameB]) ? $positions[$moduleNameB] : 0;

            return $positionA - $positionB;
        });

        return $payment_options;
    }

    public function isCustomerIdinValid($cart)
    {
        $id_customer = $cart->id_customer;
        $query = 'SELECT c.`buckaroo_idin_iseighteenorolder`'
            . ' FROM `' . _DB_PREFIX_ . 'customer` c '
            . ' WHERE c.id_customer = ' . (int) $id_customer;

        return \Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query) == 'True' ? true : false;
    }

    private function isMethodUnavailableBySpecificConditions($cart, $method)
    {
        switch ($method) {
            case 'in3':
                return !$this->isIn3Available($cart);
            case 'afterpay':
                return !$this->isAfterpayAvailable($cart);
            case 'applepay':
                return !(\Context::getContext()->isMobile());
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
     * @param float $cartTotal
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

        $newOption->setCallToActionText($this->getBuckarooLabel($method, $details->getLabel()))
            ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => $method]))
            ->setModuleName($method);

        // If template is set, use setForm, otherwise set inputs
        if (!empty($details->getTemplate())) {
            $newOption->setForm($this->context->smarty->fetch('module:buckaroo3/views/templates/hook/' . $details->getTemplate()));
        } else {
            $newOption->setInputs($this->buckarooFeeService->getBuckarooFeeInputs($method));
        }
        // Custom conditions for specific payment methods
        switch ($method) {
            case 'paybybank':
                $logoPath = '/modules/buckaroo3/views/img/buckaroo/Payment methods/SVG/' . $this->issuersPayByBank->getSelectedIssuerLogo();
                break;
            case 'in3Old':
            case 'in3':
                $logoPath = '/modules/buckaroo3/views/img/buckaroo/Payment methods/SVG/' . $this->capayableIn3->getLogo();
                break;
            case 'idin':
                $logoPath = '/modules/buckaroo3/views/img/buckaroo/Identification methods/SVG/' . $details->getIcon();
                break;
            default:
                $logoPath = '/modules/buckaroo3/views/img/buckaroo/Payment methods/SVG/' . $details->getIcon();
                break;
        }
        $newOption->setLogo($logoPath);

        return $newOption;
    }

    public function getBuckarooLabel($method, $label)
    {
        if ($method == 'in3Old') {
            $method = 'in3';
        }
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
            return ' + ' . \Tools::displayPrice($configArray['payment_fee'], $this->context->currency->id);
        }

        return '';
    }

    private function logError($message)
    {
        $this->logger->logInfo($message, 'error');
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
