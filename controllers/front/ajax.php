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

use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException;

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
        if (!$presentedCart) {
            $presentedCart = $this->cart_presenter->present($cart);
        }

        $this->context->smarty->assign([
            'configuration' => $this->getTemplateVarConfiguration(),
            'cart' => $presentedCart,
            'display_transaction_updated_info' => Tools::getIsset('updatedTransaction'),
        ]);

        $responseArray = [
            'cart_summary_totals' => $this->render('checkout/_partials/cart-summary-totals'),
        ];

        $paymentFee = $presentedCart['totals']['paymentFee'] ?? null;
        if (isset($paymentFee)) {
            $responseArray['paymentFee'] = $paymentFee;
        }

        $this->ajaxRender(json_encode($responseArray));
    }

    /**
     * @throws PrestaShopException
     * @throws LocalizationException
     */
    private function calculateTotalWithPaymentFee()
    {
        $cart = $this->context->cart;
        $paymentFeeValue = Tools::getValue('paymentFee');
        $paymentFeeValue = trim($paymentFeeValue);

        if (!$paymentFeeValue) {
            $this->renderCartSummary($cart);
            return;
        }

        $paymentFee = $this->calculatePaymentFee($paymentFeeValue, $cart);
        $orderTotals = $this->calculateOrderTotals($cart, $paymentFee);

        $this->updatePresentedCart($cart, $orderTotals, $paymentFee);
    }

    private function calculatePaymentFee($paymentFeeValue, $cart): DecimalNumber
    {
        $orderTotal = new DecimalNumber((string) $cart->getOrderTotal());

        if (strpos($paymentFeeValue, '%') !== false) {
            $paymentFeeValue = str_replace('%', '', $paymentFeeValue);
            $paymentFeeValue = new DecimalNumber((string) $paymentFeeValue);
            $percentage = $paymentFeeValue->dividedBy(new DecimalNumber('100'));
            return $orderTotal->times($percentage);
        } elseif ($paymentFeeValue > 0) {
            return new DecimalNumber((string) $paymentFeeValue);
        }

        return new DecimalNumber('0');
    }

    private function calculateOrderTotals($cart, $paymentFee): array
    {
        $orderTotalWithFee = (new DecimalNumber((string) $cart->getOrderTotal()))->plus($paymentFee);
        $orderTotalNoTaxWithFee = (new DecimalNumber((string) $cart->getOrderTotal(false)))->plus($paymentFee);

        return [
            'total_including_tax' => $orderTotalWithFee->toPrecision(2),
            'total_excluding_tax' => $orderTotalNoTaxWithFee->toPrecision(2),
        ];
    }

    /**
     * @throws PrestaShopException
     * @throws LocalizationException
     * @throws Exception
     */
    private function updatePresentedCart($cart, $orderTotals, $paymentFee)
    {
        $taxConfiguration = new TaxConfiguration();
        $presentedCart = $this->cart_presenter->present($cart);

        $buckarooFee = $this->formatPrice($paymentFee->toPrecision(2));
        $presentedCart['totals'] = [
            'total' => [
                'type' => 'total',
                'label' => $this->translator->trans('Total', [], 'Shop.Theme.Checkout'),
                'amount' => $taxConfiguration->includeTaxes() ? $orderTotals['total_including_tax'] : $orderTotals['total_excluding_tax'],
                'value' => $this->formatPrice($taxConfiguration->includeTaxes() ? $orderTotals['total_including_tax'] : $orderTotals['total_excluding_tax']),
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
        ];

        $this->renderCartSummary($cart, $presentedCart);
    }

    /**
     * @throws LocalizationException
     */
    private function formatPrice($amount): string
    {
        return $this->context->getCurrentLocale()->formatPrice($amount, $this->context->currency->iso_code);
    }

}