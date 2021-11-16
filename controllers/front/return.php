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

include_once _PS_MODULE_DIR_ . 'buckaroo3/api/paymentmethods/responsefactory.php';
include_once _PS_MODULE_DIR_ . 'buckaroo3/library/logger.php';
include_once _PS_MODULE_DIR_ . 'buckaroo3/controllers/front/common.php';

class Buckaroo3ReturnModuleFrontController extends BuckarooCommonController
{

    public $ssl = true;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {

        $this->display_column_left  = false;
        $this->display_column_right = false;
        $logger                     = new Logger(Logger::INFO, 'return');
        $logger->logInfo("\n\n\n\n***************** Return start ***********************");

        parent::initContent();

        $statuses = array();
        $tmp      = OrderState::getOrderStates(1);
        foreach ($tmp as $stat) {
            $statuses[$stat["id_order_state"]] = $stat["name"];
        }
        $response = ResponseFactory::getResponse();
        $logger->logInfo('Parse response', $response);

        if ($response->isValid()) {
            $logger->logInfo('Response valid');
            if (!empty($response->payment_method)
                &&
                ($response->payment_method == 'paypal')
                &&
                !empty($response->statuscode)
                &&
                ($response->statuscode == 791)
            ) {
                $response->statuscode == 890;
                $response->status = $response::BUCKAROO_CANCELED;
            }

            $id_order   = Order::getOrderByCartId($response->getCartId());
            $orders     = Order::getByReference($response->getReferenceId());
            $references = array();
            foreach ($orders as $order) {
                $row          = get_object_vars($order);
                $references[] = $row['reference'];
            }
            $logger->logInfo('Get order by cart id', 'Order ID: ' . $id_order);
            if ($response->brq_relatedtransaction_partialpayment != null) {
                $logger->logInfo('PUSH', "Partial payment PUSH received " . $response->status);
                if ($id_order && $response->hasSucceeded()) {
                    $order = new Order($id_order);
                    $order->setInvoice(false);
                    $payment                  = new OrderPayment();
                    $payment->order_reference = $order->reference;
                    $payment->id_currency     = $order->id_currency;
                    $payment->transaction_id  = $response->transactions;
                    $payment->amount          = urldecode($response->amount);
                    $payment->payment_method  = $response->payment_method;
                    if ($payment->id_currency == $order->id_currency) {
                        $order->total_paid_real += $response->amount;
                    } else {
                        $order->total_paid_real += Tools::ps_round(
                            Tools::convertPrice($response->amount, $payment->id_currency, false),
                            2
                        );
                    }
                    $order->save();
                    $payment->conversion_rate = 1;
                    $payment->save();
                    Db::getInstance()->execute(
                        '
                                            INSERT INTO `' . _DB_PREFIX_ . 'order_invoice_payment`
                    VALUES(' . (int) $order->invoice_number . ', ' . (int) $payment->id . ', ' . (int) $order->id . ')'
                    );

                    $message           = new Message();
                    $message->id_order = $id_order;
                    $message->message  = 'Buckaroo partial payment message (' . $response->transactions . '): ' . $response->statusmessage;//phpcs:ignore
                    $message->add();
                }
                exit();
            }
            if ($response->brq_relatedtransaction_refund != null) {
                $logger->logInfo('PUSH', "Refund payment PUSH received " . $response->status);
                if ($response->hasSucceeded()) {
                    $order                    = new Order($id_order);
                    if (isset(OrderPayment::$definition['fields']['amount']['validate'])) {
                        OrderPayment::$definition['fields']['amount']['validate'] = 'isAnything';
                    }
                    $payment                  = new OrderPayment();
                    $payment->order_reference = $order->reference;
                    $payment->id_currency     = $order->id_currency;
                    $payment->transaction_id  = $response->transactions;
                    $payment->amount          = urldecode($response->amount_credit) * (-1);
                    $payment->payment_method  = $response->payment_method;
                    
                    // $oldRealpaid              = $order->total_paid_real;
/*                    if ($payment->id_currency == $order->id_currency) {
                        $order->total_paid_real += $payment->amount;
                    } else {
                        $order->total_paid_real += Tools::ps_round(
                            Tools::convertPrice($payment->amount, $payment->id_currency, false),
                            2
                        );
                    }*/
                    $order->save();
                    // if ($order->total_paid_real == 0 && $oldRealpaid > 0) {
                        $new_status_code   = Configuration::get('PS_OS_REFUND');
                        $history           = new OrderHistory();
                        $history->id_order = $id_order;
                        $history->date_add = date('Y-m-d H:i:s');
                        $history->date_upd = date('Y-m-d H:i:s');
                        $history->changeIdOrderState($new_status_code, $id_order);
                        $history->addWithemail(false);
                    // }
                    $payment->conversion_rate = 1;
                    $payment->save();

                    $query = "INSERT INTO `" . _DB_PREFIX_ . "buckaroo_transactions` (transaction_id, original_transaction) VALUES('" . pSQL($response->transactions) . "', '" . pSQL($response->brq_relatedtransaction_refund) . "')";//phpcs:ignore
                    Db::getInstance()->execute($query);

                    $message           = new Message();
                    $message->id_order = $id_order;
                    $message->message  = 'Buckaroo refund message (' . $response->transactions . '): ' . $response->statusmessage;//phpcs:ignore
                    $message->add();
                }
                exit();
            }
            if (!$id_order) {
                header("HTTP/1.1 503 Service Unavailable");
                echo "Order do not exists";
                $logger->logInfo('PUSH', "Order do not exists");
                exit();
            } else {
                $logger->logInfo('Update the order', "Order ID: " . $id_order);

                $new_status_code = Buckaroo3::resolveStatusCode($response->status);
                $order           = new Order($id_order);

                if (!in_array($order->reference, $references)) {
                    header("HTTP/1.1 503 Service Unavailable");
                    $logger->logInfo('Order not in reference ' . $order->reference);
                    echo 'Order not in reference: ' . $order->reference;
                    exit();
                }

                $logger->logInfo(
                    'Old order status code: ' . $order->getCurrentState(
                    ) . "; new order status code: " . $new_status_code
                );
                $pending  = Configuration::get('BUCKAROO_ORDER_STATE_DEFAULT');
                $canceled = Configuration::get('BUCKAROO_ORDER_STATE_FAILED');
                $error = Configuration::get('PS_OS_ERROR');
                $outofstock_unpaid = Configuration::get('PS_OS_OUTOFSTOCK_UNPAID');
                if ($new_status_code != $order->getCurrentState() &&
                    ($pending == $order->getCurrentState() || $canceled == $order->getCurrentState(
                    ) || $error == $order->getCurrentState() || $outofstock_unpaid == $order->getCurrentState())
                ) {
                    $logger->logInfo("Update order status");
                    $history           = new OrderHistory();
                    $history->id_order = $id_order;
                    $history->date_add = date('Y-m-d H:i:s');
                    $history->date_upd = date('Y-m-d H:i:s');
                    $history->changeIdOrderState($new_status_code, $id_order);
                    $history->addWithemail(false);

                    // $payments = OrderPayment::getByOrderId($id_order);
                    $payments = OrderPayment::getByOrderReference($order->reference);
                    foreach ($payments as $payment) {
                        if ($payment->payment_method == 'Group transaction') {
                            $payment->amount = 0;
                            $payment->update();
                        }
                        /* @var $payment OrderPaymentCore */
                        if ($payment->amount == $response->amount && $payment->transaction_id == '') {
                            $payment->transaction_id = $response->transactions;
                            $payment->update();
                        }
                    }
                } else {
                    $logger->logInfo('Order status not updated');
                }
                $statusCodeName = $new_status_code;
                if (!empty($statuses[$new_status_code])) {
                    $statusCodeName = $statuses[$new_status_code];
                }
                $message           = new Message();
                $message->id_order = $id_order;
                $message->message  = 'Push message recieved. Buckaroo status: ' . $statusCodeName . '. Transaction key: ' . $response->transactions;//phpcs:ignore
                $message->add();
                if ($response->statusmessage) {
                    $message           = new Message();
                    $message->id_order = $id_order;
                    $message->message  = 'Buckaroo message: ' . $response->statusmessage;
                    $message->add();
                }
            }
        } else {
            header("HTTP/1.1 503 Service Unavailable");
            $logger->logError('Payment response not valid', $response);
            echo 'Payment response not valid';
                    exit();
        }

        $sql = 'SELECT buckaroo_fee FROM ' . _DB_PREFIX_ . 'buckaroo_fee where id_cart = ' .
            (int)($response->getCartId());
        $buckarooFee = Db::getInstance()->getValue($sql);

        if ($buckarooFee && (isset($payment) && $payment->payment_method != 'Group transaction')) {
            $jj=0;
            foreach ($payments as $payment) {
                if ($jj>0) {
                    continue;
                }
                if ($payment->amount != $response->amount && $payment->transaction_id == '') {
                    $payment->amount = $response->amount;
                    $payment->transaction_id = $response->transactions;
                    $payment->update();
                    $jj++;
                }
            }
        }

        exit();
    }
}
