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

use PrestaShop\Decimal\Number;

class Buckaroo3AjaxModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $action = Tools::getValue('action');
        switch ($action) {
            case 'getTotalCartPrice':
                $cart = Context::getContext()->cart;
                $paymentFee = Tools::getValue('paymentFee');
                if (!$paymentFee) {
                    $presentedCart = $this->cart_presenter->present($this->context->cart);
                    $this->context->smarty->assign([
                        'configuration' => $this->getTemplateVarConfiguration(),
                        'cart' => $presentedCart,
                        'display_transaction_updated_info' => Tools::getIsset('updatedTransaction'),
                    ]);

                    $this->ajaxDie(
                        json_encode(
                            [
                                'cart_summary_totals' => $this->render('checkout/_partials/cart-summary-totals'),
                            ]
                        )
                    );
                }

                $paymentFee = trim($paymentFee);
                $orderTotal = new Number((string)$cart->getOrderTotal());

                if (strpos($paymentFee, '%') !== false) {
                    $paymentFee = str_replace('%', '', $paymentFee);
                    $paymentFee = new Number((string)$paymentFee);
                    $percentage = $paymentFee->dividedBy(new Number('100'));
                    $paymentFee = $orderTotal->times($percentage);
                } elseif ($paymentFee > 0) {
                    // The fee is a flat amount.
                    $paymentFee = new Number((string)$paymentFee);
                }
                $buckarooFee = Tools::displayPrice($paymentFee->toPrecision(2));

                $orderTotalWithFee = $orderTotal->plus($paymentFee);

                $orderTotalNoTax = new Number((string)$cart->getOrderTotal(false));
                $orderTotalNoTaxWithFee = $orderTotalNoTax->plus($paymentFee);

                $total_including_tax = $orderTotalWithFee->toPrecision(2);
                $total_excluding_tax = $orderTotalNoTaxWithFee->toPrecision(2);

                $taxConfiguration = new TaxConfiguration();
                $presentedCart = $this->cart_presenter->present($this->context->cart);

                $presentedCart['totals'] = array(
                    'total' => array(
                        'type' => 'total',
                        'label' => $this->translator->trans('Total', array(), 'Shop.Theme.Checkout'),
                        'amount' => $taxConfiguration->includeTaxes() ? $total_including_tax : $total_excluding_tax,
                        'value' => Tools::displayPrice(
                            $taxConfiguration->includeTaxes() ? $total_including_tax : $total_excluding_tax
                        ),
                    ),
                    'total_including_tax' => array(
                        'type' => 'total',
                        'label' => $this->translator->trans('Total (tax incl.)', array(), 'Shop.Theme.Checkout'),
                        'amount' => $total_including_tax,
                        'value' => Tools::displayPrice($total_including_tax),
                    ),
                    'total_excluding_tax' => array(
                        'type' => 'total',
                        'label' => $this->translator->trans('Total (tax excl.)', array(), 'Shop.Theme.Checkout'),
                        'amount' => $total_excluding_tax,
                        'value' => Tools::displayPrice($total_excluding_tax),
                    ),
                );

                $this->context->smarty->assign([
                    'configuration' => $this->getTemplateVarConfiguration(),
                    'cart' => $presentedCart,
                    'display_transaction_updated_info' => Tools::getIsset('updatedTransaction'),
                ]);

                $this->ajaxDie(
                    json_encode(
                        [
                            'cart_summary_totals' => $this->render('checkout/_partials/cart-summary-totals'),
                            'paymentFee' => $buckarooFee
                        ]
                    )
                );
                break;
            default:
        }
    }
}
