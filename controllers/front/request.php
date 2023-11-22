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

use Buckaroo\PrestaShop\Src\Repository\RawPaymentMethodRepository;

include_once _PS_MODULE_DIR_ . 'buckaroo3/library/checkout/checkout.php';
include_once _PS_MODULE_DIR_ . 'buckaroo3/controllers/front/common.php';
include_once _PS_MODULE_DIR_ . 'buckaroo3/library/logger.php';

class Buckaroo3RequestModuleFrontController extends BuckarooCommonController
{
    /* @var $checkout IDealCheckout */
    public $checkout;
    public $display_column_left = false;
    /** @var bool */
    public $display_column_right = false;

    /**
     * @throws Exception
     *
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $logger = new \Logger(CoreLogger::INFO, '');
        $logger->logInfo("\n\n\n\n***************** Request start ***********************");

        $cart = $this->context->cart;
        $logger->logDebug('Get cart', $cart->id);

        if ($cart->id_customer == 0
            || $cart->id_address_delivery == 0
            || $cart->id_address_invoice == 0
            || !$this->module->active) {
            $debug = 'Customer Id: ' . $cart->id_customer . "\nDelivery Address ID: " .
            $cart->id_address_delivery . 'Invoice Address ID: ' .
            $cart->id_address_invoice . "\nModule Active: " . $this->module->active;

            $logger->logError('Validation Error', $debug);
            Tools::redirect('index.php?controller=order&step=1');
        }

        $merchant_key = Configuration::get('BUCKAROO_MERCHANT_KEY');
        $secret_key = Configuration::get('BUCKAROO_SECRET_KEY');
        if (empty($merchant_key) || empty($secret_key)) {
            $error = $this->module->l(
                '<b>Please contact merchant:</b><br/><br/> Buckaroo Plug-in is not properly configured.'
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
            $logger->logError('Authorization Error', 'This payment method is not available.');
            exit($this->module->l('This payment method is not available.', 'validation'));
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            $logger->logError('Load a customer', 'Failed to load the customer with ID: ' . $cart->id_customer);
            Tools::redirect('index.php?controller=order&step=1');
            exit;
        }

        $currency = $this->context->currency;
        $total = (float) $cart->getOrderTotal(true, Cart::BOTH);
        $payment_method = Tools::getValue('method');

        $getBuckarooFeeValue = $this->module->getBuckarooFeeService()->getBuckarooFeeValue($payment_method);
        if ($buckarooFee = $getBuckarooFeeValue) {
            $buckarooFee = trim($buckarooFee);

            if (strpos($buckarooFee, '%') !== false) {
                // The fee includes a percentage sign, so treat it as a percentage.
                // Remove the percentage sign and convert the remaining value to a float.
                $buckarooFee = str_replace('%', '', $buckarooFee);
                $total += ($total * ((float) $buckarooFee / 100));
            } elseif ($buckarooFee > 0) {
                // The fee is a flat amount.
                $total += (float) $buckarooFee;
            }
        }

        if (empty($payment_method)) {
            $logger->logError('Load a method', 'Failed to load the method');
            Tools::redirect('index.php?controller=order&step=1');
            exit;
        }
        if (Tools::getValue('service')
            && Tools::getValue('service') != 'digi'
            && Tools::getValue('service') != 'sepa') {
            $logger->logError('Load a method', 'Failed to load the method');
            Tools::redirect('index.php?controller=order&step=1');
            exit;
        }
        $debug = 'Currency: ' . $currency->name . "\nTotal Amount: " . $total . "\nPayment Method: " . $payment_method;
        $logger->logInfo('Checkout info', $debug);

        $this->checkout = Checkout::getInstance($payment_method, $cart);
        $this->checkout->platformName = 'PrestaShop';
        $this->checkout->platformVersion = _PS_VERSION_;
        $this->checkout->moduleSupplier = $this->module->author;
        $this->checkout->moduleName = $this->module->name;
        $this->checkout->moduleVersion = $this->module->version;
        $this->checkout->returnUrl = 'http' . ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 's' : '') . '://' . $_SERVER['SERVER_NAME'] . __PS_BASE_URI__ . 'index.php?fc=module&module=buckaroo3&controller=userreturn'; // phpcs:ignore
        $this->checkout->pushUrl = 'http' . ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 's' : '') . '://' . $_SERVER['SERVER_NAME'] . __PS_BASE_URI__ . 'index.php?fc=module&module=buckaroo3&controller=return';
        $logger->logDebug('Get checkout class: ');
        $pending = Configuration::get('BUCKAROO_ORDER_STATE_DEFAULT');

        $payment_method_tr = (new RawPaymentMethodRepository())->getPaymentMethodsLabel($payment_method);


//        $context = Context::getContext();
//        if(empty($context->employee))
//        {
//            $context->employee = new Employee(1);
//        }

        if (!$this->checkout->isVerifyRequired()) {
            $this->module->validateOrder(
                (int) $cart->id,
                $pending,
                (float) $total,
                $payment_method_tr,
                null,
                null,
                (int) $currency->id,
                false,
                $customer->secure_key
            );
        }
        $id_order_cart = Order::getIdByCartId($cart->id);
        $order = new Order($id_order_cart);
        $this->checkout->setReference($order->reference);
        $this->checkout->setCheckout();
        $logger->logDebug('Set checkout info: ');

        try {
            if ($this->checkout->isVerifyRequired()) {
                $logger->logInfo('Start verify process');
                $this->checkout->startVerify(['cid' => $cart->id_customer]);
            } else {
                $logger->logInfo('Start the payment process');
                $this->checkout->startPayment();
            }
        }catch (Exception $e) {
            $logger->logError('Set checkout info: ', $e->getMessage());
            $this->displayError(null, $e->getMessage());
            return;
        }


        if ($this->checkout->isRequestSucceeded()) {
            $response = $this->checkout->getResponse();
            $logger->loginfo('Request succeeded');

            if ($this->checkout->isRedirectRequired()) {
                $oldCart = new Cart($cart->id);
                $duplication = $oldCart->duplicate();
                if ($duplication && Validate::isLoadedObject($duplication['cart']) && $duplication['success']) {
                    $this->context->cookie->id_cart = $duplication['cart']->id;
                    $this->context->cookie->write();
                }

                $logger->logInfo('Redirecting ... ');
                $this->checkout->doRedirect();
                exit;
            }

            $logger->logDebug('Checkout response', $response);

            if ($response->hasSucceeded()) {
                $logger->logInfo('Payment request succeeded. Wait push message!');
                $id_order = $this->module->currentOrder;
                $message = new Message();
                $message->id_order = $id_order;
                $message->message = 'Transaction key: ' . $response->transactions;
                $message->add();
                // TODO fix adding Mandateref & date as message
                if ($response->payment_method == 'SepaDirectDebit') {
                    /* @var $response Response */
                    foreach ($response->getResponse()->Services->Service->ResponseParameter as $param) {
                        if ($param->Name == 'MandateReference') {
                            $message = new Message();
                            $message->id_order = $id_order;
                            $message->message = 'MandateReference: ' . $param->_;
                            $message->add();
                        }
                        if ($param->Name == 'MandateDate') {
                            $message = new Message();
                            $message->id_order = $id_order;
                            $message->message = 'MandateDate: ' . $param->_;
                            $message->add();
                        }
                    }
                }
                if ($response->payment_method == 'transfer') {
                    $this->context->cookie->__set('HtmlText', $response->consumerMessage['HtmlText']);
                }
                Tools::redirect(
                    'index.php?controller=order-confirmation&id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $id_order . '&key=' . $customer->secure_key . '&success=true&response_received=' . $response->payment_method// phpcs:ignore
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
                        $order = new Order($id_order);
                        $new_status_code = Buckaroo3::resolveStatusCode($response->status);
                        $pending = Configuration::get('BUCKAROO_ORDER_STATE_DEFAULT');
                        $canceled = Configuration::get('BUCKAROO_ORDER_STATE_FAILED');
                        $error = Configuration::get('PS_OS_ERROR');
                        if ($new_status_code != $order->getCurrentState()
                            && ($pending == $order->getCurrentState()
                                || $error == $order->getCurrentState()
                                || $canceled == $order->getCurrentState())
                        ) {
                            $order_history = new OrderHistory();
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

                    $oldCart = new Cart($cart->id);
                    $duplication = $oldCart->duplicate();
                    if ($duplication
                        && Validate::isLoadedObject($duplication['cart'])
                        && $duplication['success']) {
                        $this->context->cookie->id_cart = $duplication['cart']->id;
                        $this->context->cookie->write();
                    }
                    $error = null;
                    if (($response->payment_method == 'afterpayacceptgiro'
                        || $response->payment_method == 'afterpaydigiaccept')
                        && $response->statusmessage) {
                        $error = $response->statusmessage;
                    }
                    $this->displayError($id_order, $error);
                } else {
                    $oldCart = new Cart($cart->id);
                    $duplication = $oldCart->duplicate();
                    if ($duplication && Validate::isLoadedObject($duplication['cart']) && $duplication['success']) {
                        $this->context->cookie->id_cart = $duplication['cart']->id;
                        $this->context->cookie->write();
                    }
                    $logger->logInfo('Payment request not valid');
                    $error = null;
                    if (($response->payment_method == 'afterpayacceptgiro'
                        || $response->payment_method == 'afterpaydigiaccept')
                        && $response->statusmessage) {
                        $error = $response->statusmessage;
                    }
                    $this->displayError(null, $error);
                }
            }
        } else {
            $response = $this->checkout->getResponse();
            $logger->logInfo('Request not succeeded');

            $oldCart = new Cart($cart->id);
            $duplication = $oldCart->duplicate();

            if ($duplication && Validate::isLoadedObject($duplication['cart']) && $duplication['success']) {
                $this->context->cookie->id_cart = $duplication['cart']->id;
                $this->context->cookie->write();
            }

            $error = null;
            if ($response->hasSomeError()) {
                $error = $response->getSomeError();
            }

            if (isset($error['errorresponsemessage']) && is_array($error)) {
                $this->displayError(null, $error['errorresponsemessage']);
            } else {
                $this->displayError(null, $error);
            }
        }
    }
}
