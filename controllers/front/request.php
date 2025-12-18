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

include_once __DIR__ . '/../../library/checkout/checkout.php';
include_once __DIR__ . '/common.php';
include_once __DIR__ . '/../../library/logger.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

class Buckaroo3RequestModuleFrontController extends BuckarooCommonController
{
    public $checkout;
    public $display_column_left = false;
    public $display_column_right = false;
    protected $logger;
    private RawPaymentMethodRepository $paymentMethodRepository;

    public function __construct()
    {
        parent::__construct();
        $this->logger = new Logger(Logger::INFO, 'request');
        $this->paymentMethodRepository = $this->resolvePaymentMethodRepository();
    }

    private function resolvePaymentMethodRepository(): RawPaymentMethodRepository
    {
        if ($this->hasService('buckaroo.repository.raw_payment_method')) {
            return $this->getService('buckaroo.repository.raw_payment_method');
        }

        return new RawPaymentMethodRepository();
    }

    public function postProcess()
    {
        $this->logger->logInfo("\n\n\n\n***************** Request start ***********************");

        try {
            if (!$this->context || !$this->context->cart) {
                $this->logger->logError('Context or cart is not properly initialized.');
                $this->redirectToCheckoutStep(1, $this->module->l('Your shopping session expired. Please start the checkout again.'));
                return;
            }

            $cart = $this->context->cart;

            if (!$this->isValidCart($cart)) {
                return;
            }

            if (!$this->isValidConfiguration()) {
                return;
            }

            if (!$this->isAuthorized()) {
                return;
            }

            $customer = new Customer($cart->id_customer);
            if (!$this->isValidCustomer($customer, $cart)) {
                return;
            }

            $currency = $this->context->currency;
            if (!$this->isValidCurrency($currency)) {
                $this->redirectToCheckoutStep(1, $this->module->l('The selected currency is no longer available. Please refresh the checkout.'));
                return;
            }

            $payment_method = Tools::strtolower(trim((string) Tools::getValue('method', '')));
            if (!$this->isValidPaymentMethod($payment_method)) {
                return;
            }

            if (!$this->isCurrencyAllowedForMethod($payment_method, $currency)) {
                $this->redirectToCheckoutStep(1, $this->module->l('Twint is only available for Swiss francs (CHF).'));
                return;
            }

            $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
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
        } catch (Exception $exception) {
            $this->logger->logError('Unexpected checkout exception: ' . $exception->getMessage());
            $this->displayError(null, $this->module->l('We were unable to start your payment. Please try again or contact the merchant.'));
        }
    }

    private function isValidCart($cart)
    {
        if (!Validate::isLoadedObject($cart)) {
            $this->handleInvalidCart($cart, 'Cart object could not be loaded.');
            return false;
        }

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            $this->handleInvalidCart($cart, 'Cart identifiers missing or module inactive.');
            return false;
        }

        if (!$this->cartHasProducts($cart)) {
            $this->handleInvalidCart($cart, 'Cart is empty.');
            return false;
        }

        if (!$this->cartAddressesAreValid($cart)) {
            $this->handleInvalidCart($cart, 'Cart addresses are invalid.');
            return false;
        }

        if (Order::getIdByCartId($cart->id)) {
            return $this->duplicateCartForRetry($cart);
        }

        return true;
    }

    private function handleInvalidCart($cart, $reason)
    {
        $debug = 'Reason: ' . $reason . "\nCustomer Id: " . $cart->id_customer . "\nDelivery Address ID: " . $cart->id_address_delivery . "\nInvoice Address ID: " . $cart->id_address_invoice . "\nModule Active: " . $this->module->active;
        $this->logger->logError('Validation Error', $debug);
        $this->redirectToCheckoutStep(1, $this->module->l('Your shopping cart is no longer available. Please restart the checkout.'));
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

    private function isValidCustomer($customer, Cart $cart)
    {
        if (!Validate::isLoadedObject($customer)) {
            $this->logger->logError('Load a customer', 'Failed to load the customer with ID: ' . $cart->id_customer);
            $this->redirectToCheckoutStep(1, $this->module->l('We could not verify your customer information. Please sign in again.'));
            return false;
        }

        if ((int) $customer->id !== (int) $cart->id_customer) {
            $this->logger->logError('Customer mismatch detected for cart: ' . $cart->id);
            $this->redirectToCheckoutStep(1, $this->module->l('We could not verify your customer information. Please sign in again.'));
            return false;
        }

        if (property_exists($customer, 'active') && !$customer->active) {
            $this->logger->logError('Inactive customer tried to checkout: ' . $customer->id);
            $this->redirectToCheckoutStep(1, $this->module->l('Your account is inactive. Please contact the merchant.'));
            return false;
        }

        if (property_exists($customer, 'deleted') && $customer->deleted) {
            $this->logger->logError('Deleted customer tried to checkout: ' . $customer->id);
            $this->redirectToCheckoutStep(1, $this->module->l('We could not verify your customer information. Please sign in again.'));
            return false;
        }

        return true;
    }

    private function applyBuckarooFee($payment_method, $total)
    {
        $buckarooFee = $this->module->getBuckarooFee($payment_method, $total);

        if (is_array($buckarooFee)) {
            $buckarooFeeTaxIncl = $buckarooFee['buckaroo_fee_tax_incl'];
            $total += $buckarooFeeTaxIncl;

            $computePrecision = Context::getContext()->getComputingPrecision();
            $total = Tools::ps_round($total, $computePrecision);
        }

        return $total;
    }

    private function isValidCurrency($currency): bool
    {
        if (!Validate::isLoadedObject($currency)) {
            $this->logger->logError('Currency is not set in context or invalid.');
            return false;
        }

        return true;
    }

    private function isValidPaymentMethod($payment_method): bool
    {
        if (empty($payment_method)) {
            $this->logger->logError('Load a method', 'Failed to load the method');
            $this->redirectToCheckoutStep(3, $this->module->l('Please select a payment method and try again.'));
            return false;
        }

        $label = $this->paymentMethodRepository->getPaymentMethodsLabel($payment_method);
        if (!$label) {
            $this->logger->logError('Invalid payment method requested: ' . $payment_method);
            $this->redirectToCheckoutStep(3, $this->module->l('The selected payment method is not available.'));
            return false;
        }

        if (!$this->module->isPaymentModeActive($payment_method)) {
            $this->logger->logError('Inactive payment method requested: ' . $payment_method);
            $this->redirectToCheckoutStep(3, $this->module->l('The selected payment method is currently disabled.'));
            return false;
        }

        return true;
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

        $pending = Configuration::get('BUCKAROO_ORDER_STATE_DEFAULT');
        $payment_method_tr = $this->paymentMethodRepository->getPaymentMethodsLabel($payment_method);

        $id_order_cart = Order::getIdByCartId($cart->id);
        if (!$id_order_cart) {
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
        } else {
            $this->module->currentOrder = $id_order_cart;
        }

        $order = new Order($this->module->currentOrder);
        $this->checkout->setReference($order->reference);

        try {
            $this->checkout->setCheckout();
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
        $this->checkout->returnUrl = $this->context->link->getModuleLink('buckaroo3', 'userreturn', [], true);
        $this->checkout->pushUrl = $this->context->link->getModuleLink('buckaroo3', 'return', [], true);
    }

    private function isCurrencyAllowedForMethod($paymentMethod, $currency): bool
    {
        if (!isset($currency->iso_code)) {
            return true;
        }

        $iso = \Tools::strtoupper($currency->iso_code);

        if ($paymentMethod === 'twint') {
            return $iso === 'CHF';
        }

        if ($paymentMethod === 'swish') {
            return $iso === 'SEK';
        }

        if ($paymentMethod === 'wero') {
            return $iso === 'EUR';
        }

        return true;
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

        Tools::redirect($this->context->link->getPageLink('order-confirmation', true, null, [
            'id_cart' => $cartId,
            'id_module' => $this->module->id,
            'id_order' => $id_order,
            'key' => $customer->secure_key,
            'success' => 'true',
            'response_received' => $response->payment_method
        ]));
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
        }

        $msg = $response->statusmessage ?: $this->module->l(
            'Your payment was unsuccessful. Please try again or choose another payment method.'
        );

        $redirectUrl = $this->context->link->getPageLink('order', null, null, [
            'step'               => 3,
            'buckaroo_error_msg' => urlencode($msg),
            'buckaroo_error'     => 1
        ]);

        Tools::redirect($redirectUrl);
    }

    private function updateOrderHistory($response)
    {
        $this->logger->logInfo('Payment request valid');
        $id_order = Order::getIdByCartId($response->getCartId());
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

        if ($response->getResponse() instanceof TransactionResponse) {
            $this->logger->logInfo('Buckaroo error', $response->getSomeError());
        }

        // Back to PrestaShop checkout (payment step)
        Tools::redirect('index.php?controller=order&step=1');
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
        $orderId = Order::getIdByCartId($cartId);

        if ($orderId) {
            $oldCart    = new Cart($cartId);
            $duplication = $oldCart->duplicate();

            if ($duplication && Validate::isLoadedObject($duplication['cart']) && $duplication['success']) {
                $this->context->cookie->id_cart = $duplication['cart']->id;
                $this->context->cookie->write();
            } else {
                $this->logger->logError('Cart duplication failed');
            }
        } else {
            $this->context->cookie->id_cart = (int) $cartId;
            $this->context->cookie->write();
        }
    }

    private function cartHasProducts(Cart $cart): bool
    {
        return (int)$cart->nbProducts() > 0;
    }

    private function cartAddressesAreValid(Cart $cart): bool
    {
        return $this->isValidAddressId($cart->id_address_delivery, 'delivery')
            && $this->isValidAddressId($cart->id_address_invoice, 'invoice');
    }

    private function isValidAddressId($addressId, $type): bool
    {
        if (!Validate::isUnsignedId($addressId)) {
            $this->logger->logError(sprintf('Invalid %s address ID supplied: %s', $type, $addressId));
            return false;
        }

        $address = new Address($addressId);
        if (!Validate::isLoadedObject($address) || (property_exists($address, 'deleted') && $address->deleted)) {
            $this->logger->logError(sprintf('Unable to load %s address with ID %s', $type, $addressId));
            return false;
        }

        return true;
    }

    private function duplicateCartForRetry(Cart $cart): bool
    {
        $oldCart = new Cart($cart->id);
        $duplication = $oldCart->duplicate();

        if ($duplication && Validate::isLoadedObject($duplication['cart']) && $duplication['success']) {
            $this->context->cookie->id_cart = $duplication['cart']->id;
            $this->context->cart = $duplication['cart'];
            $this->context->cookie->write();
            return true;
        }

        $this->handleInvalidCart($cart, 'Failed to duplicate cart for retry.');
        return false;
    }

    private function redirectToCheckoutStep(int $step, ?string $message = null): void
    {
        if ($message !== null) {
            $params = [
                'step' => $step,
                'buckaroo_error_msg' => urlencode($message),
                'buckaroo_error' => 1,
            ];
            $url = $this->context->link->getPageLink('order', null, null, $params);
            Tools::redirect($url);
        }

        Tools::redirect('index.php?controller=order&step=' . (int) $step);
    }
}