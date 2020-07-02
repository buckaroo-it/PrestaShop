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

include_once _PS_MODULE_DIR_ . 'buckaroo3/library/checkout/checkout.php';
include_once _PS_MODULE_DIR_ . 'buckaroo3/library/logger.php';
include_once _PS_MODULE_DIR_ . 'buckaroo3/controllers/front/common.php';

class Buckaroo3RequestModuleFrontController extends BuckarooCommonController
{

    /* @var $checkout IDealCheckout */
    public $checkout;

    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $logger = new Logger(Logger::INFO, 'request');
        $logger->logInfo("\n\n\n\n***************** Request start ***********************");

        $this->display_column_left  = false;
        $this->display_column_right = false;
        $cart                       = $this->context->cart;
        $logger->logDebug("Get cart", $cart);

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            $debug = "Customer Id: " . $cart->id_customer . "\nDelivery Address ID: " .
            $cart->id_address_delivery . "Invoice Address ID: " .
            $cart->id_address_invoice . "\nModule Active: " . $this->module->active;

            $logger->logError("Validation Error", $debug);
            Tools::redirect('index.php?controller=order&step=1');
        }

        $filename    = Config::get('BUCKAROO_CERTIFICATE_PATH');
        $merchantkey = Config::get('BUCKAROO_MERCHANT_KEY');
        $secret_key  = Config::get('BUCKAROO_SECRET_KEY');
        $thumbprint  = Config::get('BUCKAROO_CERTIFICATE_THUMBPRINT');
        if (!file_exists($filename) || empty($merchantkey) || empty($secret_key) || empty($thumbprint)) {
            $error = $this->module->l(
                "<b>Please contact merchant:</b><br/><br/> Buckaroo Plug-in is not properly configured."
            );
            Tools::redirect('index.php?fc=module&module=buckaroo3&controller=error&error=' . $error);
        }

        $authorized = false;

        foreach (PaymentModule::getInstalledPaymentModules() as $module) {
            if ($module['name'] == 'buckaroo3') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            $logger->logError("Authorization Error", 'This payment method is not available.');
            die($this->module->l('This payment method is not available.', 'validation'));
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            $logger->logError("Load a customer", 'Failed to load the customer with ID: ' . $cart->id_customer);
            Tools::redirect('index.php?controller=order&step=1');
            exit();
        }

        $currency       = $this->context->currency;
        $total          = (float) $cart->getOrderTotal(true, Cart::BOTH);
        $payment_method = Tools::getValue('method');
        if (empty($payment_method)) {
            $logger->logError("Load a method", 'Failed to load the method');
            Tools::redirect('index.php?controller=order&step=1');
            exit();
        }
        if (Tools::getValue("service") && Tools::getValue("service") != 'digi' && Tools::getValue("service") != 'sepa') {
            $logger->logError("Load a method", 'Failed to load the method');
            Tools::redirect('index.php?controller=order&step=1');
            exit();
        }
        $debug = "Currency: " . $currency->name . "\nTotal Amount: " . $total . "\nPayment Method: " . $payment_method;
        $logger->logInfo("Checkout info", $debug);

        $this->checkout = Checkout::getInstance($payment_method, $cart);
        $this->checkout->returnUrl = 'http' . ((!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") ? 's' : '') . '://' . $_SERVER["SERVER_NAME"] . __PS_BASE_URI__ . 'index.php?fc=module&module=buckaroo3&controller=userreturn';
        $logger->logDebug("Get checkout class: ", $this->checkout);
        $pending           = Configuration::get('BUCKAROO_ORDER_STATE_DEFAULT');
        $payment_method_tr = $this->module->getPaymentTranslation($payment_method);
        $this->module->validateOrder(
            $cart->id,
            $pending,
            $total,
            $payment_method_tr,
            null,
            null,
            (int) $currency->id,
            false,
            $customer->secure_key
        );
        $id_order_cart = Order::getOrderByCartId($cart->id);
        $order         = new Order($id_order_cart);
        $this->checkout->setReference($order->reference);
        $this->checkout->setCheckout();
        $logger->logDebug("Set checkout info: ", $this->checkout);

        $logger->logInfo('Start the payment process');
        $this->checkout->startPayment();

        if ($this->checkout->isRequestSucceeded()) {
            $response = $this->checkout->getResponse();
            $logger->loginfo('Request succeeded');

            if ($this->checkout->isRedirectRequired()) {
                $oldCart     = new Cart($response->getCartId());
                $duplication = $oldCart->duplicate();
                if ($duplication && Validate::isLoadedObject($duplication['cart']) && $duplication['success']) {
                    $this->context->cookie->id_cart = $duplication['cart']->id;
                    $this->context->cookie->write();
                }
                $logger->logInfo('Redirecting ... ');
                $this->checkout->doRedirect();
                exit();
            }

            $response = $this->checkout->getResponse();
            $logger->logDebug('Checkout response', $response);

            if ($response->hasSucceeded()) {
                $logger->logInfo('Payment request succeeded. Wait push message!');
                $id_order          = $this->module->currentOrder;
                $message           = new Message();
                $message->id_order = $id_order;
                $message->message  = 'Transaction key: ' . $response->transactions;
                $message->add();

                if ($response->payment_method == 'SepaDirectDebit') {
                    /* @var $response Response */
                    foreach ($response->getResponse()->Services->Service->ResponseParameter as $param) {
                        if ($param->Name == 'MandateReference') {
                            $message           = new Message();
                            $message->id_order = $id_order;
                            $message->message  = 'MandateReference: ' . $param->_;
                            $message->add();
                        }
                        if ($param->Name == 'MandateDate') {
                            $message           = new Message();
                            $message->id_order = $id_order;
                            $message->message  = 'MandateDate: ' . $param->_;
                            $message->add();
                        }
                    }
                }
                if ($response->payment_method == 'transfer') {
                    $this->context->cookie->__set("HtmlText", $response->consumerMessage['HtmlText']);
                }
                Tools::redirect(
                    'index.php?controller=order-confirmation&id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $id_order . '&key=' . $customer->secure_key . '&responce_received=' . $response->payment_method
                );
            } else {
                $logger->logInfo('Payment request failed/canceled');
                if ($response->isValid()) {
                    $logger->logInfo('Payment request valid');
                    $id_order = Order::getOrderByCartId($response->getCartId());
                    if ($id_order) {
                        $logger->logInfo('Find order by cart ID', 'Order found. ID: ' . $id_order);
                        $logger->logInfo(
                            'Update order history with status: ' . Buckaroo3::resolveStatusCode($response->status)
                        );
                        $order           = new Order($id_order);
                        $new_status_code = Buckaroo3::resolveStatusCode($response->status);
                        $pending         = Configuration::get('BUCKAROO_ORDER_STATE_DEFAULT');
                        $error           = Configuration::get('PS_OS_CANCELED');
                        $canceled        = Configuration::get('PS_OS_ERROR');
                        if ($new_status_code != $order->getCurrentState() && ($pending == $order->getCurrentState() || $error == $order->getCurrentState() || $canceled == $order->getCurrentState())
                        ) {
                            $order_history           = new OrderHistory();
                            $order_history->id_order = $id_order;
                            $order_history->changeIdOrderState(
                                Buckaroo3::resolveStatusCode($response->status),
                                $id_order
                            );
                            $order_history->add(true);
                        }
                    } else {
                        $logger->logInfo('Find order by cart ID', 'Order not found.');
                    }

                    $oldCart     = new Cart($response->getCartId());
                    $duplication = $oldCart->duplicate();
                    if ($duplication && Validate::isLoadedObject($duplication['cart']) && $duplication['success']) {
                        $this->context->cookie->id_cart = $duplication['cart']->id;
                        $this->context->cookie->write();
                    }
                    $error = null;
                    if (($response->payment_method == 'afterpayacceptgiro' || $response->payment_method == 'afterpaydigiaccept') && $response->statusmessage) {
                        $error = $response->statusmessage;
                    }
                    $this->displayError($id_order, $error);
                } else {
                    $oldCart     = new Cart($cart->id);
                    $duplication = $oldCart->duplicate();
                    if ($duplication && Validate::isLoadedObject($duplication['cart']) && $duplication['success']) {
                        $this->context->cookie->id_cart = $duplication['cart']->id;
                        $this->context->cookie->write();
                    }
                    $logger->logError('Payment request not valid', $response);
                    $error = null;
                    if (($response->payment_method == 'afterpayacceptgiro' || $response->payment_method == 'afterpaydigiaccept') && $response->statusmessage) {
                        $error = $response->statusmessage;
                    }
                    $this->displayError(null, error);
                }
            };
        } else {
            $response = $this->checkout->getResponse();
            $logger->logError('Request not succeeded', $this->checkout);
            $oldCart     = new Cart($cart->id);
            $duplication = $oldCart->duplicate();
            if ($duplication && Validate::isLoadedObject($duplication['cart']) && $duplication['success']) {
                $this->context->cookie->id_cart = $duplication['cart']->id;
                $this->context->cookie->write();
            }
            $error = null;
            if (!empty($response) && !empty($response->payment_method) && !empty($response->payment_method)
                && ($response->payment_method == 'afterpayacceptgiro' || $response->payment_method == 'afterpaydigiaccept')
            ) {
                $error = $response->statusmessage;
            }
            $this->displayError(null, $error);
        };
    }
}
