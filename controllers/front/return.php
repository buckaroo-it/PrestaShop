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

include_once _PS_MODULE_DIR_ . 'buckaroo3/api/paymentmethods/responsefactory.php';
include_once _PS_MODULE_DIR_ . 'buckaroo3/library/logger.php';
include_once _PS_MODULE_DIR_ . 'buckaroo3/controllers/front/common.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

class Buckaroo3ReturnModuleFrontController extends BuckarooCommonController
{
    public $ssl = true;
    private $symContainer;
    protected $logger;

    public function __construct()
    {
        parent::__construct();
        $this->setContainer();
        $this->logger = new Logger(Logger::INFO, 'return');
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

            $id_order = Order::getOrderByCartId($response->getCartId());
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
                try {
                    $refundPushHandler = $this->symContainer->get('buckaroo.refund.push.handler');
                    $refundPushHandler->handle();
                    $messageRepo = $this->symContainer->get('buckaroo.refund.order.message');
                    $messageRepo->add(
                        $order,
                        'Buckaroo refund message (' . $response->transactions . '): ' . $response->statusmessage
                    );
                } catch (\Throwable $th) {
                    $this->logger->logInfo('PUSH', (string)$th);
                }
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

        $buckarooFee = (new RawBuckarooFeeRepository())->getFeeByOrderId($order->id);

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

    private function setContainer()
    {
        global $kernel;

        if (!$kernel) {
            require_once _PS_ROOT_DIR_ . '/app/AppKernel.php';
            $kernel = new \AppKernel('prod', false);
            $kernel->boot();
        }
        $this->symContainer = $kernel->getContainer();
    }
}