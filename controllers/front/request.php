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
use Buckaroo\Transaction\Response\TransactionResponse;

include_once _PS_MODULE_DIR_ . 'buckaroo3/library/checkout/checkout.php';
include_once _PS_MODULE_DIR_ . 'buckaroo3/controllers/front/common.php';
include_once _PS_MODULE_DIR_ . 'buckaroo3/library/logger.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

class Buckaroo3RequestModuleFrontController extends BuckarooCommonController
{
    /* @var $checkout Checkout */
    public $checkout;
    public $display_column_left = false;
    /** @var bool */
    public $display_column_right = false;
    /** @var \Logger */
    public $logger;

    public function __construct()
    {
        parent::__construct();

        $this->logger = new \Logger(CoreLogger::INFO, '');
    }

    /**
     * @throws Exception
     *
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $this->logger->logInfo("\n\n\n\n***************** Request start ***********************");

        $cart = $this->context->cart;
        $this->logger->logDebug('Get cart', $cart->id);

        if (!$this->isValidCart($cart)) {
            $this->handleInvalidCart($cart);
            return;
        }

        if (!$this->isValidConfiguration()) {
            return;
        }

        if (!$this->isAuthorized()) {
            return;
        }

        $customer = new Customer($cart->id_customer);
        if (!$this->isValidCustomer($customer)) {
            return;
        }

        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
        $payment_method = Tools::getValue('method');

        if (empty($payment_method)) {
            $this->logger->logError('Load a method', 'Failed to load the method');
            Tools::redirect('index.php?controller=order&step=1');
            return;
        }

        $total = $this->applyBuckarooFee($payment_method, $total);

        if (!$this->isValidService()) {
            return;
        }

        $debug = 'Currency: ' . $currency->name . "\nTotal Amount: " . $total . "\nPayment Method: " . $payment_method;
        $this->logger->logInfo('Checkout info', $debug);

        $this->initializeCheckout($cart, $payment_method, $currency, $total, $customer);

        if ($this->checkout->isRequestSucceeded()) {
            $this->handleSuccessfulRequest($cart->id, $customer);
        } else {
            $this->handleFailedRequest($cart->id);
        }
    }

    private function isValidCart($cart)
    {
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            return false;
        }

        // Check if an order has already been placed using this cart
        if (Order::getOrderByCartId($cart->id)) {
            // Duplicate the cart
            $oldCart = new Cart($cart->id);
            $duplication = $oldCart->duplicate();
            if ($duplication && Validate::isLoadedObject($duplication['cart']) && $duplication['success']) {
                $this->context->cookie->id_cart = $duplication['cart']->id;
                $this->context->cart = $duplication['cart'];
                $this->context->cookie->write();
                return true;
            } else {
                return false;
            }
        }

        return true;
    }

    private function handleInvalidCart($cart)
    {
        $debug = 'Customer Id: ' . $cart->id_customer . "\nDelivery Address ID: " . $cart->id_address_delivery . 'Invoice Address ID: ' . $cart->id_address_invoice . "\nModule Active: " . $this->module->active;
        $this->logger->logError('Validation Error', $debug);
        Tools::redirect('index.php?controller=order&step=1');
    }

    private function isValidConfiguration()
    {
        $merchantKey = Configuration::get('BUCKAROO_MERCHANT_KEY');
        $secretKey = Configuration::get('BUCKAROO_SECRET_KEY');
        if (empty($merchantKey) || empty($secretKey)) {
            $error = $this->module->l('<b>Please contact merchant:</b><br/><br/> Buckaroo Plug-in is not properly configured.');
            Tools::redirect('index.php?fc=module&module=buckaroo3&controller=error&error=' . $error);
            return false;
        }
        return true;
    }

    private function isAuthorized()
    {
        foreach (PaymentModule::getInstalledPaymentModules() as $module) {
            if ($module['name'] == 'buckaroo3') {
                return true;
            }
        }
        $this->logger->logError('Authorization Error', 'This payment method is not available.');
        exit($this->module->l('This payment method is not available.', 'validation'));
    }

    private function isValidCustomer($customer)
    {
        if (!Validate::isLoadedObject($customer)) {
            $this->logger->logError('Load a customer', 'Failed to load the customer with ID: ' . $cart->id_customer);
            Tools::redirect('index.php?controller=order&step=1');
            return false;
        }
        return true;
    }

    private function applyBuckarooFee($payment_method, $total)
    {
        $buckarooFee = $this->module->getBuckarooFee($payment_method);

        if (is_array($buckarooFee)) {
            $buckarooFeeTaxIncl = $buckarooFee['buckaroo_fee_tax_incl'];
            $total += $buckarooFeeTaxIncl;
        }

        return $total;
    }

    private function isValidService()
    {
        if (Tools::getValue('service') && Tools::getValue('service') != 'digi' && Tools::getValue('service') != 'sepa') {
            $this->logger->logError('Load a method', 'Failed to load the method');
            Tools::redirect('index.php?controller=order&step=1');
            return false;
        }
        return true;
    }

    private function initializeCheckout($cart, $payment_method, $currency, $total, $customer)
    {
        try {
            $this->checkout = Checkout::getInstance($payment_method, $cart, $this->context);
            $this->setCheckoutProperties();
            $this->setCheckoutUrls();
        } catch (Exception $e) {
            $this->logger->logError('Set checkout info: ', $e->getMessage());
            $this->displayError(null, $e->getMessage());
            return;
        }

        $this->logger->logDebug('Get checkout class: ');
        $pending = Configuration::get('BUCKAROO_ORDER_STATE_DEFAULT');
        $payment_method_tr = (new RawPaymentMethodRepository())->getPaymentMethodsLabel($payment_method);

        if (!$this->checkout->isVerifyRequired()) {
            try {
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
            } catch (Exception $e) {
                $this->logger->logError('Order validation failed: ', $e->getMessage());
                $this->displayError(null, $e->getMessage());
                return;
            }
        }

        $id_order_cart = Order::getIdByCartId($cart->id);
        $order = new Order($id_order_cart);
        $this->checkout->setReference($order->reference);

        try {
            $this->checkout->setCheckout();
            $this->logger->logDebug('Set checkout info: ');
            if ($this->checkout->isVerifyRequired()) {
                $this->logger->logInfo('Start verify process');
                $this->checkout->startVerify(['cid' => $cart->id_customer]);
            } else {
                $this->logger->logInfo('Start the payment process');
                $this->checkout->startPayment();
            }
        } catch (Exception $e) {
            $this->logger->logError('Set checkout info: ', $e->getMessage());
            $this->displayError(null, $e->getMessage());
            return;
        }
    }

    private function setCheckoutProperties()
    {
        $this->checkout->platformName = 'PrestaShop';
        $this->checkout->platformVersion = _PS_VERSION_;
        $this->checkout->moduleSupplier = $this->module->author;
        $this->checkout->moduleName = $this->module->name;
        $this->checkout->moduleVersion = $this->module->version;
    }

    private function setCheckoutUrls()
    {
        $this->checkout->returnUrl = 'http' . ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 's' : '') . '://' . $_SERVER['SERVER_NAME'] . __PS_BASE_URI__ . 'index.php?fc=module&module=buckaroo3&controller=userreturn';
        $this->checkout->pushUrl = 'http' . ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 's' : '') . '://' . $_SERVER['SERVER_NAME'] . __PS_BASE_URI__ . 'index.php?fc=module&module=buckaroo3&controller=return';
    }

    private function handleSuccessfulRequest($cartId, $customer)
    {
        $response = $this->checkout->getResponse();
        $this->logger->logInfo('Request succeeded');

        if ($this->checkout->isRedirectRequired()) {
            $this->setCartCookie($cartId);
            $this->logger->logInfo('Redirecting ... ');
            $this->checkout->doRedirect();
            exit;
        }

        if ($response->hasSucceeded()) {
            $this->processSuccessfulPayment($cartId, $customer, $response);
        } else {
            $this->processFailedPayment($cartId, $response);
        }
    }

    private function processSuccessfulPayment($cartId, $customer, $response)
    {
        $this->logger->logInfo('Payment request succeeded. Wait push message!');
        $id_order = $this->module->currentOrder;

        $responseData = $response->getResponse();
        $this->createTransactionMessage($id_order, 'Transaction Key: ' . $responseData->getTransactionKey());

        if ($response->payment_method == 'SepaDirectDebit') {
            $this->processSepaDirectDebit($id_order, $responseData);
        }

        if ($response->payment_method == 'transfer') {
            $this->context->cookie->__set('HtmlText', $response->consumerMessage['HtmlText']);
        }

        // Check if the order is partially paid
        if ($response->isPartialPayment()) {
            $this->logger->logInfo('isPartialPayment detected.');

            // Log the partial payment details
            $this->logger->logInfo('Partial payment details', [
                'statuscode' => $response->statuscode,
                'statusmessage' => $response->statusmessage,
                'amount' => $response->amount,
                'brq_relatedtransaction_partialpayment' => $response->brq_relatedtransaction_partialpayment,
            ]);

            // Calculate the remaining amount
            $remainingAmount = (float)$this->context->cart->getOrderTotal(true, Cart::BOTH);
            $this->logger->logInfo('Remaining Amount: ' . $remainingAmount);

            if ($remainingAmount > 0) {
                // Keep the user on the checkout page to complete the payment
                $this->logger->logInfo('Redirecting to checkout step 3 to complete the payment.');
                Tools::redirect('index.php?controller=order&step=3');
                exit;
            } else {
                $this->logger->logInfo('No remaining amount. Redirecting to order confirmation.');
                Tools::redirect(
                    'index.php?controller=order-confirmation&id_cart=' . $cartId . '&id_module=' . $this->module->id . '&id_order=' . $id_order . '&key=' . $customer->secure_key . '&success=true&response_received=' . $response->payment_method
                );
                exit;
            }
        } else {
            $this->logger->logInfo('Full payment completed. Redirecting to order confirmation.');
            Tools::redirect(
                'index.php?controller=order-confirmation&id_cart=' . $cartId . '&id_module=' . $this->module->id . '&id_order=' . $id_order . '&key=' . $customer->secure_key . '&success=true&response_received=' . $response->payment_method
            );
            exit;
        }
    }

    private function processSepaDirectDebit($id_order, $responseData)
    {
        $parameters = $responseData->getServiceParameters();
        if (!empty($parameters['mandateReference'])) {
            $this->createTransactionMessage($id_order, 'MandateReference: ' . $parameters['mandateReference']);
        }
        if (!empty($parameters['mandateDate'])) {
            $this->createTransactionMessage($id_order, 'MandateDate: ' . $parameters['mandateDate']);
        }
    }

    private function processFailedPayment($cartId, $response)
    {
        $this->logger->logInfo('Payment request failed/canceled');
        $this->setCartCookie($cartId);

        if ($response->isValid()) {
            $this->updateOrderHistory($response);
        } else {
            $this->logger->logInfo('Payment request not valid');
        }

        $error = null;
        if (($response->payment_method == 'afterpayacceptgiro' || $response->payment_method == 'afterpaydigiaccept') && $response->statusmessage) {
            $error = $response->statusmessage;
        }
        $this->displayError(null, $error);
    }

    private function updateOrderHistory($response)
    {
        $this->logger->logInfo('Payment request valid');
        $id_order = Order::getOrderByCartId($response->getCartId());
        if ($id_order) {
            $this->updateOrderStatus($response, $id_order);
        } else {
            $this->logger->logInfo('Find order by cart ID', 'Order not found.');
        }
    }

    private function updateOrderStatus($response, $id_order)
    {
        $this->logger->logInfo('Find order by cart ID', 'Order found. ID: ' . $id_order);
        $this->logger->logInfo('Update order history with status: ' . Buckaroo3::resolveStatusCode($response->status));

        $order = new Order($id_order);
        $new_status_code = Buckaroo3::resolveStatusCode($response->status);
        $pending = Configuration::get('BUCKAROO_ORDER_STATE_DEFAULT');
        $canceled = Configuration::get('BUCKAROO_ORDER_STATE_FAILED');
        $error = Configuration::get('PS_OS_ERROR');

        if ($new_status_code != $order->getCurrentState() && ($pending == $order->getCurrentState() || $error == $order->getCurrentState() || $canceled == $order->getCurrentState())) {
            $order_history = new OrderHistory();
            $order_history->id_order = $id_order;
            $order_history->changeIdOrderState(Buckaroo3::resolveStatusCode($response->status), $id_order);
            $order_history->add(true);
        }
    }

    private function handleFailedRequest($cartId)
    {
        $response = $this->checkout->getResponse();
        $this->logger->logInfo('Request not succeeded');

        $this->setCartCookie($cartId);

        $error = null;
        if ($response->getResponse() instanceof TransactionResponse) {
            $error = $response->getSomeError();
        }

        if (isset($error['errorresponsemessage']) && is_array($error)) {
            $this->displayError(null, $error['errorresponsemessage']);
        } else {
            $this->displayError(null, $error);
        }
    }

    private function createTransactionMessage($orderId, $messageString)
    {
        $message = new Message();
        $message->id_order = $orderId;
        $message->message = $messageString;
        $message->add();
    }

    private function setCartCookie($cartId)
    {
        $oldCart = new Cart($cartId);
        $duplication = $oldCart->duplicate();
        if ($duplication && Validate::isLoadedObject($duplication['cart']) && $duplication['success']) {
            $this->logger->logInfo('Cart duplicated successfully');
            $this->context->cookie->id_cart = $duplication['cart']->id;
            $this->context->cookie->write();
        } else {
            $this->logger->logError('Cart duplication failed');
        }
    }
}