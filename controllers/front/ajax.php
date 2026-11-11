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
use Buckaroo\PrestaShop\Src\Service\BuckarooFeeCalculator;
use Buckaroo\PrestaShop\Src\Service\BuckarooGroupTransactionService;
use PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Buckaroo3AjaxModuleFrontController extends ModuleFrontController
{
    /** @var Buckaroo3 */
    public $module;

    public $ssl = true;
    public $ajax = true;
    public $content_only = true;

    /**
     * Drop any output buffered so far, so the response body is JSON only.
     */
    private function discardOutputBuffers(): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    }

    /**
     * @throws PrestaShopException
     * @throws LocalizationException
     */
    public function initContent()
    {
        $this->discardOutputBuffers();

        header('Content-Type: application/json;charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');

        try {
            parent::initContent();
        } catch (Exception $e) {
            // Ignore parent errors, we'll handle everything ourselves
        }

        $this->discardOutputBuffers();

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
        } catch (Exception $e) {
            if (class_exists('\PrestaShopLogger')) {
                PrestaShopLogger::addLog('Buckaroo3 Ajax Error: ' . $e->getMessage() . ' File: ' . $e->getFile() . ' Line: ' . $e->getLine(), 3);
            }
            $this->discardOutputBuffers();
            header('Content-Type: application/json;charset=utf-8');
            $this->ajaxRender(json_encode([
                'error' => true,
                'message' => 'Error processing request: ' . $e->getMessage(),
                'cart_summary_totals' => '',
                'paymentFee' => null,
                'paymentFeeTax' => null,
                'includedTaxes' => null,
            ]));
        } catch (Throwable $e) {
            // Catch any fatal errors
            if (class_exists('\PrestaShopLogger')) {
                PrestaShopLogger::addLog('Buckaroo3 Ajax Fatal Error: ' . $e->getMessage() . ' File: ' . $e->getFile() . ' Line: ' . $e->getLine() . ' Trace: ' . $e->getTraceAsString(), 3);
            }
            $this->discardOutputBuffers();
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
            'billing_phone' => $billingPhone,
        ]));
    }

    /**
     * @param Cart $cart
     * @param array|ArrayAccess|null $presentedCart Presented cart (an array or a lazy array, depending on the PrestaShop version)
     *
     * @throws PrestaShopException
     * @throws Exception
     */
    private function renderCartSummary(Cart $cart, $presentedCart = null)
    {
        try {
            $presentedCart = $presentedCart ?: $this->cart_presenter->present($cart);

            $this->context->smarty->assign([
                'configuration' => $this->getTemplateVarConfiguration(),
                'cart' => $presentedCart,
                'display_transaction_updated_info' => Tools::getIsset('updatedTransaction'),
            ]);

            $buckarooFees = $presentedCart['_buckarooFees'] ?? [];

            $groupTransactionService = new BuckarooGroupTransactionService();
            $cartTotal = (float) $cart->getOrderTotal(true, Cart::BOTH);
            $alreadyPaid = $groupTransactionService->getAlreadyPaid((int) $cart->id);

            $responseArray = [
                'cart_summary_totals' => $this->render('checkout/_partials/cart-summary-totals'),
                'paymentFee' => $buckarooFees['paymentFee'] ?? ($presentedCart['totals']['paymentFee'] ?? null),
                'paymentFeeTax' => $buckarooFees['paymentFeeTax'] ?? ($presentedCart['totals']['paymentFeeTax'] ?? null),
                'paymentFeeTotal' => $buckarooFees['paymentFeeTotal'] ?? ($presentedCart['totals']['paymentFeeTotal'] ?? null),
                'includedTaxes' => $presentedCart['totals']['includedTaxes'] ?? null,
                'alreadyPaid' => $alreadyPaid,
                'remainingAmount' => $groupTransactionService->getRemainingAmount((int) $cart->id, $cartTotal),
                'giftcardItems' => $groupTransactionService->getDisplayItems((int) $cart->id),
                'currencySign' => $this->context->currency ? $this->context->currency->sign : '',
            ];

            $this->ajaxRender(json_encode($responseArray));
        } catch (Exception $e) {
            if (class_exists('\PrestaShopLogger')) {
                PrestaShopLogger::addLog('Buckaroo3 Render Error: ' . $e->getMessage() . ' File: ' . $e->getFile() . ' Line: ' . $e->getLine() . ' Trace: ' . $e->getTraceAsString(), 3);
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
            if (!Validate::isLoadedObject($this->context->cart)) {
                throw new Exception('Cart is not available');
            }

            $cart = $this->context->cart;
            $paymentFee = $this->resolvePaymentFee($cart);

            if ($paymentFee === null) {
                $this->renderCartSummary($cart);

                return;
            }

            $this->updatePresentedCart($cart, $this->calculateOrderTotals($cart, $paymentFee));
        } catch (Exception $e) {
            // Log error and return empty response
            if (class_exists('\PrestaShopLogger')) {
                PrestaShopLogger::addLog('Buckaroo3 Ajax Error: ' . $e->getMessage() . ' File: ' . $e->getFile() . ' Line: ' . $e->getLine(), 3);
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

    /**
     * Calculates the fee for the selected payment method with the same code that adds it
     * to the order and to the Buckaroo transaction (Buckaroo3::getBuckarooFee()).
     *
     * The configured fee is read from the module settings when the payment method is
     * known; the posted fee value is only a fallback for themes that do not send it.
     *
     * @return array|null
     */
    private function resolvePaymentFee(Cart $cart)
    {
        $cartTotal = (float) $cart->getOrderTotal(true, Cart::BOTH);
        $paymentMethod = trim((string) Tools::getValue('paymentMethod'));

        if ($paymentMethod !== '' && preg_match('/^[a-z0-9_]+$/i', $paymentMethod)) {
            return $this->module->getBuckarooFee($paymentMethod, $cartTotal);
        }

        $paymentFeeValue = trim((string) Tools::getValue('paymentFee'));
        if ($paymentFeeValue === '') {
            return null;
        }

        return $this->module->calculateBuckarooFee($paymentFeeValue, $cartTotal);
    }

    /**
     * @param array $paymentFee result of Buckaroo3::getBuckarooFee()
     */
    private function calculateOrderTotals(Cart $cart, array $paymentFee): array
    {
        $precision = (int) $this->context->getComputingPrecision();
        $cartTotalTaxIncl = (float) $cart->getOrderTotal(true, Cart::BOTH);
        $cartTotalTaxExcl = (float) $cart->getOrderTotal(false, Cart::BOTH);

        return [
            'total_including_tax' => BuckarooFeeCalculator::addToTotal($cartTotalTaxIncl, $paymentFee, $precision),
            'total_excluding_tax' => BuckarooFeeCalculator::addToTotal($cartTotalTaxExcl, $paymentFee, $precision, false),
            'payment_fee' => (float) $paymentFee['buckaroo_fee_tax_excl'],
            'payment_fee_tax' => (float) $paymentFee['buckaroo_fee_tax'],
            'payment_fee_tax_incl' => (float) $paymentFee['buckaroo_fee_tax_incl'],
            'included_taxes' => (float) Tools::ps_round(
                $cartTotalTaxIncl - $cartTotalTaxExcl + (float) $paymentFee['buckaroo_fee_tax'],
                $precision
            ),
        ];
    }

    /**
     * @throws PrestaShopException
     * @throws LocalizationException
     * @throws Exception
     */
    private function updatePresentedCart($cart, $orderTotals)
    {
        try {
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
            $paymentFeeTotalFormatted = $this->formatPrice($orderTotals['payment_fee_tax_incl']);
            $paymentFeeDisplayValue = $hasPaymentFeeTax
                ? sprintf('%s+%s', $buckarooFee, $paymentFeeTax)
                : $buckarooFee;
            $includedTaxes = $orderTotals['included_taxes'];

            $existingTotals = isset($presentedCartArray['totals']) && is_array($presentedCartArray['totals'])
                ? $presentedCartArray['totals']
                : [];

            unset($existingTotals['paymentFee']);
            unset($existingTotals['paymentFeeTax']);

            if (!isset($presentedCartArray['subtotals']) || !is_array($presentedCartArray['subtotals'])) {
                $presentedCartArray['subtotals'] = [];
            }

            $presentedCartArray['subtotals'] = array_filter($presentedCartArray['subtotals'], function ($subtotal) {
                if (isset($subtotal['label'])) {
                    $label = is_array($subtotal['label'])
                        ? (isset($subtotal['label'][$this->context->language->id]) ? $subtotal['label'][$this->context->language->id] : '')
                        : (string) $subtotal['label'];
                    $labelLower = strtolower($label);
                    // Remove any subtotal that contains "payment fee" (case insensitive)
                    if (stripos($labelLower, 'payment fee') !== false) {
                        return false;
                    }
                }

                return true;
            }, ARRAY_FILTER_USE_BOTH);

            $paymentFeeLabel = Configuration::get(Config::PAYMENT_FEE_FRONTEND_LABEL) ?: 'Payment Fee';
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
        } catch (Exception $e) {
            if (class_exists('\PrestaShopLogger')) {
                PrestaShopLogger::addLog('Buckaroo3 UpdatePresentedCart Error: ' . $e->getMessage(), 3);
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
            'paymentFeeTotal' => $paymentFeeDisplayValue ?: $this->formatPrice($orderTotals['payment_fee_tax_incl']),
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
        } catch (Exception $e) {
            if (class_exists('\PrestaShopLogger')) {
                PrestaShopLogger::addLog('Buckaroo3 FormatPrice Error: ' . $e->getMessage() . ' Amount: ' . $amount, 3);
            }

            return '0';
        }
    }
}
