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

use Buckaroo\PrestaShop\Src\Config\Config;
use Buckaroo\PrestaShop\Src\Service\BuckarooGroupTransactionService;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Buckaroo3AjaxModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $ajax = true;
    public $content_only = true;

    /**
     * @throws PrestaShopException
     * @throws LocalizationException
     */
    public function initContent()
    {
        while (@ob_get_level()) {
            @ob_end_clean();
        }

        header('Content-Type: application/json;charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');

        try {
            parent::initContent();
        } catch (\Exception $e) {
            // Ignore parent errors, we'll handle everything ourselves
        }

        while (@ob_get_level()) {
            @ob_end_clean();
        }

        header('Content-Type: application/json;charset=utf-8');
        
        try {
            $action = Tools::getValue('action');
            if ($action === 'getTotalCartPrice') {
                $this->calculateTotalWithPaymentFee();
            } elseif ($action === 'getPhoneStatus') {
                $this->getPhoneStatus();
            } else {
                $this->ajaxRender(json_encode([
                    'error' => true,
                    'message' => 'Invalid action: ' . $action,
                    'cart_summary_totals' => '',
                    'paymentFee' => null,
                    'paymentFeeTax' => null,
                    'includedTaxes' => null,
                ]));
            }
        } catch (\Exception $e) {
            if (class_exists('\PrestaShopLogger')) {
                \PrestaShopLogger::addLog('Buckaroo3 Ajax Error: ' . $e->getMessage() . ' File: ' . $e->getFile() . ' Line: ' . $e->getLine(), 3);
            }
            while (@ob_get_level()) {
                @ob_end_clean();
            }
            header('Content-Type: application/json;charset=utf-8');
            $this->ajaxRender(json_encode([
                'error' => true,
                'message' => 'Error processing request: ' . $e->getMessage(),
                'cart_summary_totals' => '',
                'paymentFee' => null,
                'paymentFeeTax' => null,
                'includedTaxes' => null,
            ]));
        } catch (\Throwable $e) {
            // Catch any fatal errors
            if (class_exists('\PrestaShopLogger')) {
                \PrestaShopLogger::addLog('Buckaroo3 Ajax Fatal Error: ' . $e->getMessage() . ' File: ' . $e->getFile() . ' Line: ' . $e->getLine() . ' Trace: ' . $e->getTraceAsString(), 3);
            }
            while (@ob_get_level()) {
                @ob_end_clean();
            }
            header('Content-Type: application/json;charset=utf-8');
            $errorMessage = 'Fatal error processing request';
            if (defined('_PS_MODE_DEV_') && _PS_MODE_DEV_) {
                $errorMessage .= ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
            }
            $this->ajaxRender(json_encode([
                'error' => true,
                'message' => $errorMessage,
                'cart_summary_totals' => '',
                'paymentFee' => null,
                'paymentFeeTax' => null,
                'includedTaxes' => null,
            ]));
        }
    }

    /**
     * Returns the delivery and billing address phone numbers for the current cart.
     * Used by the frontend to dynamically show/hide BNPL phone fields.
     */
    private function getPhoneStatus()
    {
        $cart = $this->context->cart;
        if (!$cart || !Validate::isLoadedObject($cart)) {
            $this->ajaxRender(json_encode(['error' => true, 'delivery_phone' => '', 'billing_phone' => '']));
            return;
        }

        $customer = new Customer((int) $cart->id_customer);
        $idLang = (int) $this->context->language->id;
        $addresses = $customer->getAddresses($idLang);

        $phone = '';
        $phoneMobile = '';
        $phoneBilling = '';
        $phoneMobileBilling = '';

        foreach ($addresses as $address) {
            if ((int) $address['id_address'] === (int) $cart->id_address_delivery) {
                $phone = $address['phone'];
                $phoneMobile = $address['phone_mobile'];
            }
            if ((int) $address['id_address'] === (int) $cart->id_address_invoice) {
                $phoneBilling = $address['phone'];
                $phoneMobileBilling = $address['phone_mobile'];
            }
        }

        $deliveryPhone = !empty($phoneMobile) ? $phoneMobile : $phone;

        $billingPhone = '';
        if (!empty($phoneMobileBilling)) {
            $billingPhone = $phoneMobileBilling;
        } elseif (!empty($phoneBilling)) {
            $billingPhone = $phoneBilling;
        }

        $this->ajaxRender(json_encode([
            'delivery_phone' => $deliveryPhone,
            'billing_phone'  => $billingPhone,
        ]));
    }

    /**
     * @param Cart $cart
     * @param array|\PrestaShop\PrestaShop\Adapter\Presenter\Cart\CartLazyArray|null $presentedCart
     * @throws PrestaShopException
     * @throws Exception
     */
    private function renderCartSummary(Cart $cart, $presentedCart = null)
    {
        try {
            if (!isset($this->cart_presenter) || !$this->cart_presenter) {
                $this->get('prestashop.core.cart.cart_presenter');
            }

            if (!isset($this->cart_presenter) || !$this->cart_presenter) {
                if ($this->module && method_exists($this->module, 'getCoreServiceContainer')) {
                    $container = $this->module->getCoreServiceContainer();
                    if ($container && $container->has('prestashop.core.cart.cart_presenter')) {
                        $this->cart_presenter = $container->get('prestashop.core.cart.cart_presenter');
                    }
                }
            }
            
            if (!isset($this->cart_presenter) || !$this->cart_presenter) {
                throw new \Exception('Cart presenter is not available');
            }
            
            $presentedCart = $presentedCart ?: $this->cart_presenter->present($cart);

            $this->context->smarty->assign([
                'configuration' => $this->getTemplateVarConfiguration(),
                'cart' => $presentedCart,
                'display_transaction_updated_info' => Tools::getIsset('updatedTransaction'),
            ]);

            $buckarooFees = $presentedCart['_buckarooFees'] ?? [];

            $groupTransactionService = new BuckarooGroupTransactionService();
            $cartTotal   = (float) $cart->getOrderTotal(true, Cart::BOTH);
            $alreadyPaid = $groupTransactionService->getAlreadyPaid((int) $cart->id);

            $responseArray = [
                'cart_summary_totals' => $this->render('checkout/_partials/cart-summary-totals'),
                'paymentFee' => $buckarooFees['paymentFee'] ?? ($presentedCart['totals']['paymentFee'] ?? null),
                'paymentFeeTax' => $buckarooFees['paymentFeeTax'] ?? ($presentedCart['totals']['paymentFeeTax'] ?? null),
                'paymentFeeTotal' => $buckarooFees['paymentFeeTotal'] ?? ($presentedCart['totals']['paymentFeeTotal'] ?? null),
                'includedTaxes' => $presentedCart['totals']['includedTaxes'] ?? null,
                'alreadyPaid'     => $alreadyPaid,
                'remainingAmount' => $groupTransactionService->getRemainingAmount((int) $cart->id, $cartTotal),
                'giftcardItems'   => $groupTransactionService->getDisplayItems((int) $cart->id),
            ];

            $this->ajaxRender(json_encode($responseArray));
        } catch (\Exception $e) {
            if (class_exists('\PrestaShopLogger')) {
                \PrestaShopLogger::addLog('Buckaroo3 Render Error: ' . $e->getMessage() . ' File: ' . $e->getFile() . ' Line: ' . $e->getLine() . ' Trace: ' . $e->getTraceAsString(), 3);
            }
            $errorMessage = 'Error rendering cart summary';
            if (defined('_PS_MODE_DEV_') && _PS_MODE_DEV_) {
                $errorMessage .= ': ' . $e->getMessage();
            }
            $this->ajaxRender(json_encode([
                'error' => true,
                'message' => $errorMessage,
                'cart_summary_totals' => '',
                'paymentFee' => null,
                'paymentFeeTax' => null,
                'includedTaxes' => null,
            ]));
        }
    }

    /**
     * @throws PrestaShopException
     * @throws LocalizationException
     * @throws Exception
     */
    private function calculateTotalWithPaymentFee()
    {
        try {
            if (!$this->context || !$this->context->cart || !Validate::isLoadedObject($this->context->cart)) {
                throw new \Exception('Cart is not available');
            }
            
            $cart = $this->context->cart;
            $paymentFeeValue = trim(Tools::getValue('paymentFee'));

            if (!$paymentFeeValue || $paymentFeeValue === '') {
                $this->renderCartSummary($cart);
                return;
            }

            $paymentFee = $this->calculatePaymentFee($paymentFeeValue, $cart);
            $orderTotals = $this->calculateOrderTotals($cart, $paymentFee);

            $this->updatePresentedCart($cart, $orderTotals);
        } catch (\Exception $e) {
            // Log error and return empty response
            if (class_exists('\PrestaShopLogger')) {
                \PrestaShopLogger::addLog('Buckaroo3 Ajax Error: ' . $e->getMessage() . ' File: ' . $e->getFile() . ' Line: ' . $e->getLine(), 3);
            }
            $this->ajaxRender(json_encode([
                'error' => true,
                'message' => 'Error calculating payment fee: ' . $e->getMessage(),
                'cart_summary_totals' => '',
                'paymentFee' => null,
                'paymentFeeTax' => null,
                'includedTaxes' => null,
            ]));
        }
    }

    private function calculatePaymentFee($paymentFeeValue, $cart): ?DecimalNumber
    {
        $orderTotal = new DecimalNumber((string) $cart->getOrderTotal(true, Cart::BOTH));

        if (is_string($paymentFeeValue) && strpos(trim($paymentFeeValue), '%') !== false) {
            $normalizedValue = trim(str_replace('%', '', $paymentFeeValue));

            if (!is_numeric($normalizedValue)) {
                if (class_exists('\PrestaShopLogger')) {
                    \PrestaShopLogger::addLog('Buckaroo3: Invalid percentage fee value: ' . $paymentFeeValue, 3);
                }

                return new DecimalNumber('0');
            }

            $percentage = (new DecimalNumber((string) $normalizedValue))->dividedBy(new DecimalNumber('100'));

            return $orderTotal->times($percentage);
        }

        if (!is_numeric($paymentFeeValue)) {
            if (class_exists('\PrestaShopLogger')) {
                \PrestaShopLogger::addLog('Buckaroo3: Invalid fixed fee value: ' . $paymentFeeValue, 3);
            }

            return new DecimalNumber('0');
        }

        return new DecimalNumber($paymentFeeValue > 0 ? (string) $paymentFeeValue : '0');
    }

    private function calculateOrderTotals($cart, $paymentFee): array
    {
        try {
            $paymentFeeValue = (float) (string) $paymentFee->toPrecision(2);

            $addressId = $cart->id_address_invoice ?: $cart->id_address_delivery;
            $taxRate = 0;
            $taxRateDecimal = 0;
            
            if ($addressId) {
                try {
                    $address = new Address($addressId);
                    if (Validate::isLoadedObject($address)) {
                        $taxManager = TaxManagerFactory::getManager($address, (int) Configuration::get('PS_TAX'));
                        if ($taxManager) {
                            $taxCalculator = $taxManager->getTaxCalculator();
                            if ($taxCalculator) {
                                $taxRate = $taxCalculator->getTotalRate();
                                $taxRateDecimal = $taxRate / 100;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    if (class_exists('\PrestaShopLogger')) {
                        \PrestaShopLogger::addLog('Buckaroo3 Tax Calculation Error: ' . $e->getMessage(), 3);
                    }
                }
            }

        if (Configuration::get(Config::PAYMENT_FEE_MODE) === 'subtotal_incl_tax') {
            $baseFee = $paymentFeeValue / (1 + $taxRateDecimal);
            $taxFee = $paymentFeeValue - $baseFee;
            $orderTotalWithFee = (new DecimalNumber((string) $cart->getOrderTotal(true, Cart::BOTH)))->plus(new DecimalNumber((string) $paymentFee));
            $orderTotalNoTaxWithFee = (new DecimalNumber((string) $cart->getOrderTotal(false, Cart::BOTH)))->plus(new DecimalNumber((string) $paymentFee));
            $paymentFeeTax = new DecimalNumber((string) $taxFee);
            $paymentFee = new DecimalNumber((string) $baseFee);
        } else {
            $paymentFeeTaxAmount = $paymentFeeValue * $taxRateDecimal;
            $totalFeePriceTaxIncl = $paymentFeeValue + $paymentFeeTaxAmount;
            $paymentFeeTax = new DecimalNumber((string) $paymentFeeTaxAmount);
            $orderTotalWithFee = (new DecimalNumber((string) $cart->getOrderTotal(true, Cart::BOTH)))->plus(new DecimalNumber((string) $totalFeePriceTaxIncl));
            $orderTotalNoTaxWithFee = (new DecimalNumber((string) $cart->getOrderTotal(false, Cart::BOTH)))->plus($paymentFee);
        }

            return [
                'total_including_tax' => (string) $orderTotalWithFee->toPrecision(2),
                'total_excluding_tax' => (string) $orderTotalNoTaxWithFee->toPrecision(2),
                'payment_fee' => (string) $paymentFee->toPrecision(2),
                'payment_fee_tax' => (string) $paymentFeeTax->toPrecision(2),
            ];
        } catch (\Exception $e) {
            if (class_exists('\PrestaShopLogger')) {
                \PrestaShopLogger::addLog('Buckaroo3 CalculateOrderTotals Error: ' . $e->getMessage(), 3);
            }
            return [
                'total_including_tax' => (string) $cart->getOrderTotal(true, Cart::BOTH),
                'total_excluding_tax' => (string) $cart->getOrderTotal(false, Cart::BOTH),
                'payment_fee' => '0',
                'payment_fee_tax' => '0',
            ];
        }
    }

    /**
     * @throws PrestaShopException
     * @throws LocalizationException
     * @throws Exception
     */
    private function updatePresentedCart($cart, $orderTotals)
    {
        try {
            // Initialize cart_presenter if not available
            if (!isset($this->cart_presenter) || !$this->cart_presenter) {
                if ($this->module && method_exists($this->module, 'getCoreServiceContainer')) {
                    $container = $this->module->getCoreServiceContainer();
                    if ($container && $container->has('prestashop.core.cart.cart_presenter')) {
                        $this->cart_presenter = $container->get('prestashop.core.cart.cart_presenter');
                    }
                }
            }

            if (!isset($this->cart_presenter) || !$this->cart_presenter) {
                $this->cart_presenter = $this->get('prestashop.core.cart.cart_presenter');
            }
            
            if (!isset($this->cart_presenter) || !$this->cart_presenter) {
                throw new \Exception('Cart presenter is not available');
            }
            
            $presentedCart = $this->cart_presenter->present($cart);

            $presentedCartArray = [];
            if (is_array($presentedCart)) {
                $presentedCartArray = $presentedCart;
            } else {
                foreach ($presentedCart as $key => $value) {
                    $presentedCartArray[$key] = $value;
                }
            }

            $buckarooFee = $this->formatPrice($orderTotals['payment_fee']);
            $paymentFeeTax = $this->formatPrice($orderTotals['payment_fee_tax']);
            $hasPaymentFeeTax = (float) $orderTotals['payment_fee_tax'] > 0;
            // Calculate total fee including tax
            $paymentFeeTotal = (float) $orderTotals['payment_fee'] + (float) $orderTotals['payment_fee_tax'];
            $paymentFeeTotalFormatted = $this->formatPrice($paymentFeeTotal);
            $paymentFeeDisplayValue = $hasPaymentFeeTax
                ? sprintf('%s+%s', $buckarooFee, $paymentFeeTax)
                : $buckarooFee;
            
            $totalWithoutTax = new DecimalNumber((string) $cart->getOrderTotal(false, Cart::BOTH));
            $totalWithTax = new DecimalNumber((string) $cart->getOrderTotal(true, Cart::BOTH));
            $includedTaxes = (string) $totalWithTax->minus($totalWithoutTax)->plus(new DecimalNumber($orderTotals['payment_fee_tax']))->toPrecision(2);

            $existingTotals = isset($presentedCartArray['totals']) && is_array($presentedCartArray['totals']) 
                ? $presentedCartArray['totals'] 
                : [];

            unset($existingTotals['paymentFee']);
            unset($existingTotals['paymentFeeTax']);

            if (!isset($presentedCartArray['subtotals']) || !is_array($presentedCartArray['subtotals'])) {
                $presentedCartArray['subtotals'] = [];
            }

            $presentedCartArray['subtotals'] = array_filter($presentedCartArray['subtotals'], function($subtotal) {
                if (isset($subtotal['label'])) {
                    $label = is_array($subtotal['label']) 
                        ? (isset($subtotal['label'][$this->context->language->id]) ? $subtotal['label'][$this->context->language->id] : '') 
                        : (string)$subtotal['label'];
                    $labelLower = strtolower($label);
                    // Remove any subtotal that contains "payment fee" (case insensitive)
                    if (stripos($labelLower, 'payment fee') !== false) {
                        return false;
                    }
                }
                return true;
            }, ARRAY_FILTER_USE_BOTH);

            $paymentFeeLabel = Configuration::get('PAYMENT_FEE_FRONTEND_LABEL') ?: 'Payment Fee';
            $paymentFeeLabelWithSuffix = $paymentFeeLabel . ' (inc taxes)';
            $presentedCartArray['subtotals']['paymentFee'] = [
                'type' => 'paymentFee',
                'label' => $paymentFeeLabelWithSuffix,
                'amount' => $paymentFeeTotalFormatted,
                'value' => $paymentFeeDisplayValue,
            ];

            $presentedCartArray['_buckarooFees'] = [
                'paymentFee' => $buckarooFee,
                'paymentFeeTax' => $paymentFeeTax,
                'paymentFeeTotal' => $paymentFeeDisplayValue,
            ];

            $newTotals = $this->getTotalsArray($orderTotals, $buckarooFee, $paymentFeeTax, $includedTaxes, $paymentFeeDisplayValue);
            $presentedCartArray['totals'] = array_merge($existingTotals, $newTotals);

            $this->renderCartSummary($cart, $presentedCartArray);
        } catch (\Exception $e) {
            if (class_exists('\PrestaShopLogger')) {
                \PrestaShopLogger::addLog('Buckaroo3 UpdatePresentedCart Error: ' . $e->getMessage(), 3);
            }
            $this->renderCartSummary($cart);
        }
    }

    private function getTotalsArray($orderTotals, $buckarooFee, $paymentFeeTax, $includedTaxes, $paymentFeeDisplayValue = null): array
    {
        $totalsArray = [
            'total' => [
                'type' => 'total',
                'label' => $this->translator->trans('Total', [], 'Shop.Theme.Checkout'),
                'amount' => $orderTotals['total_including_tax'],
                'value' => $this->formatPrice($orderTotals['total_including_tax']),
            ],
            'total_including_tax' => [
                'type' => 'total',
                'label' => $this->translator->trans('Total (tax incl.)', [], 'Shop.Theme.Checkout'),
                'amount' => $orderTotals['total_including_tax'],
                'value' => $this->formatPrice($orderTotals['total_including_tax']),
            ],
            'total_excluding_tax' => [
                'type' => 'total',
                'label' => $this->translator->trans('Total (tax excl.)', [], 'Shop.Theme.Checkout'),
                'amount' => $orderTotals['total_excluding_tax'],
                'value' => $this->formatPrice($orderTotals['total_excluding_tax']),
            ],
            'paymentFeeTotal' => $paymentFeeDisplayValue ?: $this->formatPrice((float) $orderTotals['payment_fee'] + (float) $orderTotals['payment_fee_tax']),
        ];

        if (Configuration::get(Config::PAYMENT_FEE_MODE) === 'subtotal') {
            $totalsArray['includedTaxes'] = [
                'type' => 'tax',
                'label' => $this->translator->trans('Included taxes', [], 'Shop.Theme.Checkout'),
                'amount' => $includedTaxes,
                'value' => $this->formatPrice($includedTaxes),
            ];
        }

        return $totalsArray;
    }

    /**
     * @throws LocalizationException
     */
    private function formatPrice($amount): string
    {
        try {
            if (!is_numeric($amount)) {
                return '0';
            }
            return $this->context->getCurrentLocale()->formatPrice($amount, $this->context->currency->iso_code);
        } catch (\Exception $e) {
            if (class_exists('\PrestaShopLogger')) {
                \PrestaShopLogger::addLog('Buckaroo3 FormatPrice Error: ' . $e->getMessage() . ' Amount: ' . $amount, 3);
            }
            return '0';
        }
    }
}