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
include_once _PS_MODULE_DIR_ . 'buckaroo3/library/logger.php';

use Buckaroo\PrestaShop\Src\AddressComponents;
use Buckaroo\PrestaShop\Src\Entity\BkOrdering;
use Buckaroo\PrestaShop\Src\Entity\BkPaymentMethods;
use Buckaroo\PrestaShop\Src\Repository\RawGiftCardsRepository;
use Doctrine\ORM\EntityManager;
use PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use Buckaroo\PrestaShop\Src\Repository\RawCreditCardsRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}
class BuckarooPaymentService
{
    public $module;
    protected $logger;
    private $bkOrderingRepository;
    private $paymentMethodRepository;
    private $context;
    private BuckarooConfigService $buckarooConfigService;
    private BuckarooFeeService $buckarooFeeService;
    private $issuersPayByBank;
    private $capayableIn3;
    private $countryRepository;

    public function __construct(EntityManager $entityManager, $buckarooFeeService, $buckarooConfigService, $issuersPayByBank, $capayableIn3, $countryRepository)
    {
        $this->module = \Module::getInstanceByName('buckaroo3');
        $this->logger = new \Logger(\Logger::INFO, '');
        $this->context = \Context::getContext();
        $this->bkOrderingRepository = $entityManager->getRepository(BkOrdering::class);
        $this->paymentMethodRepository = $entityManager->getRepository(BkPaymentMethods::class);
        $this->buckarooFeeService = $buckarooFeeService;
        $this->buckarooConfigService = $buckarooConfigService;
        $this->issuersPayByBank = $issuersPayByBank;
        $this->capayableIn3 = $capayableIn3;
        $this->countryRepository = $countryRepository;
    }

    public function getPaymentOptions($cart)
    {
        $payment_options = [];
        libxml_use_internal_errors(true);
        $paymentMethods = $this->paymentMethodRepository->findAll();

        $isoCode2 = $this->context->country->iso_code;
        $country = $this->countryRepository->getCountryByIsoCode2($isoCode2);

        $activePaymentMethods = $this->paymentMethodRepository->getActivePaymentMethods($country['id']);
        $activeMethodIds = array_column($activePaymentMethods, 'id');

        $positions = $this->bkOrderingRepository->fetchPositions($country['id'], $activeMethodIds);

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

            if ($method == 'idin' && (!$this->module->isIdinCheckout($cart) || $this->isCustomerIdinValid($cart))) {
                continue;
            }

            if ($isMethodValid) {
                if ($method === 'creditcard' && $this->areCardsSeparate()) {
                    $payment_options = array_merge($payment_options, $this->getIndividualCards($method, $details));
                } elseif ($method === 'giftcard') {
                    $payment_options = array_merge($payment_options, $this->getIndividualGiftCards($method, $details));
                } else {
                    $payment_options[] = $this->createPaymentOption($method, $details);
                }
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

    private function getIndividualCards($method, $details): array
    {
        $configArray = $this->buckarooConfigService->getConfigArrayForMethod('creditcard');

        $methods = [];
        if (is_array($configArray['activeCreditcards']) && count($configArray['activeCreditcards']) > 0) {
            foreach ($configArray['activeCreditcards'] as $card) {
                if (array_key_exists('service_code', $card))
                    $methods[] = $this->getIndividualCard($method, $details, $card['service_code'], $configArray);
            }
        }
        return $methods;
    }

    private function getIndividualGiftCards($method, $details): array
    {
        $configArray = $this->buckarooConfigService->getConfigArrayForMethod('giftcard');

        $methods = [];
        if (is_array($configArray['activeGiftcards']) && count($configArray['activeGiftcards']) > 0) {
            foreach ($configArray['activeGiftcards'] as $cards) {
                foreach ($cards as $card){
                    if (array_key_exists('code', $card)) {
                        $methods[] = $this->getIndividualGiftCard($method, $details, $card['code'], $cards);
                    }
                }
            }
        }
        return $methods;
    }
    private function getIndividualCard($method, $details, $cardCode, $configArray)
    {
        $newOption = new PaymentOption();
        $cardData  = $this->getCardData($cardCode);

        $title = $this->getCardTitle($cardData['name'] ?? null, $configArray);

        if ($title === null) {
            $title = $this->getBuckarooLabel($method, $details->getLabel());
        }

        $newOption->setCallToActionText($title)
            ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => $method, 'cardCode' => $cardCode]))
            ->setModuleName($method);

        
        $newOption->setInputs($this->buckarooFeeService->getBuckarooFeeInputs($method));

        $logoPath = '/modules/buckaroo3/views/img/buckaroo/' . $this->getCardLogoPath($cardData['icon'] ?? null, $details);

        $newOption->setLogo($logoPath);

        return $newOption;
    }

    private function getIndividualGiftCard($method, $details, $cardCode, $configArray)
    {
        $newOption = new PaymentOption();
        $cardData = $this->getGiftCardData($cardCode);

        $title = $this->getCardTitle($cardData['name'] ?? null, $configArray);

        if ($title === null) {
            $title = $this->getBuckarooLabel($method, $details->getLabel());
        }

        if (!empty($details->getTemplate())) {
            $newOption->setForm($this->context->smarty->fetch('module:buckaroo3/views/templates/hook/' . $details->getTemplate()));
        } else {
            $newOption->setInputs($this->buckarooFeeService->getBuckarooFeeInputs($method));
        }

        $newOption->setCallToActionText($title)
            ->setAction($this->context->link->getModuleLink('buckaroo3', 'request', ['method' => $method, 'cardCode' => $cardCode]))
            ->setModuleName($method);


        $newOption->setInputs($this->buckarooFeeService->getBuckarooFeeInputs($method));

        $logoPath = '/modules/buckaroo3/views/img/buckaroo/' . $this->getGiftCardLogoPath($cardData);

        $newOption->setLogo($logoPath);

        return $newOption;
    }

    private function getCardTitle($title, $configArray)
    {
        if (is_string($title)) {
            $feeLabel = $this->getFeeLabel($configArray);

            return $this->module->l($title . $feeLabel);
        }
    }

    private function getCardLogoPath($logo, $details)
    {
        if (!is_string($logo)) {
            return "Payment methods/SVG/" . $details->getIcon();
        }
        return "Creditcard issuers/SVG/" . $logo;
    }

    private function getGiftCardLogoPath($cardData)
    {
        if ($cardData['is_custom']){
            return "Giftcards/SVG/BuckarooVoucher.svg";

        }
        return "Giftcards/SVG/" . $cardData['logo'];
    }

    private function getCardData(string $cardCode): ?array
    {
        $repo = new RawCreditCardsRepository();

        foreach ($repo->getCreditCardsData() as $cardData) {
            if ($cardData['service_code'] === $cardCode) {
                return $cardData;
            }
        }
    }

    private function getGiftCardData(string $cardCode): ?array
    {
        $repo = new RawGiftCardsRepository();
        foreach ($repo->getGiftCardsFromDB() as $cardData) {
            if ($cardData['code'] === $cardCode) {
                return $cardData;
            }
        }
    }


    private function areCardsSeparate(): bool
    {
        $configArray = $this->buckarooConfigService->getConfigArrayForMethod('creditcard');
        return ($configArray['display_in_checkout'] ?? "grouped") === "separate";
    }

    public function isCustomerIdinValid($cart)
    {
        $id_customer = (int) $cart->id_customer;

        $sql = new \DbQuery();
        $sql->select('buckaroo_idin_iseighteenorolder');
        $sql->from('bk_customer_idin');
        $sql->where('customer_id = ' . pSQL($id_customer));

        return \Db::getInstance()->getValue($sql) === 'True';
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

        $customerType = $this->buckarooConfigService->getConfigValue('afterpay', 'customer_type');

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
        $logoPath = '/modules/buckaroo3/views/img/buckaroo/Payment methods/SVG/';
        // Custom conditions for specific payment methods
        switch ($method) {
            case 'paybybank':
                $logoPath .= $this->issuersPayByBank->getSelectedIssuerLogo();
                break;
            case 'in3Old':
            case 'in3':
                $logoPath .= $this->capayableIn3->getLogo();
                break;
            case 'idin':
                $logoPath = '/modules/buckaroo3/views/img/buckaroo/Identification methods/SVG/' . $details->getIcon();
                break;
            default:
                $logoPath .= $details->getIcon();
                break;
        }
        $newOption->setLogo($logoPath);

        return $newOption;
    }

    public function getBuckarooLabel($method, $label)
    {
        if ($method == 'in3' && $this->capayableIn3->isV3()) {
            $label = 'iDEAL In3';
        }

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
        return (isset($configArray['frontend_label'])
            && $configArray['frontend_label'] !== '') ? $configArray['frontend_label'] : $defaultLabel;
    }

    /**
     * @throws LocalizationException
     * @throws \Exception
     */
    private function getFeeLabel($configArray)
    {
        $locale = \Tools::getContextLocale(\Context::getContext());
        $currency = \Context::getContext()->currency;

        if (isset($configArray['payment_fee']) && $configArray['payment_fee'] > 0) {
            return ' + ' . $locale->formatPrice($configArray['payment_fee'], $currency->iso_code);
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
        $afterpay_customer_type = $this->buckarooConfigService->getConfigValue('afterpay', 'customer_type');

        return $this->shouldShowCoc(
            $cart,
            $afterpay_customer_type,
            \AfterPayCheckout::CUSTOMER_TYPE_B2B,
            \AfterPayCheckout::CUSTOMER_TYPE_B2C
        );
    }

    public function showBillinkCoc($cart)
    {
        $billink_customer_type = $this->buckarooConfigService->getConfigValue('billink', 'customer_type');

        return $this->shouldShowCoc(
            $cart,
            $billink_customer_type,
            \BillinkCheckout::CUSTOMER_TYPE_B2B,
            \BillinkCheckout::CUSTOMER_TYPE_B2C);
    }

    public function areHouseNumberValidForCountryDE($cart) {
        list($billingAddress, $billingCountry, $shippingAddress, $shippingCountry) = $this->getAddressDetails($cart);
        return [
            "billing" =>$this->isHouseNumberValid($billingAddress) || $billingCountry !== 'DE',
            "shipping" => $this->isHouseNumberValid($shippingAddress) || $shippingCountry !== 'DE'
        ];
    }

    private function isHouseNumberValid($address) {
        if (is_string($address->address1)) {
            $address = AddressComponents::getAddressComponents($address->address1);
            return is_string($address['house_number']) && !empty(trim($address['house_number']));
        }
        return false;
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
     * @return \Address|void
     */
    protected function getAddressById($id)
    {
        if (is_scalar($id)) {
            return new \Address((int)$id);
        }
    }

    public function paymentMethodsWithFinancialWarning()
    {
        $buyNowPayLaterMethods = [
            'klarna',
            'afterpay',
            'billink',
            'in3',
        ];
        $methods = [];
        foreach ($buyNowPayLaterMethods as $method) {
            $methods[$method] = $this->buckarooConfigService->getConfigValue($method, 'financial_warning') ?? true;
        }
        $methods['warningText'] = 'Je moet minimaal 18+ zijn om deze dienst te gebruiken. Als je op tijd betaalt,
                voorkom je extra kosten en zorg je dat je in de toekomst nogmaals gebruik kunt
                maken van de diensten van %s. Door verder te gaan, accepteer je de Algemene
                Voorwaarden en bevestig je dat je de Privacyverklaring en Cookieverklaring hebt gelezen.';

        return $methods;
    }
}
