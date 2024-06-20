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
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Buckaroo3AjaxModuleFrontController extends ModuleFrontController
{
    /**
     * @throws PrestaShopException
     * @throws LocalizationException
     */
    public function postProcess()
    {
        $action = Tools::getValue('action');
        if ($action === 'getTotalCartPrice') {
            $this->calculateTotalWithPaymentFee();
        }
    }

    /**
     * @throws PrestaShopException
     * @throws Exception
     */
    private function renderCartSummary(Cart $cart, array $presentedCart = null)
    {
        $presentedCart = $presentedCart ?: $this->cart_presenter->present($cart);

        $this->context->smarty->assign([
            'configuration' => $this->getTemplateVarConfiguration(),
            'cart' => $presentedCart,
            'display_transaction_updated_info' => Tools::getIsset('updatedTransaction'),
        ]);

        $responseArray = [
            'cart_summary_totals' => $this->render('checkout/_partials/cart-summary-totals'),
            'paymentFee' => $presentedCart['totals']['paymentFee'] ?? null,
            'paymentFeeTax' => $presentedCart['totals']['paymentFeeTax'] ?? null,
            'includedTaxes' => $presentedCart['totals']['includedTaxes'] ?? null,
        ];

        $this->ajaxRender(json_encode($responseArray));
    }

    /**
     * @throws PrestaShopException
     * @throws LocalizationException
     * @throws Exception
     */
    private function calculateTotalWithPaymentFee()
    {
        $cart = $this->context->cart;
        $paymentFeeValue = trim(Tools::getValue('paymentFee'));

        if (!$paymentFeeValue) {
            $this->renderCartSummary($cart);
            return;
        }

        $paymentFee = $this->calculatePaymentFee($paymentFeeValue, $cart);
        $orderTotals = $this->calculateOrderTotals($cart, $paymentFee);

        $this->updatePresentedCart($cart, $orderTotals);
    }

    private function calculatePaymentFee($paymentFeeValue, $cart): DecimalNumber
    {
        $orderTotal = new DecimalNumber((string) $cart->getOrderTotal());

        if (strpos($paymentFeeValue, '%') !== false) {
            $paymentFeeValue = str_replace('%', '', $paymentFeeValue);
            $percentage = (new DecimalNumber((string) $paymentFeeValue))->dividedBy(new DecimalNumber('100'));
            return $orderTotal->times($percentage);
        }

        return new DecimalNumber($paymentFeeValue > 0 ? (string) $paymentFeeValue : '0');
    }

    private function calculateOrderTotals($cart, $paymentFee): array
    {
        $paymentFeeValue = (float) $paymentFee->toPrecision(2);
        $address = new Address($cart->id_address_invoice);
        $taxManager = TaxManagerFactory::getManager($address, (int) Configuration::get('PS_TAX'));
        $taxCalculator = $taxManager->getTaxCalculator();
        $taxRate = $taxCalculator->getTotalRate();
        $taxRateDecimal = $taxRate / 100;

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
                'total_including_tax' => $orderTotalWithFee->toPrecision(2),
                'total_excluding_tax' => $orderTotalNoTaxWithFee->toPrecision(2),
                'payment_fee' => $paymentFee->toPrecision(2),
                'payment_fee_tax' => $paymentFeeTax->toPrecision(2),
            ];
    }

    /**
     * @throws PrestaShopException
     * @throws LocalizationException
     * @throws Exception
     */
    private function updatePresentedCart($cart, $orderTotals)
    {
        $taxConfiguration = new TaxConfiguration();
        $presentedCart = $this->cart_presenter->present($cart);

        $buckarooFee = $this->formatPrice($orderTotals['payment_fee']);
        $paymentFeeTax = $this->formatPrice($orderTotals['payment_fee_tax']);
        $totalWithoutTax = new DecimalNumber((string) $cart->getOrderTotal(false, Cart::BOTH));
        $totalWithTax = new DecimalNumber((string) $cart->getOrderTotal(true, Cart::BOTH));
        $includedTaxes = $totalWithTax->minus($totalWithoutTax)->plus(new DecimalNumber($orderTotals['payment_fee_tax']))->toPrecision(2);

        $presentedCart['totals'] = $this->getTotalsArray($orderTotals, $buckarooFee, $paymentFeeTax, $includedTaxes);

        $this->renderCartSummary($cart, $presentedCart);
    }

    private function getTotalsArray($orderTotals, $buckarooFee, $paymentFeeTax, $includedTaxes): array
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
            'paymentFee' => $buckarooFee,
            'paymentFeeTax' => $paymentFeeTax,
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
        return $this->context->getCurrentLocale()->formatPrice($amount, $this->context->currency->iso_code);
    }
}