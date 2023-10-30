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

class Buckaroo3AjaxModuleFrontController extends ModuleFrontController
{
    /**
     * @throws PrestaShopException
     * @throws \PrestaShop\Decimal\Exception\DivisionByZeroException
     * @throws \PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException
     */
    public function postProcess()
    {
        $currency = $this->context->currency;

        $locale = \Tools::getContextLocale($this->context);
        $action = Tools::getValue('action');

        if($action == 'getTotalCartPrice'){
          $this->getTotalCartPrice($locale,$currency);
        }
    }
    private function getTotalCartPrice($locale,$currency){
        //ToDo Refactor this
        $cart = $this->context->cart;
        $paymentFee = Tools::getValue('paymentFee');
        if (!$paymentFee) {
            $presentedCart = $this->cart_presenter->present($cart);
            $this->context->smarty->assign([
                'configuration' => $this->getTemplateVarConfiguration(),
                'cart' => $presentedCart,
                'display_transaction_updated_info' => Tools::getIsset('updatedTransaction'),
            ]);

            $this->ajaxRender(
                json_encode(
                    [
                        'cart_summary_totals' => $this->render('checkout/_partials/cart-summary-totals'),
                    ]
                )
            );
            exit;
        }

        $paymentFee = trim($paymentFee);
        $orderTotal = new DecimalNumber((string) $cart->getOrderTotal());

        if (strpos($paymentFee, '%') !== false) {
            $paymentFee = str_replace('%', '', $paymentFee);
            $paymentFee = new DecimalNumber((string) $paymentFee);
            $percentage = $paymentFee->dividedBy(new DecimalNumber('100'));
            $paymentFee = $orderTotal->times($percentage);
        } elseif ($paymentFee > 0) {
            // The fee is a flat amount.
            $paymentFee = new DecimalNumber((string) $paymentFee);
        }

        $buckarooFee = $locale->formatPrice($paymentFee->toPrecision(2), $currency->iso_code);

        $orderTotalWithFee = $orderTotal->plus($paymentFee);

        $orderTotalNoTax = new DecimalNumber((string) $cart->getOrderTotal(false));
        $orderTotalNoTaxWithFee = $orderTotalNoTax->plus($paymentFee);

        $total_including_tax = $orderTotalWithFee->toPrecision(2);
        $total_excluding_tax = $orderTotalNoTaxWithFee->toPrecision(2);

        $taxConfiguration = new TaxConfiguration();
        $presentedCart = $this->cart_presenter->present($this->context->cart);

        $presentedCart['totals'] = [
            'total' => [
                'type' => 'total',
                'label' => $this->translator->trans('Total', [], 'Shop.Theme.Checkout'),
                'amount' => $taxConfiguration->includeTaxes() ? $total_including_tax : $total_excluding_tax,
                'value' => $locale->formatPrice(
                    $taxConfiguration->includeTaxes() ? $total_including_tax : $total_excluding_tax, $currency->iso_code),
            ],
            'total_including_tax' => [
                'type' => 'total',
                'label' => $this->translator->trans('Total (tax incl.)', [], 'Shop.Theme.Checkout'),
                'amount' => $total_including_tax,
                'value' => $locale->formatPrice($total_including_tax, $currency->iso_code),
            ],
            'total_excluding_tax' => [
                'type' => 'total',
                'label' => $this->translator->trans('Total (tax excl.)', [], 'Shop.Theme.Checkout'),
                'amount' => $total_excluding_tax,
                'value' => $locale->formatPrice($total_excluding_tax, $currency->iso_code),
            ],
        ];

        $this->context->smarty->assign([
            'configuration' => $this->getTemplateVarConfiguration(),
            'cart' => $presentedCart,
            'display_transaction_updated_info' => Tools::getIsset('updatedTransaction'),
        ]);

        $this->ajaxRender(
            json_encode(
                [
                    'cart_summary_totals' => $this->render('checkout/_partials/cart-summary-totals'),
                    'paymentFee' => $buckarooFee,
                ]
            )
        );
    }
}
