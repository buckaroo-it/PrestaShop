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

use Buckaroo\PrestaShop\Src\Repository\RawBuckarooFeeRepository;
use Buckaroo\PrestaShop\Src\Service\BuckarooGroupTransactionService;

include_once __DIR__ . '/../../api/paymentmethods/responsefactory.php';
include_once __DIR__ . '/../../library/logger.php';
include_once __DIR__ . '/common.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

class Buckaroo3ReturnModuleFrontController extends BuckarooCommonController
{
    public $ssl = true;
    protected $logger;
    private RawBuckarooFeeRepository $buckarooFeeRepository;

    public function __construct()
    {
        parent::__construct();
        $this->logger = new Logger(Logger::INFO, 'return');
        $this->buckarooFeeRepository = $this->resolveBuckarooFeeRepository();
    }

    private function resolveBuckarooFeeRepository(): RawBuckarooFeeRepository
    {
        if ($this->hasService('buckaroo.repository.raw_buckaroo_fee')) {
            return $this->getService('buckaroo.repository.raw_buckaroo_fee');
        }

        return new RawBuckarooFeeRepository();
    }

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        $this->display_column_left = false;
        $this->display_column_right = false;
        $this->logger->logInfo("\n\n\n\n***************** Return start ***********************");

        parent::initContent();

        $statuses = [];
        $tmp = OrderState::getOrderStates(1);
        foreach ($tmp as $stat) {
            $statuses[$stat['id_order_state']] = $stat['name'];
        }

        $response = ResponseFactory::getResponse();
        $this->logger->logInfo('Parse response', $response);

        $isRefundPush =
            Tools::getIsset('brq_amount_credit')
            && Tools::getIsset('brq_relatedtransaction_refund');

        if ($response->isValid() || $isRefundPush) {
            if (!$response->isValid() && $isRefundPush) {
                $this->logger->logWarn('Refund push detected and processed despite failed validation');
            }
            $this->logger->logInfo('Response valid');
            if (!empty($response->payment_method)
                && ($response->payment_method == 'paypal')
                && !empty($response->statuscode)
                && ($response->statuscode == $response::BUCKAROO_STATUSCODE_PENDING_PROCESSING)
            ) {
                $response->statuscode = $response::BUCKAROO_STATUSCODE_CANCELLED_BY_USER;
                $response->status = $response::BUCKAROO_CANCELED;
            }

            $id_order = Order::getIdByCartId($response->getCartId());
            $orders = Order::getByReference($response->getReferenceId());
            $references = [];
            foreach ($orders as $order) {
                $row = get_object_vars($order);
                $references[] = $row['reference'];
            }

            $this->logger->logInfo('Get order by cart id', 'Order ID: ' . $id_order);

            if ($response->brq_relatedtransaction_partialpayment != null) {
                $this->logger->logInfo('PUSH', 'Partial payment PUSH received ' . $response->status);

                // Confirm the gift card group-transaction row in the DB regardless of whether
                // the order exists yet (the push may arrive before validateOrder completes).
                $groupTransactionService = new BuckarooGroupTransactionService();
                if ($response->hasSucceeded()) {
                    $groupTransactionService->updateGroupTransactionStatus(
                        (string) $response->transactions,
                        190
                    );
                } else {
                    $groupTransactionService->updateGroupTransactionStatus(
                        (string) $response->transactions,
                        (int) $response->status
                    );
                }

                if ($id_order && $response->hasSucceeded()) {
                    $order = new Order($id_order);
                    $order->setInvoice(false);
                    $payment = new OrderPayment();
                    $payment->order_reference = $order->reference;
                    $payment->id_currency = $order->id_currency;
                    $payment->transaction_id = $response->transactions;
                    $payment->amount = urldecode($response->amount);
                    $payment->payment_method = $response->payment_method;
                    $order->total_paid_real += $response->amount;
                    $order->save();
                    $payment->conversion_rate = 1;
                    $payment->save();
                    Db::getInstance()->execute(
                        '
                        INSERT INTO `' . _DB_PREFIX_ . 'order_invoice_payment`
                        VALUES(' . (int)$order->invoice_number . ', ' . (int)$payment->id . ', ' . (int)$order->id . ')'
                    );

                    // Link group-transaction rows to the order if not already done
                    if ($order->id_cart) {
                        $groupTransactionService->linkOrderToCart((int) $order->id_cart, (int) $order->id);
                    }

                    $message = new Message();
                    $message->id_order = $id_order;
                    $message->message = 'Buckaroo partial payment message (' . $response->transactions . '): ' . $response->statusmessage;
                    $message->add();
                }
                exit;
            }

            if ($response->brq_relatedtransaction_refund != null) {
                $order = $id_order ? new Order($id_order) : null;
                $this->handleRefundPush($order, $response);
                exit;
            }

            if (!$id_order) {
                header('HTTP/1.1 503 Service Unavailable');
                echo 'Order does not exist';
                $this->logger->logError('PUSH', 'Order does not exist');
                exit;
            } else {
                $this->logger->logInfo('Update the order', 'Order ID: ' . $id_order);

                $new_status_code = (int) Buckaroo3::resolveStatusCode($response->status, $id_order);
                $order = new Order($id_order);

                // Validate that the resolved order state actually exists in this shop.
                if (!$this->isValidOrderStateId($new_status_code)) {
                    $this->logger->logError(
                        sprintf(
                            'Resolved order state id %d is invalid for order %d; status change skipped',
                            $new_status_code,
                            $id_order
                        )
                    );
                    $new_status_code = (int) $order->getCurrentState();
                }

                if (!in_array($order->reference, $references)) {
                    header('HTTP/1.1 503 Service Unavailable');
                    $this->logger->logError('Order not in reference ' . $order->reference);
                    echo 'Order not in reference: ' . $order->reference;
                    exit;
                }

                $this->logger->logInfo(
                    'Old order status code: ' . $order->getCurrentState() . '; new order status code: ' . $new_status_code
                );

                $pending = Configuration::get('BUCKAROO_ORDER_STATE_DEFAULT');
                $canceled = Configuration::get('BUCKAROO_ORDER_STATE_FAILED');
                $error = Configuration::get('PS_OS_ERROR');
                $outofstock_unpaid = Configuration::get('PS_OS_OUTOFSTOCK_UNPAID');

                if ($new_status_code != $order->getCurrentState()
                    && ($pending == $order->getCurrentState() || $canceled == $order->getCurrentState()
                        || $error == $order->getCurrentState() || $outofstock_unpaid == $order->getCurrentState())
                ) {
                    $this->logger->logInfo('Update order status');
                    $history = new OrderHistory();
                    $history->id_order = $id_order;
                    $history->date_add = date('Y-m-d H:i:s');
                    $history->date_upd = date('Y-m-d H:i:s');
                    $history->changeIdOrderState($new_status_code, $id_order);
                    $history->addWithemail(false);

                    $payments = OrderPayment::getByOrderReference($order->reference);
                    foreach ($payments as $payment) {
                        if ($payment->payment_method == 'Group transaction') {
                            $payment->amount = 0;
                            $payment->update();
                        }
                        if ($payment->amount == $response->amount && $payment->transaction_id == '') {
                            $payment->transaction_id = $response->transactions;
                            $payment->update();
                        }
                    }
                } else {
                    $this->logger->logInfo('Order status not updated');
                }

                $statusCodeName = $new_status_code;
                if (!empty($statuses[$new_status_code])) {
                    $statusCodeName = $statuses[$new_status_code];
                }

                $message = new Message();
                $message->id_order = $id_order;
                $message->message = 'Push message received. Buckaroo status: ' . $statusCodeName . '. Transaction key: ' . $response->transactions;
                $message->add();

                if ($response->statusmessage) {
                    $message = new Message();
                    $message->id_order = $id_order;
                    $message->message = 'Buckaroo message: ' . $response->statusmessage;
                    $message->add();
                }
            }
        } else {
            header('HTTP/1.1 503 Service Unavailable');
            $this->logger->logError('Payment response not valid', $response);
            echo 'Payment response not valid';
            exit;
        }

        $buckarooFee = $this->buckarooFeeRepository->getFeeByOrderId($order->id);

        if ($buckarooFee && (isset($payment) && $payment->payment_method != 'Group transaction')) {
            $jj = 0;
            foreach ($payments as $payment) {
                if ($jj > 0) {
                    continue;
                }
                if ($payment->amount != $response->amount && $payment->transaction_id == '') {
                    $payment->amount = $response->amount;
                    $payment->transaction_id = $response->transactions;
                    $payment->update();
                    ++$jj;
                }
            }
        }

        exit;
    }

    /**
     * Check whether an order state id is valid and available in this shop.
     *
     * This is important on upgraded shops (including PS 9.x) where custom
     * states may have been removed or not migrated correctly.
     *
     * @param int $id_order_state
     *
     * @return bool
     */
    private function isValidOrderStateId($id_order_state)
    {
        $id_order_state = (int) $id_order_state;
        if ($id_order_state <= 0) {
            return false;
        }

        $state = new OrderState($id_order_state);

        return Validate::isLoadedObject($state);
    }

    private function handleRefundPush(?\Order $order, $response): void
    {
        try {
            if ($this->hasService('buckaroo.refund.push.handler')) {
                // Preferred path: use the Symfony service so refunds are tracked
                $refundPushHandler = $this->getService('buckaroo.refund.push.handler');
                $refundPushHandler->handle();

                if ($order instanceof Order && $this->hasService('buckaroo.refund.order.message')) {
                    $messageRepo = $this->getService('buckaroo.refund.order.message');
                    $messageRepo->add(
                        $order,
                        'Buckaroo refund message (' . $response->transactions . '): ' . $response->statusmessage
                    );
                }
                return;
            }

            // Fallback path: container service not available (common on some PS9 setups).
            // We still want the order in PrestaShop to reflect the refund, even if we
            // cannot use the full refund tracking stack.
            $this->logger->logWarn('Refund push handler service not available, applying simple refund fallback');

            if (!$order instanceof Order) {
                $this->logger->logError('Refund push fallback: unable to resolve Order instance');
                return;
            }

            $amountCredit = (float) Tools::getValue('brq_amount_credit', 0);
            if ($amountCredit <= 0) {
                $this->logger->logError('Refund push fallback: invalid or missing brq_amount_credit');
                return;
            }

            // Decide full vs partial based only on this refund amount vs order total
            $totalPaid = (float) $order->total_paid;
            $epsilon = 0.005;

            $statusRefunded = (int) Configuration::get('PS_OS_REFUND');
            $statusPartialRefunded = (int) Configuration::get('PS_CHECKOUT_STATE_PARTIALLY_REFUNDED');

            if ($statusRefunded <= 0 && $statusPartialRefunded <= 0) {
                $this->logger->logError('Refund push fallback: no refund order states configured (PS_OS_REFUND / PS_CHECKOUT_STATE_PARTIALLY_REFUNDED)');
                return;
            }

            $targetStatus = 0;
            if ($totalPaid > 0 && abs($amountCredit - $totalPaid) <= $epsilon && $statusRefunded > 0) {
                $targetStatus = $statusRefunded;
            } elseif ($statusPartialRefunded > 0) {
                $targetStatus = $statusPartialRefunded;
            }

            if ($targetStatus > 0) {
                $history = new OrderHistory();
                $history->id_order = (int) $order->id;
                $history->date_add = date('Y-m-d H:i:s');
                $history->date_upd = date('Y-m-d H:i:s');
                $history->changeIdOrderState($targetStatus, (int) $order->id);
                $history->addWithemail(false);
            }

            // Optionally create a negative payment when configured
            if (Configuration::get(\Buckaroo\PrestaShop\Src\Refund\Settings::LABEL_REFUND_CREATE_NEGATIVE_PAYMENT)) {
                $refundKey = (string) Tools::getValue('brq_transactions', '');
                $method = (string) Tools::getValue('brq_transaction_method', '');

                if ($refundKey !== '' && $method !== '') {
                    $payment = new OrderPayment();
                    $payment->order_reference = $order->reference;
                    $payment->id_currency = $order->id_currency;
                    $payment->transaction_id = $refundKey;
                    $payment->amount = -1 * $amountCredit;
                    $payment->payment_method = $method;
                    $payment->save();
                }
            }

            // Add a basic order message so there is some audit trail
            if ($order instanceof Order) {
                $message = new Message();
                $message->id_order = (int) $order->id;
                $message->message = sprintf(
                    'Buckaroo refund (fallback) of %s received. Transaction key: %s. Message: %s',
                    $amountCredit,
                    (string) $response->transactions,
                    (string) $response->statusmessage
                );
                $message->add();
            }
        } catch (\Throwable $th) {
            $this->logger->logInfo('PUSH', (string) $th);
        }
    }
}