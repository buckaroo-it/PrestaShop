<?php
/**
* 2014-2015 Buckaroo.nl
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
*  @copyright 2014-2015 Buckaroo.nl
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/

class BuckarooCommonController extends ModuleFrontController
{

    private $id_order;

    protected function displayConfirmationTransfer($response)
    {
        $this->context = Context::getContext();

        $this->id_order = Order::getOrderByCartId($response->getCartId());
        $order = new Order($this->id_order);
        $message = '';
        if (!empty($response->consumerMessage['HtmlText'])) {
            $message = $response->consumerMessage['HtmlText'];
        }

        $this->context->smarty->assign(
            array(
                'is_guest' => (($this->context->customer->is_guest) || $this->context->customer->id == false),
                'order' => $order,
                'message' => $message,
            )
        );
        $this->setTemplate('order-confirmation-transfer.tpl');
    }

    protected function displayConfirmation($order_id)
    {
        $this->context = Context::getContext();

        $this->id_order = $order_id;
        $order = new Order($this->id_order);

        $price = $order->getOrdersTotalPaid();

        $this->context->smarty->assign(
            array(
                'is_guest' => (($this->context->customer->is_guest) || $this->context->customer->id == false),
                'order' => $order,
                'price' => Tools::displayPrice($price, $this->context->currency->id),
            )
        );
        $this->setTemplate('order-confirmation.tpl');
    }

    protected function displayError($invoicenumber = null, $error_message = null)
    {

        //if ($invoicenumber != null)
        if (is_null($error_message)) {
            $error_message = $this->module->l(
                'Your payment was unsuccessful. Please try again or choose another payment method.'
            );
        }
        $this->context->smarty->assign(
            array(
                'order_id' => $invoicenumber,
                'error_message' => $error_message,
            )
        );

        $this->setTemplate('error.tpl');
    }
}
