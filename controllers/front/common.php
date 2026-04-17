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

use Buckaroo\PrestaShop\Src\Container\ContainerAwareTrait;
use PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException;

if (!defined('_PS_VERSION_')) {
    exit;
}

class BuckarooCommonController extends ModuleFrontController
{
    use ContainerAwareTrait;
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

    /**
     * If a giftcard was applied before the current payment (partial coverage),
     * record it as an OrderPayment on the order and add the amount to total_paid_real.
     * Clears all giftcard session cookies afterwards.
     */
    protected function recordAppliedGiftcardPayment(int $orderId): void
    {
        $applied = (float) ($this->context->cookie->buckaroo_giftcard_applied ?? 0);
        $groupTx = (string) ($this->context->cookie->buckaroo_giftcard_group_tx ?? '');

        if ($applied <= 0 || empty($groupTx)) {
            return;
        }

        $order = new Order($orderId);
        if (!Validate::isLoadedObject($order)) {
            return;
        }

        $payment                  = new OrderPayment();
        $payment->order_reference = $order->reference;
        $payment->id_currency     = $order->id_currency;
        $payment->conversion_rate = 1;
        $payment->amount          = $applied;
        $payment->payment_method  = (string) ($this->context->cookie->buckaroo_giftcard_card_code ?: 'giftcard');
        $payment->transaction_id  = (string) ($this->context->cookie->buckaroo_giftcard_tx_key ?? '');
        $payment->save();

        $order->total_paid_real = (float) $order->total_paid_real + $applied;
        $order->save();

        $this->clearGiftcardCookies();
    }

    /**
     * Unset all giftcard partial-payment session cookies.
     */
    protected function clearGiftcardCookies(): void
    {
        unset($this->context->cookie->buckaroo_giftcard_group_tx);
        unset($this->context->cookie->buckaroo_giftcard_remainder);
        unset($this->context->cookie->buckaroo_giftcard_applied);
        unset($this->context->cookie->buckaroo_giftcard_tx_key);
        unset($this->context->cookie->buckaroo_giftcard_card_code);
        $this->context->cookie->write();
    }
}
