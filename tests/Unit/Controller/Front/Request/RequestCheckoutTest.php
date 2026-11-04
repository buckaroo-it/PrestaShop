<?php

namespace BuckarooTest\Unit\Controller\Front\Request;

use Buckaroo\PrestaShop\Src\Klarna\ReservationKey;
use Buckaroo\PrestaShop\Src\Repository\RawPaymentMethodRepository;
use Buckaroo\Transaction\Response\TransactionResponse;
use BuckarooTest\Stubs;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;

require_once __DIR__ . '/RequestTestCase.php';

/**
 * Buckaroo3RequestModuleFrontController: order creation, payment start and the
 * handling of the payment response.
 */
class RequestCheckoutTest extends RequestTestCase
{
    private const ORDER_ID = 1000;

    protected function setUp(): void
    {
        parent::setUp();
        Stubs::object('Order', self::ORDER_ID, ['reference' => 'REF1000', 'id_cart' => self::CART_ID, 'payment' => 'iDEAL', 'total_paid_tax_incl' => 10.0, 'conversion_rate' => 1]);
    }

    private function transactionResponse(string $key, array $serviceParameters = []): TransactionResponse
    {
        $response = $this->getMockBuilder(TransactionResponse::class)->disableOriginalConstructor()->getMock();
        $response->method('getTransactionKey')->willReturn($key);
        $response->method('getServiceParameters')->willReturn($serviceParameters);

        return $response;
    }

    private function customer(): \Customer
    {
        return new \Customer(self::CUSTOMER_ID);
    }

    /** A controller holding $fake as its checkout and the order the request created. */
    private function controllerWithCheckout(FakeCheckout $fake): \Buckaroo3RequestModuleFrontController
    {
        $controller = $this->controller();
        $controller->checkout = $fake;
        $this->module->currentOrder = self::ORDER_ID;

        return $controller;
    }

    public function testValidRequestCreatesThePendingOrderAndStartsThePayment(): void
    {
        $controller = $this->controller();
        $fake = $this->useFakeCheckout($controller);
        $fake->redirectRequired = true;
        $fake->response = new FakeResponse();

        $targets = redirectTargets(function () use ($controller) {
            $controller->postProcess();
        });

        self::assertInstanceOf(\IDealCheckout::class, $this->realCheckout);
        self::assertSame([[
            self::CART_ID, self::PENDING_STATE, 10.0, 'iDEAL', null, null, 1, false, 'sk-7',
        ]], self::validateOrderCalls());
        self::assertSame('PrestaShop', $fake->platformName);
        self::assertSame(_PS_VERSION_, $fake->platformVersion);
        self::assertSame('Buckaroo', $fake->moduleSupplier);
        self::assertSame('buckaroo3', $fake->moduleName);
        self::assertSame('5.4.0', $fake->moduleVersion);
        self::assertSame('https://shop.test/module/buckaroo3/userreturn', $fake->returnUrl);
        self::assertSame('https://shop.test/module/buckaroo3/return', $fake->pushUrl);
        self::assertSame('REF1000', $fake->reference);
        self::assertSame(['setCheckout', 'startPayment', 'doRedirect'], $fake->calls);
        self::assertSame(['https://checkout.buckaroo.test/pay'], $targets);
        self::assertSame(self::CART_ID, $this->context()->cookie->id_cart);
        self::assertContains('Start the payment process', $this->logger->messages('info'));
        self::assertSame("Currency: Euro\nTotal Amount: 10\nPayment Method: ideal", $this->logger->descriptionOf('Checkout info'));
    }

    public function testVerifyRequestStartsVerificationWithoutCreatingAnOrder(): void
    {
        $controller = $this->controller();
        $fake = $this->useFakeCheckout($controller);
        $fake->verifyRequired = true;
        $fake->redirectRequired = true;
        $fake->response = new FakeResponse();

        redirectTargets(function () use ($controller) {
            $controller->postProcess();
        });

        self::assertSame([], self::validateOrderCalls());
        self::assertSame(['setCheckout', 'startVerify', 'doRedirect'], $fake->calls);
        self::assertSame(['cid' => self::CUSTOMER_ID, 'cart' => self::CART_ID], $fake->verifyArgs);
        self::assertContains('Start verify process', $this->logger->messages('info'));
    }

    public function testCheckoutThatCannotBeCreatedShowsTheError(): void
    {
        Stubs::$dbResponders = [];
        Stubs::db('/SELECT label FROM/', 'iDEAL');
        $controller = $this->controller();

        self::invoke($controller, 'initializeCheckout', [$this->cart, 'ideal', $this->context()->currency, 10.0, $this->customer()]);

        self::assertNull($controller->checkout);
        self::assertSame([], self::validateOrderCalls());
        $description = $this->logger->descriptionOf('Set checkout info: ');
        self::assertSame('ideal', $description['payment_method']);
        self::assertSame('module:buckaroo3/views/templates/front/error.tpl', $controller->template);
        self::assertSame('Configuration not found for payment id ', $this->context()->smarty->getTemplateVars('error_message'));
    }

    /**
     * Regression: postProcess() used to call isRequestSucceeded() on the null
     * checkout, an Error that escaped catch (Exception) as a fatal 500.
     */
    public function testPostProcessStopsWithTheErrorPageWhenTheCheckoutCannotBeCreated(): void
    {
        Stubs::$dbResponders = [];
        Stubs::db('/SELECT label FROM/', 'iDEAL');
        $controller = $this->controller();

        $targets = redirectTargets(function () use ($controller) {
            $controller->postProcess();
        });

        self::assertSame([], $targets);
        self::assertNull($controller->checkout);
        self::assertSame([], self::validateOrderCalls());
        self::assertSame(['Set checkout info: '], $this->logger->messages('error'));
        self::assertSame('module:buckaroo3/views/templates/front/error.tpl', $controller->template);
        self::assertSame('Configuration not found for payment id ', $this->context()->smarty->getTemplateVars('error_message'));
    }

    public function testFailedOrderValidationShowsTheErrorAndStartsNoPayment(): void
    {
        Stubs::throws('PaymentModule->validateOrder', new \RuntimeException('Cart cannot be loaded'));
        $controller = $this->controller();
        $fake = $this->useFakeCheckout($controller);

        self::invoke($controller, 'initializeCheckout', [$this->cart, 'ideal', $this->context()->currency, 10.0, $this->customer()]);

        self::assertSame([], $fake->calls);
        $description = $this->logger->descriptionOf('Order validation failed: ');
        self::assertSame('5', $description['cart_id']);
        self::assertSame('ideal', $description['payment_method']);
        self::assertSame('Cart cannot be loaded', $this->context()->smarty->getTemplateVars('error_message'));
    }

    /**
     * @dataProvider checkoutErrorProvider
     */
    public function testPaymentStartErrorShowsTheError(string $property): void
    {
        $controller = $this->controller();
        $fake = $this->useFakeCheckout($controller);
        $fake->{$property} = new \Exception('Invalid issuer');

        self::invoke($controller, 'initializeCheckout', [$this->cart, 'ideal', $this->context()->currency, 10.0, $this->customer()]);

        self::assertSame('ideal', $this->logger->descriptionOf('Set checkout info: ')['payment_method']);
        self::assertSame('Invalid issuer', $this->context()->smarty->getTemplateVars('error_message'));
    }

    public function checkoutErrorProvider(): array
    {
        return [
            'setCheckout' => ['setCheckoutError'],
            'startPayment' => ['startPaymentError'],
        ];
    }

    public function testFailedRequestAfterPostProcessReturnsToThePaymentStep(): void
    {
        $controller = $this->controller();
        $fake = $this->useFakeCheckout($controller);
        $fake->requestSucceeded = false;

        $targets = redirectTargets(function () use ($controller) {
            $controller->postProcess();
        });

        $redirect = self::checkoutRedirect($targets[0]);
        self::assertSame(3, $redirect['step']);
        self::assertSame('Your payment was unsuccessful. Please try again or choose another payment method.', $redirect['message']);
        self::assertContains('Request not succeeded', $this->logger->messages('info'));
    }

    public function testSuccessfulPaymentWithoutRedirectGoesToTheOrderConfirmation(): void
    {
        $fake = new FakeCheckout();
        $fake->response = new FakeResponse();
        $fake->response->transactionResponse = $this->transactionResponse(' TK-1 ');
        $this->context()->cookie->buckaroo_giftcard_applied = '1';
        $this->context()->cookie->buckaroo_giftcard_cart_id = self::CART_ID;
        $controller = $this->controllerWithCheckout($fake);

        $targets = redirectTargets(function () use ($controller) {
            self::invoke($controller, 'handleSuccessfulRequest', [self::CART_ID, $this->customer()]);
        });

        self::assertSame(
            'https://shop.test/order-confirmation?id_cart=5&id_module=77&id_order=1000&key=sk-7&success=true&response_received=ideal',
            $targets[0]
        );
        self::assertSame(['Transaction Key: TK-1'], self::addedMessages());
        self::assertSame(self::ORDER_ID, Stubs::persisted('Message', 'add')[0]->id_order);
        self::assertSame([], Stubs::persisted('OrderPayment'), 'iDEAL books the key with the push');
        self::assertSame([], $this->context()->cookie->values, 'giftcard cookies are cleared');
        self::assertCount(1, Stubs::persisted('Order', 'save'), 'total_paid_real is synchronised');
    }

    public function testSepaDirectDebitStoresTheKeyAndTheMandate(): void
    {
        $this->request([], ['method' => 'sepadirectdebit']);
        $fake = new FakeCheckout();
        $fake->response = new FakeResponse();
        $fake->response->payment_method = 'SepaDirectDebit';
        $fake->response->transactionResponse = $this->transactionResponse('TK-SEPA', ['mandateReference' => 'M-1', 'mandateDate' => '2026-11-04']);
        $payment = self::orderPayment(['id' => 4, 'transaction_id' => '', 'amount' => 0]);
        Stubs::returns('OrderPayment::getByOrderReference', function ($reference) use ($payment) {
            return $reference === 'REF1000' ? [$payment] : [];
        });
        $controller = $this->controllerWithCheckout($fake);

        $targets = redirectTargets(function () use ($controller) {
            self::invoke($controller, 'handleSuccessfulRequest', [self::CART_ID, $this->customer()]);
        });

        self::assertStringContainsString('response_received=SepaDirectDebit', $targets[0]);
        self::assertSame(['Transaction Key: TK-SEPA', 'MandateReference: M-1', 'MandateDate: 2026-11-04'], self::addedMessages());
        self::assertSame('TK-SEPA', $payment->transaction_id);
        self::assertSame([$payment], Stubs::persisted('OrderPayment', 'update'));
    }

    public function testSepaDirectDebitWithoutMandateDataAddsOnlyTheKey(): void
    {
        $fake = new FakeCheckout();
        $fake->response = new FakeResponse();
        $fake->response->payment_method = 'SepaDirectDebit';
        $fake->response->transactionResponse = $this->transactionResponse('TK-SEPA');
        $controller = $this->controllerWithCheckout($fake);

        redirectTargets(function () use ($controller) {
            self::invoke($controller, 'handleSuccessfulRequest', [self::CART_ID, $this->customer()]);
        });

        self::assertSame(['Transaction Key: TK-SEPA'], self::addedMessages());
    }

    public function testTransferKeepsTheInstructionsAndBooksTheKeyOnANewPayment(): void
    {
        $this->request([], ['method' => 'transfer']);
        $fake = new FakeCheckout();
        $fake->response = new FakeResponse();
        $fake->response->payment_method = 'transfer';
        $fake->response->consumerMessage = ['HtmlText' => '<p>Pay to NL00BANK</p>'];
        $fake->response->transactionResponse = $this->transactionResponse('TK-TR');
        $controller = $this->controllerWithCheckout($fake);

        redirectTargets(function () use ($controller) {
            self::invoke($controller, 'handleSuccessfulRequest', [self::CART_ID, $this->customer()]);
        });

        self::assertSame('<p>Pay to NL00BANK</p>', $this->context()->cookie->HtmlText);
        $added = Stubs::persisted('OrderPayment', 'add');
        self::assertCount(1, $added);
        self::assertSame('REF1000', $added[0]->order_reference);
        self::assertSame('TK-TR', $added[0]->transaction_id);
        self::assertSame(0, $added[0]->amount, 'nothing is collected before the push');
        self::assertSame('iDEAL', $added[0]->payment_method);
        self::assertSame(1.0, $added[0]->conversion_rate);
    }

    public function testSuccessfulPaymentWithoutTransactionResponseStoresNoKey(): void
    {
        $this->request([], ['method' => 'payperemail']);
        $fake = new FakeCheckout();
        $fake->response = new FakeResponse();
        $controller = $this->controllerWithCheckout($fake);

        redirectTargets(function () use ($controller) {
            self::invoke($controller, 'handleSuccessfulRequest', [self::CART_ID, $this->customer()]);
        });

        self::assertSame(['Transaction Key: '], self::addedMessages());
        self::assertSame([], Stubs::persisted('OrderPayment'));
    }

    public function testKlarnaRedirectStoresTheReservationKeyBeforeRedirecting(): void
    {
        $this->request([], ['method' => 'Klarna']);
        $fake = new FakeCheckout();
        $fake->redirectRequired = true;
        $fake->response = new FakeResponse();
        $fake->response->transactionResponse = $this->transactionResponse('DATA-REQ-1');
        Stubs::returns('OrderPayment::getByOrderReference', false);
        $controller = $this->controllerWithCheckout($fake);

        $targets = redirectTargets(function () use ($controller) {
            self::invoke($controller, 'handleSuccessfulRequest', [self::CART_ID, $this->customer()]);
        });

        self::assertSame(['https://checkout.buckaroo.test/pay'], $targets);
        self::assertSame([ReservationKey::message('DATA-REQ-1')], self::addedMessages());
        $added = Stubs::persisted('OrderPayment', 'add');
        self::assertCount(1, $added);
        self::assertSame(10.0, $added[0]->amount, 'Klarna books the order total on the payment');
        self::assertSame('DATA-REQ-1', $added[0]->transaction_id);
        self::assertSame(self::CART_ID, $this->context()->cookie->id_cart);
        self::assertContains('Redirecting ... ', $this->logger->messages('info'));
    }

    /**
     * @dataProvider klarnaWithoutKeyProvider
     */
    public function testRedirectWithoutKlarnaKeyStoresNothing(string $method, ?string $key): void
    {
        $this->request([], ['method' => $method]);
        $fake = new FakeCheckout();
        $fake->redirectRequired = true;
        $fake->response = new FakeResponse();
        $fake->response->transactionResponse = $key === null ? null : $this->transactionResponse($key);
        $controller = $this->controllerWithCheckout($fake);

        redirectTargets(function () use ($controller) {
            self::invoke($controller, 'handleSuccessfulRequest', [self::CART_ID, $this->customer()]);
        });

        self::assertSame([], self::addedMessages());
        self::assertSame([], Stubs::persisted('OrderPayment'));
    }

    public function klarnaWithoutKeyProvider(): array
    {
        return [
            'other method' => ['ideal', 'TK'],
            'klarna without response' => ['klarna', null],
            'klarna with empty key' => ['klarna', '  '],
        ];
    }

    public function testFailedPaymentCancelsThePendingOrder(): void
    {
        $fake = new FakeCheckout();
        $fake->response = new FakeResponse();
        $fake->response->succeeded = false;
        $fake->response->cartId = self::CART_ID;
        $fake->response->status = \BuckarooAbstract::BUCKAROO_FAILED;
        $fake->response->statusmessage = 'Payment cancelled by consumer';
        Stubs::returns('Order::getIdByCartId', function ($cartId) {
            return $cartId === self::CART_ID ? self::ORDER_ID : 0;
        });
        Stubs::object('Order', self::ORDER_ID, ['reference' => 'REF1000', 'current_state' => self::PENDING_STATE]);
        $newCart = new \Cart();
        $newCart->id = 9;
        Stubs::returns('Cart->duplicate', ['cart' => $newCart, 'success' => true]);
        $controller = $this->controllerWithCheckout($fake);

        $targets = redirectTargets(function () use ($controller) {
            self::invoke($controller, 'handleSuccessfulRequest', [self::CART_ID, $this->customer()]);
        });

        $redirect = self::checkoutRedirect($targets[0]);
        self::assertSame(3, $redirect['step']);
        self::assertSame('Payment cancelled by consumer', $redirect['message']);
        self::assertSame(9, $this->context()->cookie->id_cart, 'the shopper continues with a copy of the cart');
        self::assertSame([[self::FAILED_STATE, self::ORDER_ID]], Stubs::callsTo('OrderHistory->changeIdOrderState'));
        self::assertCount(1, Stubs::persisted('OrderHistory', 'add'));
        self::assertSame(self::ORDER_ID, Stubs::persisted('OrderHistory', 'add')[0]->id_order);
        self::assertContains('Update order history with status: ' . self::FAILED_STATE, $this->logger->messages('info'));
    }

    /**
     * @dataProvider unchangedStateProvider
     */
    public function testFailedPaymentLeavesOrdersOutsideThePendingStatesAlone(int $currentState): void
    {
        $fake = new FakeCheckout();
        $fake->response = new FakeResponse();
        $fake->response->succeeded = false;
        $fake->response->status = \BuckarooAbstract::BUCKAROO_FAILED;
        Stubs::returns('Order::getIdByCartId', self::ORDER_ID);
        Stubs::object('Order', self::ORDER_ID, ['current_state' => $currentState]);
        Stubs::returns('Cart->duplicate', false);
        $controller = $this->controllerWithCheckout($fake);

        redirectTargets(function () use ($controller) {
            self::invoke($controller, 'handleSuccessfulRequest', [self::CART_ID, $this->customer()]);
        });

        self::assertSame([], Stubs::persisted('OrderHistory'));
        self::assertContains('Cart duplication failed', $this->logger->messages('error'));
    }

    public function unchangedStateProvider(): array
    {
        return [
            'already failed' => [self::FAILED_STATE],
            'paid' => [self::SUCCESS_STATE],
        ];
    }

    public function testFailedPaymentWithoutOrderOrValidationUsesTheGenericMessage(): void
    {
        $fake = new FakeCheckout();
        $fake->response = new FakeResponse();
        $fake->response->succeeded = false;
        $controller = $this->controllerWithCheckout($fake);

        $targets = redirectTargets(function () use ($controller) {
            self::invoke($controller, 'handleSuccessfulRequest', [self::CART_ID, $this->customer()]);
        });

        self::assertSame('Your payment was unsuccessful. Please try again or choose another payment method.', self::checkoutRedirect($targets[0])['message']);
        self::assertSame('Order not found.', $this->logger->descriptionOf('Find order by cart ID'));
        self::assertSame(self::CART_ID, $this->context()->cookie->id_cart);

        Stubs::$calls = [];
        $this->logger->entries = [];
        $fake->response->valid = false;
        redirectTargets(function () use ($controller) {
            self::invoke($controller, 'handleSuccessfulRequest', [self::CART_ID, $this->customer()]);
        });
        self::assertNotContains('Payment request valid', $this->logger->messages('info'));
    }

    public function testFailedRequestShowsTheBuckarooError(): void
    {
        $fake = new FakeCheckout();
        $fake->response = new FakeResponse();
        $fake->response->transactionResponse = $this->transactionResponse('TK');
        $fake->response->someError = 'Card expired';
        $controller = $this->controllerWithCheckout($fake);

        $targets = redirectTargets(function () use ($controller) {
            self::invoke($controller, 'handleFailedRequest', [self::CART_ID]);
        });

        self::assertSame('Card expired', self::checkoutRedirect($targets[0])['message']);
        self::assertSame('Card expired', $this->logger->descriptionOf('Buckaroo error'));
    }

    /**
     * @dataProvider genericFailureProvider
     */
    public function testFailedRequestFallsBackToTheGenericMessage(bool $withCheckout, bool $withTransaction, string $error): void
    {
        $controller = $this->controller();
        if ($withCheckout) {
            $controller->checkout = new FakeCheckout();
            $controller->checkout->response = new FakeResponse();
            $controller->checkout->response->someError = $error;
            if ($withTransaction) {
                $controller->checkout->response->transactionResponse = $this->transactionResponse('TK');
            }
        }

        $targets = redirectTargets(function () use ($controller) {
            self::invoke($controller, 'handleFailedRequest', [self::CART_ID]);
        });

        self::assertSame('Your payment was unsuccessful. Please try again or choose another payment method.', self::checkoutRedirect($targets[0])['message']);
        self::assertNull($this->logger->descriptionOf('Buckaroo error'));
    }

    public function genericFailureProvider(): array
    {
        return [
            'no checkout (SDK failed early)' => [false, false, ''],
            'no transaction response' => [true, false, 'ignored'],
            'transaction response without error' => [true, true, ''],
        ];
    }

    public function testStoringTheKeySkipsPaymentsThatAlreadyHaveOne(): void
    {
        $booked = self::orderPayment(['transaction_id' => 'OLD']);
        $open = self::orderPayment(['transaction_id' => '']);
        Stubs::returns('OrderPayment::getByOrderReference', [$booked, $open]);
        $controller = $this->controller();

        self::invoke($controller, 'storeTransactionKeyOnOrderPayment', [self::ORDER_ID, 'NEW']);

        self::assertSame('OLD', $booked->transaction_id);
        self::assertSame('NEW', $open->transaction_id);
        self::assertSame([$open], Stubs::persisted('OrderPayment'));
    }

    /**
     * @dataProvider noKeyStorageProvider
     */
    public function testStoringTheKeyNeedsAnOrderAndAKey(int $orderId, string $key): void
    {
        self::invoke($this->controller(), 'storeTransactionKeyOnOrderPayment', [$orderId, $key]);

        self::assertSame([], Stubs::persisted('OrderPayment'));
        self::assertSame(0, Stubs::callCount('OrderPayment::getByOrderReference'));
    }

    public function noKeyStorageProvider(): array
    {
        return [
            'no order id' => [0, 'TK'],
            'no key' => [self::ORDER_ID, ''],
            'unknown order' => [404, 'TK'],
        ];
    }

    public function testNewPaymentFallsBackToTheModuleNameAndCurrencyRate(): void
    {
        Stubs::object('Order', 1001, ['reference' => 'R', 'payment' => '', 'conversion_rate' => 0, 'id_currency' => 2]);

        self::invoke($this->controller(), 'storeTransactionKeyOnOrderPayment', [1001, 'TK']);

        $added = Stubs::persisted('OrderPayment', 'add')[0];
        self::assertSame('buckaroo3', $added->payment_method);
        self::assertSame(1.0, $added->conversion_rate);
        self::assertSame(2, $added->id_currency);
    }

    public function testRedirectToCheckoutStepWithoutMessage(): void
    {
        $controller = $this->controller();

        $targets = redirectTargets(function () use ($controller) {
            self::invoke($controller, 'redirectToCheckoutStep', [2]);
        });

        self::assertSame(['index.php?controller=order&step=2'], $targets);
    }

    public function testPaymentMethodRepositoryComesFromTheContainerWhenRegistered(): void
    {
        $repository = new class extends RawPaymentMethodRepository {
            public function getPaymentMethodsLabel($name)
            {
                return 'Label of ' . $name;
            }
        };
        SymfonyContainer::$instance = new ContainerDouble(['buckaroo.repository.raw_payment_method' => $repository]);

        $controller = $this->controller();

        self::assertSame($repository, self::getProperty($controller, 'paymentMethodRepository'));
    }

    public function testPaymentMethodRepositoryIsCreatedWithoutContainer(): void
    {
        self::assertInstanceOf(RawPaymentMethodRepository::class, self::getProperty($this->controller(), 'paymentMethodRepository'));
    }
}
