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

use PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException;

if (!defined('_PS_VERSION_')) {
    exit;
}

class BuckarooCommonController extends ModuleFrontController
{
    public function __construct()
    {
        parent::__construct();
    }
    private $id_order;

    protected function displayConfirmationTransfer($response)
    {
        $this->id_order = Order::getIdByCartId($response->getCartId());
        $order = new Order($this->id_order);
        $message = '';
        if (!empty($response->consumerMessage['HtmlText'])) {
            $message = $response->consumerMessage['HtmlText'];
        }

        $this->context->smarty->assign(
            [
                'is_guest' => ($this->context->customer->is_guest || $this->context->customer->id == false),
                'order' => $order,
                'message' => $message,
            ]
        );
        $this->setTemplate('order-confirmation-transfer.tpl');
    }

    /**
     * @throws PrestaShopException
     * @throws PrestaShopDatabaseException
     * @throws LocalizationException
     * @throws Exception
     */
    protected function displayConfirmation($order_id)
    {
        $currency = $this->context->currency;
        $locale = \Tools::getContextLocale($this->context);

        $this->id_order = $order_id;
        $order = new Order($this->id_order);

        $price = $order->getOrdersTotalPaid();

        $this->context->smarty->assign(
            [
                'is_guest' => ($this->context->customer->is_guest || $this->context->customer->id == false),
                'order' => $order,
                'price' => $locale->formatPrice($price, $currency->iso_code),
            ]
        );
        $this->setTemplate('order-confirmation.tpl');
    }

    protected function displayError($invoicenumber = null, $error_message = null)
    {
        if (is_null($error_message)) {
            $error_message = $this->module->l(
                'Your payment was unsuccessful. Please try again or choose another payment method.'
            );
        }
        $this->context->smarty->assign(
            [
                'order_id' => $invoicenumber,
                'error_message' => $error_message,
            ]
        );

        $this->setTemplate('module:buckaroo3/views/templates/front/error.tpl');
    }

    /**
     * Method to initialize content, should be overridden in child classes
     */
    public function initContent()
    {
        parent::initContent();
    }
}
