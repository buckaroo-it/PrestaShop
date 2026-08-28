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
use Buckaroo\PrestaShop\Src\Service\BuckarooGroupTransactionService;
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
     * Backwards-compatible wrapper; prefer prepareOrderPaymentsBeforePaidStatus().
     */
    protected function recordAppliedGiftcardPayment(int $orderId): void
    {
        $this->prepareOrderPaymentsBeforePaidStatus($orderId);
        $this->clearGiftcardCookies();
    }

    protected function orderPaymentExists(string $orderReference, string $transactionKey): bool
    {
        if ($transactionKey === '') {
            return false;
        }

        $payments = OrderPayment::getByOrderReference($orderReference);
        if (!is_array($payments)) {
            return false;
        }

        foreach ($payments as $payment) {
            if ((string) $payment->transaction_id === $transactionKey) {
                return true;
            }
        }

        return false;
    }

    protected function recordGroupTransactionPayments(int $orderId): void
    {
        $order = new Order($orderId);
        if (!Validate::isLoadedObject($order) || (int) $order->id_cart <= 0) {
            return;
        }

        $groupTransactionService = new BuckarooGroupTransactionService();
        $groupTransactionService->linkOrderToCart((int) $order->id_cart, $orderId);

        foreach ($groupTransactionService->getGroupTransactionItems((int) $order->id_cart) as $row) {
            $transactionKey = (string) ($row['transaction_key'] ?? '');
            $amount = (float) ($row['amount'] ?? 0) - (float) ($row['refunded_amount'] ?? 0);

            if ($amount <= 0 || $transactionKey === '') {
                continue;
            }

            if ($this->orderPaymentExists($order->reference, $transactionKey)) {
                continue;
            }

            $payment                  = new OrderPayment();
            $payment->order_reference = $order->reference;
            $payment->id_currency     = $order->id_currency;
            $payment->conversion_rate = 1;
            $payment->amount          = $amount;
            $payment->payment_method  = (string) ($row['card_code'] ?: 'giftcard');
            $payment->transaction_id  = $transactionKey;
            $payment->save();
        }
    }

    protected function recordPushPayment(int $orderId, $response): bool
    {
        $transactionKey = (string) ($response->transactions ?? '');
        if ($transactionKey === '') {
            return false;
        }

        $order = new Order($orderId);
        if (!Validate::isLoadedObject($order)) {
            return false;
        }

        if ($this->orderPaymentExists($order->reference, $transactionKey)) {
            return false;
        }

        $amount = (float) urldecode((string) ($response->amount ?? 0));
        if ($amount <= 0) {
            return false;
        }

        $payment                  = new OrderPayment();
        $payment->order_reference = $order->reference;
        $payment->id_currency     = $order->id_currency;
        $payment->conversion_rate = 1;
        $payment->amount          = $amount;
        $payment->payment_method  = (string) ($response->payment_method ?? $order->payment);
        $payment->transaction_id  = $transactionKey;
        $payment->save();

        return true;
    }

    protected function syncOrderTotalPaidReal(int $orderId): void
    {
        $order = new Order($orderId);
        if (!Validate::isLoadedObject($order)) {
            return;
        }

        $payments = OrderPayment::getByOrderReference($order->reference);
        $sum = 0.0;
        if (is_array($payments)) {
            foreach ($payments as $payment) {
                $sum += (float) $payment->amount;
            }
        }

        $totalDue = (float) $order->total_paid;
        if ($totalDue > 0 && abs($sum - $totalDue) <= 0.005) {
            $order->total_paid_real = $totalDue;
        } else {
            $order->total_paid_real = $sum;
        }
        $order->save();
    }

    protected function prepareOrderPaymentsBeforePaidStatus(int $orderId, $response = null): void
    {
        $this->recordGroupTransactionPayments($orderId);

        if ($response !== null
            && method_exists($response, 'hasSucceeded')
            && $response->hasSucceeded()
        ) {
            $this->recordPushPayment($orderId, $response);
        }

        $this->syncOrderTotalPaidReal($orderId);
    }

    protected function isOrderFullyPaid(Order $order): bool
    {
        $epsilon = 0.005;
        $totalDue = (float) $order->total_paid;

        return $totalDue > 0 && ($this->getEffectivePaidAmount($order) + $epsilon >= $totalDue);
    }

    protected function getEffectivePaidAmount(Order $order): float
    {
        $totalPaidReal = (float) $order->total_paid_real;

        if ($order->id_cart <= 0) {
            return $totalPaidReal;
        }

        $groupTransactionService = new BuckarooGroupTransactionService();
        $groupPaid = $groupTransactionService->getAlreadyPaid((int) $order->id_cart);

        if ($groupPaid <= 0) {
            return $totalPaidReal;
        }

        // Giftcard amounts from group transactions may not yet be in total_paid_real
        // when the remainder push arrives before userreturn.
        if ($totalPaidReal + 0.005 < $groupPaid) {
            return $totalPaidReal + $groupPaid;
        }

        return $totalPaidReal;
    }

    protected function linkAllPaymentsToInvoice(int $orderId): void
    {
        $order = new Order($orderId);
        if (!Validate::isLoadedObject($order) || !$order->hasInvoice()) {
            return;
        }

        $invoices = $order->getInvoicesCollection();
        if (!$invoices || !count($invoices)) {
            return;
        }

        $invoice = $invoices[0];
        $invoiceId = (int) $invoice->id;
        $payments = OrderPayment::getByOrderReference($order->reference);
        if (!is_array($payments)) {
            return;
        }

        foreach ($payments as $payment) {
            $linked = (int) Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'order_invoice_payment`
                 WHERE `id_order_payment` = ' . (int) $payment->id . '
                 AND `id_order` = ' . (int) $orderId
            );
            if ($linked > 0) {
                continue;
            }

            Db::getInstance()->insert('order_invoice_payment', [
                'id_order_invoice' => $invoiceId,
                'id_order_payment' => (int) $payment->id,
                'id_order' => (int) $orderId,
            ]);
        }

        Cache::clean('order_invoice_paid_*');
    }

    protected function removeRedundantInvoicePayments(int $orderId): void
    {
        $order = new Order($orderId);
        if (!Validate::isLoadedObject($order)) {
            return;
        }

        $payments = OrderPayment::getByOrderReference($order->reference);
        if (!is_array($payments)) {
            return;
        }

        $totalDue = (float) $order->total_paid;
        $authenticatedSum = 0.0;

        foreach ($payments as $payment) {
            if ((string) $payment->transaction_id !== '') {
                $authenticatedSum += (float) $payment->amount;
            }
        }

        if ($totalDue <= 0 || $authenticatedSum + 0.005 < $totalDue) {
            return;
        }

        foreach ($payments as $payment) {
            if ((string) $payment->transaction_id !== '') {
                continue;
            }

            Db::getInstance()->delete(
                'order_invoice_payment',
                'id_order_payment = ' . (int) $payment->id
            );
            $payment->delete();
        }
    }

    protected function finalizeOrderPaymentsAfterStatusChange(int $orderId): void
    {
        $this->linkAllPaymentsToInvoice($orderId);
        $this->removeRedundantInvoicePayments($orderId);
        $this->syncOrderTotalPaidReal($orderId);
    }

    protected function updatePendingOrderStatus(int $orderId, int $newStatusCode, bool $sendEmail = false): bool
    {
        $order = new Order($orderId);
        if (!Validate::isLoadedObject($order)) {
            return false;
        }

        $currentState = (int) $order->getCurrentState();
        if ($currentState === $newStatusCode) {
            return false;
        }

        $pending = (int) Configuration::get('BUCKAROO_ORDER_STATE_DEFAULT');
        $canceled = (int) Configuration::get('BUCKAROO_ORDER_STATE_FAILED');
        $error = (int) Configuration::get('PS_OS_ERROR');
        $outofstockUnpaid = (int) Configuration::get('PS_OS_OUTOFSTOCK_UNPAID');

        if (!in_array($currentState, [$pending, $canceled, $error, $outofstockUnpaid], true)) {
            return false;
        }

        $history = new OrderHistory();
        $history->id_order = $orderId;
        $history->date_add = date('Y-m-d H:i:s');
        $history->date_upd = date('Y-m-d H:i:s');
        $history->changeIdOrderState($newStatusCode, $orderId, true);
        $history->addWithemail($sendEmail);

        $this->finalizeOrderPaymentsAfterStatusChange($orderId);

        return true;
    }

    protected function completeOrderIfFullyPaid(int $orderId, $response = null): bool
    {
        $this->prepareOrderPaymentsBeforePaidStatus($orderId, $response);

        $order = new Order($orderId);
        if (!Validate::isLoadedObject($order) || !$this->isOrderFullyPaid($order)) {
            return false;
        }

        $newStatus = (int) Buckaroo3::resolveStatusCode(BuckarooAbstract::BUCKAROO_SUCCESS, $orderId);

        return $this->updatePendingOrderStatus($orderId, $newStatus);
    }

    protected function markOrderPaidAfterSuccessfulReturn(int $orderId, $response = null): bool
    {
        $this->prepareOrderPaymentsBeforePaidStatus($orderId, $response);

        $newStatus = (int) Buckaroo3::resolveStatusCode(BuckarooAbstract::BUCKAROO_SUCCESS, $orderId);
        $updated = $this->updatePendingOrderStatus($orderId, $newStatus);

        if (!$updated) {
            $this->finalizeOrderPaymentsAfterStatusChange($orderId);
        }

        return $updated;
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
        unset($this->context->cookie->buckaroo_giftcard_cart_id);
        $this->context->cookie->write();
    }
}
