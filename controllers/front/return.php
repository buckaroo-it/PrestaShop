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

        if ($response->isValid()) {
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

                $new_status_code = Buckaroo3::resolveStatusCode($response->status, $id_order);
                $order = new Order($id_order);

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

                $currentState = (int) $order->getCurrentState();
                $newStatusCode = (int) $new_status_code;

                // Validate order state transition is allowed (PrestaShop 9.0.1 compatibility)
                $isTransitionAllowed = Buckaroo3::isValidOrderStateTransition($id_order, $newStatusCode);

                if ($currentState !== $newStatusCode && $isTransitionAllowed
                    && ($pending == $currentState || $canceled == $currentState
                        || $error == $currentState || $outofstock_unpaid == $currentState)
                ) {
                    $this->logger->logInfo('Update order status');
                    try {
                        $history = new OrderHistory();
                        $history->id_order = $id_order;
                        $history->date_add = date('Y-m-d H:i:s');
                        $history->date_upd = date('Y-m-d H:i:s');

                        // Use order object instead of ID for PrestaShop 9.0.1 compatibility
                        // Determine if we should use existing payments
                        $useExistingPayments = !$order->hasInvoice();

                        // Change order state with proper order object and payment handling
                        $history->changeIdOrderState($newStatusCode, $order, $useExistingPayments);

                        // Use addWithemail (correct method name for PrestaShop 9.0.1)
                        $historyAdded = $history->addWithemail(false);

                        if (!$historyAdded) {
                            $this->logger->logError('Failed to add order history entry for order #' . $id_order);
                        }
                    } catch (\Exception $e) {
                        $this->logger->logError('Error updating order status for order #' . $id_order . ': ' . $e->getMessage());
                    }

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

    private function handleRefundPush(?\Order $order, $response): void
    {
        if (!$this->hasService('buckaroo.refund.push.handler')) {
            $this->logger->logWarn('Refund push received but service is not available');
            return;
        }

        try {
            $refundPushHandler = $this->getService('buckaroo.refund.push.handler');
            $refundPushHandler->handle();

            if ($order instanceof Order && $this->hasService('buckaroo.refund.order.message')) {
                $messageRepo = $this->getService('buckaroo.refund.order.message');
                $messageRepo->add(
                    $order,
                    'Buckaroo refund message (' . $response->transactions . '): ' . $response->statusmessage
                );
            }
        } catch (\Throwable $th) {
            $this->logger->logInfo('PUSH', (string) $th);
        }
    }
}