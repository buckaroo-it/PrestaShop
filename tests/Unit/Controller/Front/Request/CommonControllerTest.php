<?php

namespace BuckarooTest\Unit\Controller\Front\Request;

use BuckarooTest\Stubs;
use BuckarooTest\TestCase;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;

require_once __DIR__ . '/RequestTestSupport.php';

/**
 * BuckarooCommonController: confirmation pages, giftcard and push payment
 * bookkeeping, and moving a pending order to paid.
 */
class CommonControllerTest extends TestCase
{
    private const ORDER_ID = 40;
    private const CART_ID = 5;
    private const PENDING = 3;
    private const PAID = 2;
    private const FAILED = 6;
    private const ERROR = 8;
    private const OUT_OF_STOCK_UNPAID = 9;

    /** @var RequestModuleDouble */
    private $module;

    /** @var ExposedCommonController */
    private $controller;

    protected function setUp(): void
    {
        parent::setUp();
        loadControllers();
        SymfonyContainer::$instance = null;
        $this->module = new RequestModuleDouble();
        \Module::$instances['buckaroo3'] = $this->module;
        $this->controller = new ExposedCommonController();

        \Configuration::updateValue('BUCKAROO_ORDER_STATE_DEFAULT', self::PENDING);
        \Configuration::updateValue('BUCKAROO_ORDER_STATE_SUCCESS', self::PAID);
        \Configuration::updateValue('BUCKAROO_ORDER_STATE_FAILED', self::FAILED);
        \Configuration::updateValue('PS_OS_ERROR', self::ERROR);
        \Configuration::updateValue('PS_OS_OUTOFSTOCK_UNPAID', self::OUT_OF_STOCK_UNPAID);
        Stubs::object('OrderState', self::PAID);
    }

    protected function tearDown(): void
    {
        SymfonyContainer::$instance = null;
        parent::tearDown();
    }

    private function order(array $props = []): void
    {
        Stubs::object('Order', self::ORDER_ID, $props + [
            'reference' => 'REF40',
            'id_cart' => self::CART_ID,
            'id_currency' => 1,
            'total_paid' => 100.0,
            'total_paid_real' => 0.0,
            'payment' => 'iDEAL',
        ]);
    }

    private static function payment(array $props): \OrderPayment
    {
        $payment = new \OrderPayment();
        $payment->hydrate($props);

        return $payment;
    }

    private static function savedOrder(): \Order
    {
        $saved = Stubs::persisted('Order', 'save');
        self::assertNotEmpty($saved, 'the order is saved');

        return end($saved);
    }

    private function groupRows(array $rows): void
    {
        Stubs::db('/FROM `ps_bk_group_transaction`/', $rows);
    }

    // ---------------------------------------------------------------- services

    public function testPaymentServiceComesFromTheModuleContainer(): void
    {
        $service = new PaymentServiceDouble();
        $this->module->services['buckaroo.config.api.payment.service'] = $service;

        self::assertSame($service, $this->controller->call('getBuckarooPaymentService'));
    }

    /**
     * @dataProvider moduleWithoutServiceProvider
     */
    public function testPaymentServiceFallsBackToThePrestaShopContainer(bool $registerWrongType): void
    {
        if ($registerWrongType) {
            $this->module->services['buckaroo.config.api.payment.service'] = new \stdClass();
        }
        $service = new PaymentServiceDouble();
        SymfonyContainer::$instance = new ContainerDouble(['buckaroo.config.api.payment.service' => $service]);

        self::assertSame($service, $this->controller->call('getBuckarooPaymentService'));
    }

    public function moduleWithoutServiceProvider(): array
    {
        return [
            'module container throws' => [false],
            'module returns something else' => [true],
        ];
    }

    // ----------------------------------------------------------- confirmation

    public function testTransferConfirmationShowsTheBankInstructions(): void
    {
        $this->order();
        Stubs::returns('Order::getIdByCartId', function ($cartId) {
            return $cartId === self::CART_ID ? self::ORDER_ID : 0;
        });
        $customer = new \Customer();
        $customer->id = 7;
        $this->context()->customer = $customer;
        $response = new FakeResponse();
        $response->cartId = self::CART_ID;
        $response->consumerMessage = ['HtmlText' => '<b>IBAN</b>'];

        $this->controller->call('displayConfirmationTransfer', [$response]);

        $smarty = $this->context()->smarty;
        self::assertFalse($smarty->getTemplateVars('is_guest'));
        self::assertSame(self::ORDER_ID, $smarty->getTemplateVars('order')->id);
        self::assertSame('<b>IBAN</b>', $smarty->getTemplateVars('message'));
        self::assertSame('order-confirmation-transfer.tpl', $this->controller->template);
    }

    public function testTransferConfirmationWithoutInstructionsForAGuest(): void
    {
        $customer = new \Customer();
        $customer->id = 7;
        $customer->is_guest = 1;
        $this->context()->customer = $customer;

        $this->controller->call('displayConfirmationTransfer', [new FakeResponse()]);

        self::assertTrue($this->context()->smarty->getTemplateVars('is_guest'));
        self::assertSame('', $this->context()->smarty->getTemplateVars('message'));
        self::assertNull($this->context()->smarty->getTemplateVars('order')->id);
    }

    public function testConfirmationShowsTheFormattedTotal(): void
    {
        $this->order();
        Stubs::returns('Order->getOrdersTotalPaid', 12.5);
        $this->context()->customer = new \Customer();

        $this->controller->call('displayConfirmation', [self::ORDER_ID]);

        $smarty = $this->context()->smarty;
        self::assertSame('EUR 12.50', $smarty->getTemplateVars('price'));
        self::assertTrue($smarty->getTemplateVars('is_guest'), 'a customer without id counts as guest');
        self::assertSame(self::ORDER_ID, $smarty->getTemplateVars('order')->id);
        self::assertSame('order-confirmation.tpl', $this->controller->template);
    }

    public function testErrorPageWithDefaultAndCustomMessage(): void
    {
        $this->controller->call('displayError');

        self::assertNull($this->context()->smarty->getTemplateVars('order_id'));
        self::assertSame(
            'Your payment was unsuccessful. Please try again or choose another payment method.',
            $this->context()->smarty->getTemplateVars('error_message')
        );
        self::assertSame('module:buckaroo3/views/templates/front/error.tpl', $this->controller->template);

        $this->controller->call('displayError', ['INV-1', 'Card declined']);

        self::assertSame('INV-1', $this->context()->smarty->getTemplateVars('order_id'));
        self::assertSame('Card declined', $this->context()->smarty->getTemplateVars('error_message'));
    }

    public function testInitContentRunsTheParent(): void
    {
        $this->controller->initContent();

        self::assertNull($this->controller->template);
    }

    // ------------------------------------------------------- payment records

    /**
     * @dataProvider orderPaymentExistsProvider
     */
    public function testOrderPaymentExists(string $key, $payments, bool $expected): void
    {
        Stubs::returns('OrderPayment::getByOrderReference', $payments);

        self::assertSame($expected, $this->controller->call('orderPaymentExists', ['REF40', $key]));
    }

    public function orderPaymentExistsProvider(): array
    {
        return [
            'empty key' => ['', [self::payment(['transaction_id' => ''])], false],
            'no payments' => ['TK', false, false],
            'matching payment' => ['TK', [self::payment(['transaction_id' => 'X']), self::payment(['transaction_id' => 'TK'])], true],
            'other payments' => ['TK', [self::payment(['transaction_id' => 'X'])], false],
        ];
    }

    public function testGroupTransactionsAreBookedAsOrderPayments(): void
    {
        $this->order();
        $this->groupRows([
            ['transaction_key' => 'GC-1', 'amount' => '25.00', 'refunded_amount' => '5.00', 'card_code' => 'fashioncheque'],
            ['transaction_key' => 'GC-2', 'amount' => '10.00', 'refunded_amount' => '0', 'card_code' => ''],
            ['transaction_key' => 'GC-3', 'amount' => '10.00', 'refunded_amount' => '10.00', 'card_code' => 'vvv'],
            ['transaction_key' => '', 'amount' => '10.00', 'refunded_amount' => '0', 'card_code' => 'vvv'],
            ['transaction_key' => 'GC-OLD', 'amount' => '10.00', 'refunded_amount' => '0', 'card_code' => 'vvv'],
        ]);
        Stubs::returns('OrderPayment::getByOrderReference', [self::payment(['transaction_id' => 'GC-OLD'])]);

        $this->controller->call('recordGroupTransactionPayments', [self::ORDER_ID]);

        $saved = Stubs::persisted('OrderPayment', 'save');
        self::assertCount(2, $saved);
        self::assertSame(['GC-1', 20.0, 'fashioncheque', 'REF40'], [$saved[0]->transaction_id, $saved[0]->amount, $saved[0]->payment_method, $saved[0]->order_reference]);
        self::assertSame(['GC-2', 10.0, 'giftcard'], [$saved[1]->transaction_id, $saved[1]->amount, $saved[1]->payment_method]);
        self::assertSame(1, $saved[0]->conversion_rate);
        $updates = Stubs::sql('update');
        self::assertCount(1, $updates);
        self::assertStringContainsString('UPDATE `ps_bk_group_transaction` {"order_id":40} WHERE `cart_id` = 5', $updates[0]);
    }

    /**
     * @dataProvider orderWithoutCartProvider
     */
    public function testGroupTransactionsNeedALoadedOrderWithCart(bool $loaded): void
    {
        if ($loaded) {
            $this->order(['id_cart' => 0]);
        }

        $this->controller->call('recordGroupTransactionPayments', [self::ORDER_ID]);

        self::assertSame([], Stubs::$queries);
    }

    public function orderWithoutCartProvider(): array
    {
        return ['unknown order' => [false], 'order without cart' => [true]];
    }

    public function testPaidPushIsBookedAsOrderPayment(): void
    {
        $this->order();
        $response = new FakeResponse();
        $response->transactions = 'TK-PUSH';
        $response->amount = '12.34';
        $response->payment_method = 'IDEAL';

        self::assertTrue($this->controller->call('recordPushPayment', [self::ORDER_ID, $response]));

        $saved = Stubs::persisted('OrderPayment', 'save')[0];
        self::assertSame(['TK-PUSH', 12.34, 'ideal', 'REF40', 1], [$saved->transaction_id, $saved->amount, $saved->payment_method, $saved->order_reference, $saved->id_currency]);
    }

    /**
     * @dataProvider unbookablePushProvider
     */
    public function testPushPaymentIsNotBooked(array $responseProps, bool $orderExists, $existingPayments): void
    {
        if ($orderExists) {
            $this->order();
        }
        Stubs::returns('OrderPayment::getByOrderReference', $existingPayments);
        $response = new FakeResponse();
        foreach ($responseProps as $key => $value) {
            $response->{$key} = $value;
        }

        self::assertFalse($this->controller->call('recordPushPayment', [self::ORDER_ID, $response]));
        self::assertSame([], Stubs::persisted('OrderPayment'));
    }

    public function unbookablePushProvider(): array
    {
        $paid = ['transactions' => 'TK', 'amount' => '10', 'payment_method' => 'ideal'];

        return [
            'no transaction key' => [['transactions' => null] + $paid, true, []],
            'group transaction (I150)' => [['brq_transaction_type' => 'I150'] + $paid, true, []],
            'group transaction (no method)' => [['payment_method' => ''] + $paid, true, []],
            'unknown order' => [$paid, false, []],
            'already booked' => [$paid, true, [self::payment(['transaction_id' => 'TK'])]],
            'zero amount' => [['amount' => '0'] + $paid, true, []],
        ];
    }

    /**
     * @dataProvider isGroupTransactionProvider
     */
    public function testIsGroupTransactionResponse($response, bool $expected): void
    {
        self::assertSame($expected, $this->controller->call('isGroupTransactionResponse', [$response]));
    }

    public function isGroupTransactionProvider(): array
    {
        return [
            'not an object' => [null, false],
            'I150' => [(object) ['brq_transaction_type' => 'I150', 'payment_method' => 'ideal'], true],
            'no payment method' => [(object) ['payment_method' => '  '], true],
            'regular payment' => [(object) ['brq_transaction_type' => 'C021', 'payment_method' => 'ideal'], false],
        ];
    }

    /**
     * @dataProvider totalPaidRealProvider
     */
    public function testTotalPaidRealFollowsTheBookedPayments($payments, float $totalPaid, float $expected): void
    {
        $this->order(['total_paid' => $totalPaid]);
        Stubs::returns('OrderPayment::getByOrderReference', $payments);

        $this->controller->call('syncOrderTotalPaidReal', [self::ORDER_ID]);

        self::assertSame($expected, self::savedOrder()->total_paid_real);
    }

    public function totalPaidRealProvider(): array
    {
        return [
            'payments match the total within rounding' => [[self::payment(['amount' => '60.00']), self::payment(['amount' => '39.996'])], 100.0, 100.0],
            'partial payments' => [[self::payment(['amount' => '60.00'])], 100.0, 60.0],
            'no payments' => [false, 100.0, 0.0],
            'free order' => [[self::payment(['amount' => '0'])], 0.0, 0.0],
        ];
    }

    public function testSyncingAnUnknownOrderDoesNothing(): void
    {
        $this->controller->call('syncOrderTotalPaidReal', [self::ORDER_ID]);

        self::assertSame([], Stubs::persisted('Order'));
    }

    public function testPreparingPaymentsBooksGroupTransactionsAndThePaidPush(): void
    {
        $this->order();
        $this->groupRows([['transaction_key' => 'GC-1', 'amount' => '40', 'refunded_amount' => '0', 'card_code' => 'vvv']]);
        $response = new FakeResponse();
        $response->paid = true;
        $response->transactions = 'TK-REST';
        $response->amount = '60';

        $this->controller->call('prepareOrderPaymentsBeforePaidStatus', [self::ORDER_ID, $response]);

        $keys = array_map(function ($payment) {
            return $payment->transaction_id;
        }, Stubs::persisted('OrderPayment', 'save'));
        self::assertSame(['GC-1', 'TK-REST'], $keys);
        self::assertCount(1, Stubs::persisted('Order', 'save'));
    }

    public function testUnpaidResponseIsNotBooked(): void
    {
        $this->order();
        $response = new FakeResponse();
        $response->transactions = 'TK';
        $response->amount = '60';

        $this->controller->call('prepareOrderPaymentsBeforePaidStatus', [self::ORDER_ID, $response]);

        self::assertSame([], Stubs::persisted('OrderPayment'));
    }

    public function testAppliedGiftcardIsRecordedAndItsCookiesCleared(): void
    {
        $this->order();
        $cookie = $this->context()->cookie;
        foreach (['group_tx', 'remainder', 'applied', 'tx_key', 'card_code', 'cart_id'] as $suffix) {
            $cookie->{'buckaroo_giftcard_' . $suffix} = 'x';
        }
        $cookie->id_cart = self::CART_ID;

        $this->controller->call('recordAppliedGiftcardPayment', [self::ORDER_ID]);

        self::assertSame(['id_cart' => self::CART_ID], $cookie->values);
        self::assertSame(1, $cookie->writes);
        self::assertCount(1, Stubs::persisted('Order', 'save'));
    }

    // ---------------------------------------------------------------- paid?

    /**
     * @dataProvider effectivePaidProvider
     */
    public function testEffectivePaidAmount(int $cartId, float $totalPaidReal, array $groupRows, float $expected): void
    {
        $order = new \Order();
        $order->id_cart = $cartId;
        $order->total_paid_real = $totalPaidReal;
        $this->groupRows($groupRows);

        self::assertSame($expected, $this->controller->call('getEffectivePaidAmount', [$order]));
    }

    public function effectivePaidProvider(): array
    {
        $giftcard = [['transaction_key' => 'GC', 'amount' => '30', 'refunded_amount' => '0']];

        return [
            'order without cart' => [0, 20.0, $giftcard, 20.0],
            'no giftcard' => [self::CART_ID, 20.0, [], 20.0],
            'giftcard not yet booked' => [self::CART_ID, 20.0, $giftcard, 50.0],
            'giftcard already booked' => [self::CART_ID, 50.0, $giftcard, 50.0],
        ];
    }

    /**
     * @dataProvider fullyPaidProvider
     */
    public function testIsOrderFullyPaid(float $totalPaid, float $totalPaidReal, bool $expected): void
    {
        $order = new \Order();
        $order->id_cart = 0;
        $order->total_paid = $totalPaid;
        $order->total_paid_real = $totalPaidReal;

        self::assertSame($expected, $this->controller->call('isOrderFullyPaid', [$order]));
    }

    public function fullyPaidProvider(): array
    {
        return [
            'paid' => [100.0, 100.0, true],
            'paid within rounding' => [100.0, 99.996, true],
            'partially paid' => [100.0, 99.0, false],
            'free order' => [0.0, 0.0, false],
        ];
    }

    // -------------------------------------------------------------- invoices

    public function testPaymentsAreLinkedToTheInvoiceOnce(): void
    {
        $this->order();
        Stubs::returns('Order->hasInvoice', true);
        $invoice = new \OrderInvoice();
        $invoice->id = 70;
        Stubs::returns('Order->getInvoicesCollection', [$invoice]);
        Stubs::returns('OrderPayment::getByOrderReference', [self::payment(['id' => 1]), self::payment(['id' => 2])]);
        Stubs::db('/`id_order_payment` = 1\b/', '1');
        Stubs::db('/COUNT\(\*\) FROM `ps_order_invoice_payment`/', '0');

        $this->controller->call('linkAllPaymentsToInvoice', [self::ORDER_ID]);

        self::assertSame(
            ['INSERT INTO `ps_order_invoice_payment` {"id_order_invoice":70,"id_order_payment":2,"id_order":40}'],
            Stubs::sql('insert')
        );
        self::assertSame([['order_invoice_paid_*']], Stubs::callsTo('Cache::clean'));
    }

    /**
     * @dataProvider noInvoiceLinkProvider
     */
    public function testNothingIsLinkedWithoutInvoiceOrPayments(bool $orderExists, bool $hasInvoice, $invoices, $payments): void
    {
        if ($orderExists) {
            $this->order();
        }
        Stubs::returns('Order->hasInvoice', $hasInvoice);
        Stubs::returns('Order->getInvoicesCollection', $invoices);
        Stubs::returns('OrderPayment::getByOrderReference', $payments);

        $this->controller->call('linkAllPaymentsToInvoice', [self::ORDER_ID]);

        self::assertSame([], Stubs::sql('insert'));
        self::assertSame([], Stubs::callsTo('Cache::clean'));
    }

    public function noInvoiceLinkProvider(): array
    {
        $invoice = new \OrderInvoice();
        $invoice->id = 70;

        return [
            'unknown order' => [false, true, [$invoice], []],
            'no invoice' => [true, false, [$invoice], []],
            'empty invoice collection' => [true, true, [], []],
            'no invoice collection' => [true, true, false, []],
            'payments unavailable' => [true, true, [$invoice], false],
        ];
    }

    public function testPaymentsWithoutKeyAreRemovedOnceKeyedPaymentsCoverTheTotal(): void
    {
        $this->order();
        $manual = self::payment(['id' => 3, 'transaction_id' => '', 'amount' => '100']);
        Stubs::returns('OrderPayment::getByOrderReference', [
            self::payment(['id' => 1, 'transaction_id' => 'GC', 'amount' => '40']),
            self::payment(['id' => 2, 'transaction_id' => 'TK', 'amount' => '60']),
            $manual,
        ]);

        $this->controller->call('removeRedundantInvoicePayments', [self::ORDER_ID]);

        self::assertSame(['DELETE FROM `ps_order_invoice_payment` WHERE id_order_payment = 3'], Stubs::sql('delete'));
        self::assertSame([$manual], Stubs::persisted('OrderPayment', 'delete'));
    }

    /**
     * @dataProvider keepPaymentsProvider
     */
    public function testPaymentsAreKeptWhileTheTotalIsNotCovered(bool $orderExists, float $totalPaid, $payments): void
    {
        if ($orderExists) {
            $this->order(['total_paid' => $totalPaid]);
        }
        Stubs::returns('OrderPayment::getByOrderReference', $payments);

        $this->controller->call('removeRedundantInvoicePayments', [self::ORDER_ID]);

        self::assertSame([], Stubs::persisted('OrderPayment', 'delete'));
    }

    public function keepPaymentsProvider(): array
    {
        $payments = [self::payment(['id' => 1, 'transaction_id' => 'TK', 'amount' => '40']), self::payment(['id' => 2, 'transaction_id' => '', 'amount' => '60'])];

        return [
            'unknown order' => [false, 100.0, $payments],
            'payments unavailable' => [true, 100.0, false],
            'keyed payments below total' => [true, 100.0, $payments],
            'free order' => [true, 0.0, $payments],
        ];
    }

    // ---------------------------------------------------------- order state

    /**
     * @dataProvider pendingStateProvider
     */
    public function testPendingOrderMovesToTheNewState(int $currentState, bool $sendEmail): void
    {
        $this->order(['current_state' => $currentState]);

        self::assertTrue($this->controller->call('updatePendingOrderStatus', [self::ORDER_ID, self::PAID, $sendEmail]));

        self::assertSame([[self::PAID, self::ORDER_ID, true]], Stubs::callsTo('OrderHistory->changeIdOrderState'));
        self::assertSame([[$sendEmail]], Stubs::callsTo('OrderHistory->addWithemail'));
        self::assertNotEmpty(Stubs::persisted('Order', 'save'), 'payments are finalised');
    }

    public function pendingStateProvider(): array
    {
        return [
            'pending' => [self::PENDING, false],
            'canceled' => [self::FAILED, true],
            'error' => [self::ERROR, false],
            'out of stock unpaid' => [self::OUT_OF_STOCK_UNPAID, false],
        ];
    }

    /**
     * @dataProvider keptStateProvider
     */
    public function testOrderStateIsNotChanged(bool $orderExists, int $currentState): void
    {
        if ($orderExists) {
            $this->order(['current_state' => $currentState]);
        }

        self::assertFalse($this->controller->call('updatePendingOrderStatus', [self::ORDER_ID, self::PAID]));
        self::assertSame([], Stubs::callsTo('OrderHistory->changeIdOrderState'));
    }

    public function keptStateProvider(): array
    {
        return [
            'unknown order' => [false, self::PENDING],
            'already in the new state' => [true, self::PAID],
            'shipped' => [true, 4],
        ];
    }

    public function testFullyPaidOrderIsMarkedPaid(): void
    {
        $this->order(['current_state' => self::PENDING, 'total_paid_real' => 100.0, 'id_cart' => 0]);
        Stubs::returns('OrderPayment::getByOrderReference', [self::payment(['amount' => '100', 'transaction_id' => 'TK'])]);

        self::assertTrue($this->controller->call('completeOrderIfFullyPaid', [self::ORDER_ID]));
        self::assertSame([[self::PAID, self::ORDER_ID, true]], Stubs::callsTo('OrderHistory->changeIdOrderState'));
    }

    /**
     * @dataProvider notCompletedProvider
     */
    public function testOrderIsNotCompletedUntilFullyPaid(bool $orderExists): void
    {
        if ($orderExists) {
            $this->order(['current_state' => self::PENDING, 'id_cart' => 0]);
        }
        Stubs::returns('OrderPayment::getByOrderReference', [self::payment(['amount' => '30', 'transaction_id' => 'TK'])]);

        self::assertFalse($this->controller->call('completeOrderIfFullyPaid', [self::ORDER_ID]));
        self::assertSame([], Stubs::callsTo('OrderHistory->changeIdOrderState'));
    }

    public function notCompletedProvider(): array
    {
        return ['unknown order' => [false], 'partially paid' => [true]];
    }

    public function testPaidReturnMarksThePendingOrderPaid(): void
    {
        $this->order(['current_state' => self::PENDING]);
        $response = new FakeResponse();
        $response->paid = true;

        self::assertTrue($this->controller->call('markOrderPaidAfterSuccessfulReturn', [self::ORDER_ID, $response]));
        self::assertSame([[self::PAID, self::ORDER_ID, true]], Stubs::callsTo('OrderHistory->changeIdOrderState'));
    }

    public function testPaidReturnForAnAlreadyPaidOrderOnlyFinalisesPayments(): void
    {
        $this->order(['current_state' => self::PAID]);
        Stubs::returns('Order->hasInvoice', false);
        $response = new FakeResponse();
        $response->paid = true;

        self::assertFalse($this->controller->call('markOrderPaidAfterSuccessfulReturn', [self::ORDER_ID, $response]));
        self::assertSame([], Stubs::callsTo('OrderHistory->changeIdOrderState'));
        self::assertCount(2, Stubs::persisted('Order', 'save'), 'payments are synchronised and finalised');
        self::assertCount(1, Stubs::callsTo('Order->hasInvoice'), 'invoice linking ran');
    }

    /**
     * @dataProvider unpaidReturnProvider
     */
    public function testPendingOrUnknownReturnLeavesTheOrderPending($response): void
    {
        $this->order(['current_state' => self::PENDING]);

        self::assertFalse($this->controller->call('markOrderPaidAfterSuccessfulReturn', [self::ORDER_ID, $response]));
        self::assertSame([], Stubs::persisted('Order'));
    }

    public function unpaidReturnProvider(): array
    {
        return [
            'no response' => [null],
            'response without isPaid' => [new \stdClass()],
            'pending response' => [new FakeResponse()],
        ];
    }
}
