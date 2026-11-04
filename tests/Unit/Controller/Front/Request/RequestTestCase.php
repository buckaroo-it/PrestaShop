<?php
/**
 * Shared setup for the request controller tests: a valid cart, customer,
 * configuration and payment method, so each test only changes what it is about.
 */

namespace BuckarooTest\Unit\Controller\Front\Request;

use BuckarooTest\Stubs;
use BuckarooTest\TestCase;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;

require_once __DIR__ . '/RequestTestSupport.php';

abstract class RequestTestCase extends TestCase
{
    public const CART_ID = 5;
    public const CUSTOMER_ID = 7;
    public const DELIVERY_ADDRESS_ID = 11;
    public const INVOICE_ADDRESS_ID = 12;
    public const PENDING_STATE = 3;
    public const SUCCESS_STATE = 2;
    public const FAILED_STATE = 6;
    public const ERROR_STATE = 8;

    /** @var RequestModuleDouble */
    protected $module;

    /** @var PaymentServiceDouble */
    protected $paymentService;

    /** @var ConfigServiceDouble */
    protected $configService;

    /** @var RecordingLogger */
    protected $logger;

    /** @var \Cart */
    protected $cart;

    /** @var FakeCheckout */
    protected $fake;

    /** @var object|null the real checkout Checkout::getInstance() created */
    protected $realCheckout;

    /** @var array<string, string> original Checkout::$payment_method_type */
    private $checkoutTypes;

    protected function setUp(): void
    {
        parent::setUp();
        loadControllers();
        SymfonyContainer::$instance = null;
        $this->checkoutTypes = \Checkout::$payment_method_type;

        $this->paymentService = new PaymentServiceDouble();
        $this->configService = new ConfigServiceDouble();
        $this->module = new RequestModuleDouble();
        $this->module->services = [
            'buckaroo.config.api.payment.service' => $this->paymentService,
            'buckaroo.config.api.config.service' => $this->configService,
            'buckaroo.config.api.fee.service' => new \stdClass(),
        ];
        \Module::$instances['buckaroo3'] = $this->module;

        $this->cart = new \Cart();
        $this->cart->id = self::CART_ID;
        $this->cart->id_customer = self::CUSTOMER_ID;
        $this->cart->id_address_delivery = self::DELIVERY_ADDRESS_ID;
        $this->cart->id_address_invoice = self::INVOICE_ADDRESS_ID;
        $this->cart->products = [['id_product' => 1, 'quantity' => 1]];
        $this->cart->totals = [\Cart::BOTH => 10.0];
        $this->context()->cart = $this->cart;
        $this->context()->currency->name = 'Euro';

        Stubs::object('Customer', self::CUSTOMER_ID, ['secure_key' => 'sk-7', 'active' => 1, 'deleted' => 0]);
        Stubs::object('Address', self::DELIVERY_ADDRESS_ID, ['id_customer' => self::CUSTOMER_ID]);
        Stubs::object('Address', self::INVOICE_ADDRESS_ID, ['id_customer' => self::CUSTOMER_ID]);

        \Configuration::updateValue('BUCKAROO_MERCHANT_KEY', 'merchant');
        \Configuration::updateValue('BUCKAROO_SECRET_KEY', 'secret');
        \Configuration::updateValue('BUCKAROO_ORDER_STATE_DEFAULT', self::PENDING_STATE);
        \Configuration::updateValue('BUCKAROO_ORDER_STATE_SUCCESS', self::SUCCESS_STATE);
        \Configuration::updateValue('BUCKAROO_ORDER_STATE_FAILED', self::FAILED_STATE);
        \Configuration::updateValue('PS_OS_ERROR', self::ERROR_STATE);
        Stubs::object('OrderState', self::SUCCESS_STATE);
        Stubs::object('OrderState', self::FAILED_STATE);
        Stubs::object('OrderState', self::ERROR_STATE);

        Stubs::returns('PaymentModule::getInstalledPaymentModules', [['name' => 'ps_wirepayment'], ['name' => 'buckaroo3']]);
        Stubs::returns('Order::getIdByCartId', 0);
        Stubs::db('/SELECT label FROM/', 'iDEAL');
        Stubs::db('/FROM `ps_bk_configuration`/', '{"mode":"test"}');

        $this->request([], ['method' => 'ideal']);
    }

    protected function tearDown(): void
    {
        \Checkout::$payment_method_type = $this->checkoutTypes;
        SymfonyContainer::$instance = null;
        parent::tearDown();
    }

    protected function controller(): \Buckaroo3RequestModuleFrontController
    {
        $controller = new \Buckaroo3RequestModuleFrontController();
        $this->logger = new RecordingLogger();
        self::setProperty($controller, 'logger', $this->logger);

        return $controller;
    }

    /**
     * Swap the checkout Checkout::getInstance() creates for a FakeCheckout as
     * soon as the controller builds its return URLs.
     */
    protected function useFakeCheckout(\Buckaroo3RequestModuleFrontController $controller): FakeCheckout
    {
        $this->fake = new FakeCheckout();
        $fake = $this->fake;
        $test = $this;
        $this->context()->link = new HookLink(function () use ($controller, $fake, $test) {
            $test->realCheckout = $controller->checkout;
            $fake->adopt($controller->checkout);
            $controller->checkout = $fake;
        });

        return $fake;
    }

    /** Let a payment method build the iDEAL checkout, so tests need no method specific setup. */
    protected function checkoutAsIdeal(string $method): void
    {
        \Checkout::$payment_method_type[$method] = 'IDeal';
    }

    /**
     * Split a redirect to the checkout step into its step and decoded message.
     *
     * @return array{step: int, message: string, error: int}
     */
    protected static function checkoutRedirect(string $url): array
    {
        self::assertStringStartsWith('https://shop.test/order?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return [
            'step' => (int) $query['step'],
            'message' => urldecode((string) $query['buckaroo_error_msg']),
            'error' => (int) $query['buckaroo_error'],
        ];
    }

    /** @return array<int, array> argument lists of every validateOrder() call */
    protected static function validateOrderCalls(): array
    {
        return Stubs::callsTo('PaymentModule->validateOrder');
    }

    /** @return string[] messages of every Message the controller added */
    protected static function addedMessages(): array
    {
        $messages = [];
        foreach (Stubs::persisted('Message', 'add') as $message) {
            $messages[] = $message->message;
        }

        return $messages;
    }

    protected static function orderPayment(array $props): \OrderPayment
    {
        $payment = new \OrderPayment();
        $payment->hydrate($props);

        return $payment;
    }
}
