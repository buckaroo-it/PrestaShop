<?php

namespace BuckarooTest\Unit\Controller\Front\ReturnFlow;

use Buckaroo\PrestaShop\Src\Refund\Settings;
use Buckaroo\PrestaShop\Src\Repository\RawBuckarooFeeRepository;
use BuckarooTest\Stubs;
use BuckarooTest\TerminateException;

require_once __DIR__ . '/StubsSupport.php';

/**
 * Buckaroo push handler (controllers/front/return.php): signature and gateway
 * checks, order lookup, status updates, refunds, reservations and partial payments.
 */
class ReturnControllerTest extends ReturnFlowTestCase
{
    private const PENDING = 10;
    private const FAILED = 11;
    private const PAID = 2;
    private const ERROR = 8;
    private const OUT_OF_STOCK_UNPAID = 9;
    private const SHIPPED = 4;

    /** @var RecordingLogger */
    private $logger;

    /** @var \OrderPayment[] payments stored before the push */
    private $payments = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireRewrittenReturnController();

        foreach ([
            'BUCKAROO_ORDER_STATE_DEFAULT' => self::PENDING,
            'BUCKAROO_ORDER_STATE_FAILED' => self::FAILED,
            'BUCKAROO_ORDER_STATE_SUCCESS' => self::PAID,
            'PS_OS_PAYMENT' => self::PAID,
            'PS_OS_ERROR' => self::ERROR,
            'PS_OS_OUTOFSTOCK_UNPAID' => self::OUT_OF_STOCK_UNPAID,
            'PS_OS_CANCELED' => 6,
        ] as $key => $value) {
            \Configuration::updateValue($key, $value);
        }
        foreach ([self::PAID, self::PENDING, self::FAILED, self::ERROR, 6] as $state) {
            Stubs::object('OrderState', $state);
        }
        Stubs::returns('OrderState::getOrderStates', [
            ['id_order_state' => self::PAID, 'name' => 'Payment accepted'],
            ['id_order_state' => self::FAILED, 'name' => 'Payment failed'],
        ]);

        $this->payments = [];
        Stubs::returns('OrderPayment::getByOrderReference', function ($reference) {
            $found = [];
            foreach (array_merge($this->payments, Stubs::persisted('OrderPayment', 'save')) as $payment) {
                if ($payment->order_reference === $reference) {
                    $found[spl_object_id($payment)] = $payment;
                }
            }

            return array_values($found);
        });
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Order 42 on cart 5 with reference REF123. */
    private function givenOrder(array $props = []): void
    {
        Stubs::returns('Order::getIdByCartId', function ($cartId) {
            return (int) $cartId === 5 ? 42 : 0;
        });
        Stubs::object('Order', 42, $props + [
            'reference' => 'REF123',
            'id_cart' => 5,
            'current_state' => self::PENDING,
            'total_paid' => 10.0,
            'total_paid_real' => 0.0,
            'id_currency' => 1,
        ]);
        Stubs::returns('Order::getByReference', function ($reference) {
            return $this->collection($reference === 'REF123' ? [new \Order(42)] : []);
        });
    }

    private function givenPayment(array $props): \OrderPayment
    {
        $payment = $this->payment($props + ['order_reference' => 'REF123']);
        $this->payments[] = $payment;

        return $payment;
    }

    private function push(array $fields, bool $tamper = false): void
    {
        $this->signedPush($fields + [
            'brq_invoicenumber' => 'REF123_5',
            'brq_payment_method' => 'ideal',
            'brq_amount' => '10.00',
            'brq_currency' => 'EUR',
            'brq_test' => 'false',
            'brq_transactions' => 'TXKEY',
        ], $tamper);
    }

    private function newController(): \Buckaroo3ReturnModuleFrontController
    {
        $controller = new \Buckaroo3ReturnModuleFrontController();
        $this->logger = $this->recordLogs($controller);

        return $controller;
    }

    /** Run the push handler and return what it printed before ending the request. */
    private function runPush(?\Buckaroo3ReturnModuleFrontController $controller = null): string
    {
        $controller = $controller ?: $this->newController();
        ob_start();
        try {
            $controller->initContent();
        } catch (TerminateException $terminate) {
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        ob_end_clean();
        self::fail('The push handler did not end the request');
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    public function testTamperedPushIsRejectedWithoutTouchingTheOrder(): void
    {
        $this->givenOrder();
        $this->push(['brq_statuscode' => '190'], true);

        self::assertSame('Payment response not valid', $this->runPush());
        self::assertSame(['Payment response not valid'], $this->logger->messages('error'));
        self::assertSame([], $this->stateChanges());
        self::assertSame(0, Stubs::callCount('Order::getIdByCartId'));
    }

    public function testRequestWithoutStatusCodeIsRejected(): void
    {
        $this->request([], ['brq_invoicenumber' => 'REF123_5']);

        self::assertSame('Payment response not valid', $this->runPush());
    }

    public function testTestGatewayPushCannotCompleteALiveOrder(): void
    {
        $this->givenOrder();
        Stubs::db('/bk_transaction_mode/', [['mode' => 'live']]);
        $this->push(['brq_statuscode' => '190', 'brq_test' => 'true']);

        self::assertSame('Payment response not valid', $this->runPush());
        self::assertSame([], $this->stateChanges());
        self::assertStringContainsString("WHERE `invoice` = 'REF123_5'", Stubs::sql('executeS')[0]);
    }

    public function testLivePushOnLiveStartedOrderIsAccepted(): void
    {
        $this->givenOrder();
        Stubs::db('/bk_transaction_mode/', [['mode' => 'live']]);
        $this->push(['brq_statuscode' => '190', 'brq_test' => 'false']);

        self::assertSame('', $this->runPush());
        self::assertSame([[self::PAID, 42, true]], $this->stateChanges());
    }

    // -------------------------------------------------------------------------
    // iDIN
    // -------------------------------------------------------------------------

    public function testIdinPushStoresTheVerificationForTheCartOwner(): void
    {
        Stubs::object('Cart', 5, ['id_customer' => 7]);
        $this->push([
            'brq_payment_method' => 'IDIN',
            'brq_statuscode' => '190',
            'ADD_cid' => '7',
            'ADD_cart' => '5',
            'brq_SERVICE_idin_ConsumerBIN' => 'BIN123',
            'brq_SERVICE_idin_IsEighteenOrOlder' => 'True',
        ]);

        self::assertSame('OK', $this->runPush());
        $inserts = array_values(array_filter(Stubs::sql('execute'), function ($sql) {
            return strpos($sql, 'bk_customer_idin') !== false;
        }));
        self::assertCount(1, $inserts);
        self::assertStringContainsString('INSERT INTO ps_bk_customer_idin', $inserts[0]);
        self::assertStringContainsString('"BIN123"', $inserts[0]);
        self::assertSame(0, Stubs::callCount('Order::getIdByCartId'));
    }

    public function testIdinPushForAnotherCustomersCartIsNotStored(): void
    {
        Stubs::object('Cart', 5, ['id_customer' => 99]);
        $this->push([
            'brq_payment_method' => 'IDIN',
            'brq_statuscode' => '190',
            'ADD_cid' => '7',
            'ADD_cart' => '5',
            'brq_SERVICE_idin_ConsumerBIN' => 'BIN123',
            'brq_SERVICE_idin_IsEighteenOrOlder' => 'True',
        ]);

        self::assertSame('iDIN result not stored', $this->runPush());
        self::assertSame([], Stubs::sql('execute'));
    }

    // -------------------------------------------------------------------------
    // Regular status pushes
    // -------------------------------------------------------------------------

    public function testPaidPushMovesPendingOrderToPaidAndBooksThePayment(): void
    {
        $this->givenOrder();
        $pendingKey = $this->givenPayment(['transaction_id' => 'TXKEY', 'amount' => 0, 'payment_method' => 'ideal']);
        $group = $this->givenPayment(['transaction_id' => 'GROUP', 'amount' => 5, 'payment_method' => 'Group transaction']);
        $unlinked = $this->givenPayment(['transaction_id' => '', 'amount' => 10.0, 'payment_method' => 'Buckaroo']);
        $this->push(['brq_statuscode' => '190', 'brq_statusmessage' => 'Transaction successfully processed']);

        self::assertSame('', $this->runPush());

        self::assertSame([[self::PAID, 42, true]], $this->stateChanges());
        self::assertSame([[false]], Stubs::callsTo('OrderHistory->addWithemail'));
        self::assertSame(10.0, $pendingKey->amount, 'the pending 0.00 payment gets the paid amount');
        self::assertSame(0, $group->amount, 'group transaction rows are zeroed');
        self::assertSame('TXKEY', $unlinked->transaction_id);
        self::assertSame('ideal', $unlinked->payment_method);
        self::assertSame([
            '42:Push message received. Buckaroo status: Payment accepted. Transaction key: TXKEY',
            '42:Buckaroo message: Transaction successfully processed',
        ], $this->addedMessages());
        self::assertContains('Update order status', $this->logger->messages('info'));
    }

    /**
     * @dataProvider updatableStates
     */
    public function testStatusIsUpdatedFromEveryUnpaidState(int $currentState): void
    {
        $this->givenOrder(['current_state' => $currentState]);
        $this->push(['brq_statuscode' => '190']);

        $this->runPush();

        self::assertSame([[self::PAID, 42, true]], $this->stateChanges());
    }

    public function updatableStates(): array
    {
        return [
            'pending' => [self::PENDING],
            'failed' => [self::FAILED],
            'error' => [self::ERROR],
            'out of stock unpaid' => [self::OUT_OF_STOCK_UNPAID],
        ];
    }

    /**
     * @dataProvider lockedStates
     */
    public function testPushDoesNotChangeAnOrderThatLeftThePendingStates(int $currentState, string $statusCode): void
    {
        $this->givenOrder(['current_state' => $currentState]);
        $this->push(['brq_statuscode' => $statusCode]);

        self::assertSame('', $this->runPush());

        self::assertSame([], $this->stateChanges());
        self::assertContains('Order status not updated', $this->logger->messages('info'));
    }

    public function lockedStates(): array
    {
        return [
            'cancel push on a shipped order' => [self::SHIPPED, '890'],
            'paid push on a paid order' => [self::PAID, '190'],
        ];
    }

    public function testCancelledPushMovesPendingOrderToFailedWithoutBookingPayment(): void
    {
        $this->givenOrder();
        $pendingKey = $this->givenPayment(['transaction_id' => 'TXKEY', 'amount' => 0, 'payment_method' => 'ideal']);
        $this->push(['brq_statuscode' => '890']);

        $this->runPush();

        self::assertSame([[self::FAILED, 42, true]], $this->stateChanges());
        self::assertSame(0, $pendingKey->amount);
        self::assertSame(['42:Push message received. Buckaroo status: Payment failed. Transaction key: TXKEY'], $this->addedMessages());
    }

    public function testPaidPushWithoutAmountBooksNothingOnThePendingPayment(): void
    {
        $this->givenOrder();
        $pendingKey = $this->givenPayment(['transaction_id' => 'TXKEY', 'amount' => 0, 'payment_method' => 'ideal']);
        $this->push(['brq_statuscode' => '190', 'brq_amount' => '0.00']);

        $this->runPush();

        self::assertSame([[self::PAID, 42, true]], $this->stateChanges());
        self::assertSame(0, $pendingKey->amount);
    }

    public function testPendingPaypalPushIsTreatedAsCancelledByTheCustomer(): void
    {
        $this->givenOrder(['current_state' => self::ERROR]);
        $this->push(['brq_statuscode' => '791', 'brq_payment_method' => 'paypal']);

        $this->runPush();

        self::assertSame([[self::FAILED, 42, true]], $this->stateChanges());
    }

    public function testPushForUnknownOrderAnswersServiceUnavailable(): void
    {
        Stubs::returns('Order::getIdByCartId', 0);
        $this->push(['brq_statuscode' => '190']);

        self::assertSame('Order does not exist', $this->runPush());
        self::assertSame(['PUSH'], $this->logger->messages('error'));
        self::assertSame([], $this->addedMessages());
    }

    public function testPushWhoseReferenceDoesNotMatchTheOrderIsRejected(): void
    {
        $this->givenOrder(['reference' => 'OTHERREF']);
        Stubs::returns('Order::getByReference', function ($reference) {
            // The signed invoice names REF123; a different order owns that reference.
            return $this->collection($reference === 'REF123' ? [(new \Order())->hydrate(['reference' => 'REF123'])] : []);
        });
        $this->push(['brq_statuscode' => '190']);

        self::assertSame('Order not in reference: OTHERREF', $this->runPush());
        self::assertSame([], $this->stateChanges());
        self::assertContains('Order not in reference OTHERREF', $this->logger->messages('error'));
    }

    public function testGiftcardInvoiceIsAcceptedForATransactionStoredOnTheCart(): void
    {
        $this->givenOrder();
        Stubs::db('/SELECT 1 FROM `ps_bk_group_transaction`/', '1');
        $this->push(['brq_statuscode' => '190', 'brq_invoicenumber' => 'gc_1700000000_5', 'brq_transactions' => 'GCKEY']);

        self::assertSame('', $this->runPush());

        self::assertSame([[self::PAID, 42, true]], $this->stateChanges());
        $lookup = Stubs::sql('getValue')[0];
        self::assertStringContainsString('`cart_id` = 5', $lookup);
        self::assertStringContainsString("`transaction_key` = 'GCKEY'", $lookup);
    }

    public function testGiftcardInvoiceForAnUnknownTransactionIsRejected(): void
    {
        $this->givenOrder();
        Stubs::db('/SELECT 1 FROM `ps_bk_group_transaction`/', false);
        $this->push(['brq_statuscode' => '190', 'brq_invoicenumber' => 'gc_1700000000_5', 'brq_transactions' => 'GCKEY']);

        self::assertSame('Order not in reference: REF123', $this->runPush());
        self::assertSame([], $this->stateChanges());
    }

    /**
     * @dataProvider invalidResolvedStates
     */
    public function testInvalidResolvedStateKeepsTheCurrentState($errorState, string $expectedId): void
    {
        \Configuration::updateValue('BUCKAROO_ORDER_STATE_SUCCESS', 0);
        \Configuration::updateValue('PS_OS_PAYMENT', 0);
        \Configuration::updateValue('PS_OS_ERROR', $errorState);
        Stubs::$objects['OrderState'] = [];
        $this->givenOrder();
        $this->push(['brq_statuscode' => '190']);

        $this->runPush();

        self::assertSame([], $this->stateChanges());
        self::assertContains(
            'Resolved order state id ' . $expectedId . ' is invalid for order 42; status change skipped',
            $this->logger->messages('error')
        );
        self::assertSame(
            ['42:Push message received. Buckaroo status: ' . self::PENDING . '. Transaction key: TXKEY'],
            $this->addedMessages(),
            'the unnamed current state id is reported'
        );
    }

    public function invalidResolvedStates(): array
    {
        return [
            'deleted error state' => [77, '77'],
            'no state configured' => [0, '0'],
        ];
    }

    public function testKlarnaCapturePushStoresTheCaptureKeyOnThePayment(): void
    {
        $this->givenOrder(['current_state' => self::PAID]);
        $payment = $this->givenPayment(['transaction_id' => 'RESERVEKEY', 'amount' => 10.0, 'payment_method' => 'klarnakp']);
        $this->push([
            'brq_statuscode' => '190',
            'brq_payment_method' => 'klarnakp',
            'brq_transaction_type' => 'C339',
            'brq_transactions' => 'CAPTUREKEY',
        ]);

        $this->runPush();

        self::assertSame('CAPTUREKEY', $payment->transaction_id);
        self::assertCount(1, Stubs::persisted('OrderPayment', 'update'));
    }

    /** Register a fee repository that reports a Buckaroo fee for every order. */
    private function givenPaymentFee(): RawBuckarooFeeRepository
    {
        $fees = new class extends RawBuckarooFeeRepository {
            /** @var int[] */
            public $orders = [];

            public function getFeeByOrderId($orderId)
            {
                $this->orders[] = $orderId;

                return ['buckaroo_fee_tax_incl' => 1.5];
            }
        };
        $this->container->services['buckaroo.repository.raw_buckaroo_fee'] = $fees;

        return $fees;
    }

    public function testPaymentFeeAmountIsCorrectedOnTheSinglePayment(): void
    {
        $fees = $this->givenPaymentFee();
        $this->givenOrder();
        $payment = $this->givenPayment(['transaction_id' => '', 'amount' => 10.0, 'payment_method' => 'Buckaroo']);
        $this->push(['brq_statuscode' => '190', 'brq_amount' => '11.50']);

        $this->runPush();

        self::assertSame([42], $fees->orders);
        self::assertSame(11.5, $payment->amount);
        self::assertSame('TXKEY', $payment->transaction_id);
    }

    public function testPaymentFeeIsNotBookedWhenSeveralUnlinkedPaymentsCouldBeMeant(): void
    {
        $this->givenPaymentFee();
        $this->givenOrder();
        $first = $this->givenPayment(['transaction_id' => '', 'amount' => 8.5, 'payment_method' => 'Buckaroo']);
        $second = $this->givenPayment(['transaction_id' => '', 'amount' => 3.0, 'payment_method' => 'Buckaroo']);
        $this->push(['brq_statuscode' => '190', 'brq_amount' => '11.50']);

        $this->runPush();

        self::assertSame(8.5, $first->amount);
        self::assertSame('', $first->transaction_id);
        self::assertSame(3.0, $second->amount);
        self::assertSame('', $second->transaction_id);
        self::assertContains('Payment fee correction skipped: several unlinked payments', $this->logger->messages('info'));
    }

    /**
     * Regression: gift card + remainder. The old code checked the last row of an
     * earlier loop and booked the full push amount on the first row without a
     * transaction key, which could be the group-transaction row.
     */
    public function testPaymentFeeOfTheRemainderLeavesGiftcardAndGroupRowsAlone(): void
    {
        $this->givenPaymentFee();
        $this->givenOrder(['total_paid' => 21.5]);
        $giftcard = $this->givenPayment(['transaction_id' => 'GCKEY', 'amount' => 10.0, 'payment_method' => 'boekenbon']);
        $group = $this->givenPayment(['transaction_id' => '', 'amount' => 21.5, 'payment_method' => 'Group transaction']);
        $remainder = $this->givenPayment(['transaction_id' => '', 'amount' => 10.0, 'payment_method' => 'Buckaroo']);
        $this->push(['brq_statuscode' => '190', 'brq_amount' => '11.50']);

        $this->runPush();

        self::assertSame(10.0, $giftcard->amount);
        self::assertSame('GCKEY', $giftcard->transaction_id);
        self::assertSame(0, $group->amount, 'group transaction rows are zeroed, never re-booked');
        self::assertSame('', $group->transaction_id);
        self::assertSame(11.5, $remainder->amount);
        self::assertSame('TXKEY', $remainder->transaction_id);
    }

    /** A Group transaction row that comes last used to disable the correction (stale $payment). */
    public function testPaymentFeeIsCorrectedWhateverRowComesLast(): void
    {
        $this->givenPaymentFee();
        $this->givenOrder();
        $remainder = $this->givenPayment(['transaction_id' => '', 'amount' => 10.0, 'payment_method' => 'Buckaroo']);
        $group = $this->givenPayment(['transaction_id' => 'GROUP', 'amount' => 5.0, 'payment_method' => 'Group transaction']);
        $this->push(['brq_statuscode' => '190', 'brq_amount' => '11.50']);

        $this->runPush();

        self::assertSame(11.5, $remainder->amount);
        self::assertSame('TXKEY', $remainder->transaction_id);
        self::assertSame(0, $group->amount);
        self::assertSame('GROUP', $group->transaction_id);
    }

    public function testPaymentFeeCorrectionPrefersTheRowOfThePushTransaction(): void
    {
        $this->givenPaymentFee();
        $this->givenOrder();
        $other = $this->givenPayment(['transaction_id' => '', 'amount' => 10.0, 'payment_method' => 'Buckaroo']);
        $own = $this->givenPayment(['transaction_id' => 'TXKEY', 'amount' => 0, 'payment_method' => 'ideal']);
        $this->push(['brq_statuscode' => '190', 'brq_amount' => '11.50']);

        $this->runPush();

        self::assertSame(11.5, $own->amount, 'booked by the pending-key booking');
        self::assertSame(10.0, $other->amount, 'the other row is not booked a second time');
        self::assertSame('', $other->transaction_id);
    }

    /**
     * @dataProvider uncorrectedPushes
     */
    public function testPaymentFeeIsNotBookedForAnUnpaidOrGroupPush(array $fields): void
    {
        $this->givenPaymentFee();
        $this->givenOrder();
        $payment = $this->givenPayment(['transaction_id' => '', 'amount' => 10.0, 'payment_method' => 'Buckaroo']);
        $this->push($fields + ['brq_amount' => '11.50']);

        $this->runPush();

        self::assertSame(10.0, $payment->amount);
        self::assertSame('', $payment->transaction_id);
    }

    public function uncorrectedPushes(): array
    {
        return [
            'cancelled push' => [['brq_statuscode' => '890']],
            'group transaction push' => [['brq_statuscode' => '190', 'brq_transaction_type' => 'I150']],
            'push without transaction key' => [['brq_statuscode' => '190', 'brq_transactions' => '']],
        ];
    }

    /** Regression: amounts are compared in cents, not loosely as float vs "10.00" string. */
    public function testPushLinksThePaymentWhoseAmountMatchesInCents(): void
    {
        $this->givenOrder();
        $payment = $this->givenPayment(['transaction_id' => '', 'amount' => 10.0000001, 'payment_method' => 'Buckaroo']);
        $this->push(['brq_statuscode' => '190', 'brq_amount' => '10.00']);

        $this->runPush();

        self::assertSame('TXKEY', $payment->transaction_id);
        self::assertSame('ideal', $payment->payment_method);
    }

    /** An invalid push never echoes or stores its unsigned status message. */
    public function testTamperedPushDoesNotUseItsStatusMessage(): void
    {
        $this->givenOrder();
        $this->push(['brq_statuscode' => '490', 'brq_statusmessage' => '<script>alert(1)</script>'], true);

        self::assertSame('Payment response not valid', $this->runPush());
        self::assertSame([], $this->addedMessages());
    }

    public function testFeeRepositoryFallsBackToTheRawRepository(): void
    {
        $controller = new \Buckaroo3ReturnModuleFrontController();

        self::assertInstanceOf(RawBuckarooFeeRepository::class, self::getProperty($controller, 'buckarooFeeRepository'));
    }

    // -------------------------------------------------------------------------
    // Klarna reservation pushes
    // -------------------------------------------------------------------------

    private function givenReservation(float $amount = 25.0): \OrderPayment
    {
        $this->givenOrder(['current_state' => self::PENDING, 'total_paid' => 25.0]);
        $payment = $this->givenPayment(['transaction_id' => 'RESKEY', 'amount' => $amount, 'payment_method' => 'klarnakp']);
        Stubs::returns('PrestaShopCollection::OrderPayment', [$payment]);

        return $payment;
    }

    public function testCancelReservationCancelsTheOrderAndZeroesThePayment(): void
    {
        $payment = $this->givenReservation();
        $this->push([
            'brq_statuscode' => '190',
            'brq_actioncode' => 'CancelReservation',
            'brq_originalreservation_datarequest_key' => 'RESKEY',
            'brq_statusmessage' => 'Reservation cancelled',
            'brq_payment_method' => 'klarnakp',
        ]);

        self::assertSame('', $this->runPush());

        self::assertSame([[self::FAILED, 42, true]], $this->stateChanges());
        self::assertSame(0, $payment->amount);
        self::assertSame(0.0, Stubs::$objects['Order'][42]['total_paid_real'], 'total paid real is synced');
        self::assertSame(['42:Buckaroo reservation cancelled. Reservation cancelled'], $this->addedMessages());
        self::assertSame(0, Stubs::callCount('Order::getIdByCartId'), 'the regular push path is skipped');
    }

    public function testCancelReservationOnAlreadyCancelledOrderOnlyAddsTheMessage(): void
    {
        $payment = $this->givenReservation(0.0);
        Stubs::$objects['Order'][42]['current_state'] = self::FAILED;
        $this->push([
            'brq_statuscode' => '190',
            'brq_actioncode' => 'cancelreservation',
            'brq_originalreservation_datarequest_key' => 'RESKEY',
            'brq_payment_method' => 'klarnakp',
        ]);

        $this->runPush();

        self::assertSame([], $this->stateChanges());
        self::assertSame([], Stubs::persisted('OrderPayment', 'update'));
        self::assertSame(['42:Buckaroo reservation cancelled. '], $this->addedMessages());
    }

    public function testExtendReservationOnlyAddsAMessage(): void
    {
        $payment = $this->givenReservation();
        $this->push([
            'brq_statuscode' => '190',
            'brq_actioncode' => 'ExtendReservation',
            'brq_originalreservation_datarequest_key' => 'RESKEY',
            'brq_statusmessage' => 'Extended',
            'brq_payment_method' => 'klarnakp',
        ]);

        $this->runPush();

        self::assertSame([], $this->stateChanges());
        self::assertSame(25.0, $payment->amount);
        self::assertSame(['42:Buckaroo reservation extended. Extended'], $this->addedMessages());
    }

    /**
     * @dataProvider unresolvableReservations
     */
    public function testUnresolvableReservationFallsBackToTheRegularPush(string $key, array $payments, array $orders): void
    {
        $this->givenOrder();
        Stubs::returns('PrestaShopCollection::OrderPayment', array_map(function ($props) {
            return $this->payment($props);
        }, $payments));
        Stubs::returns('Order::getByReference', function ($reference) use ($orders) {
            return $this->collection($reference === 'REF123' ? array_map(function () {
                return new \Order(42);
            }, $orders) : []);
        });
        $this->push([
            'brq_statuscode' => '190',
            'brq_actioncode' => 'cancelreservation',
            'brq_originalreservation_datarequest_key' => $key,
        ]);

        $output = $this->runPush();

        self::assertSame(1, Stubs::callCount('Order::getIdByCartId'), 'regular push path runs');
        self::assertNotContains('42:Buckaroo reservation cancelled. ', $this->addedMessages());
        self::assertIsString($output);
    }

    public function unresolvableReservations(): array
    {
        return [
            'no reservation key' => ['', [], [1]],
            'unknown reservation key' => ['RESKEY', [], [1]],
            'payment without order reference' => ['RESKEY', [['order_reference' => '']], [1]],
            'order not found' => ['RESKEY', [['order_reference' => 'REF123']], []],
        ];
    }

    // -------------------------------------------------------------------------
    // Refund pushes
    // -------------------------------------------------------------------------

    private function refundPush(array $fields = []): void
    {
        $this->push($fields + [
            'brq_statuscode' => '190',
            'brq_relatedtransaction_refund' => 'ORIGINALKEY',
            'brq_amount_credit' => '10.00',
            'brq_transactions' => 'REFUNDKEY',
            'brq_transaction_method' => 'ideal',
            'brq_statusmessage' => 'Refunded',
        ]);
    }

    public function testRefundPushIsHandledByTheRefundService(): void
    {
        $handler = new class {
            public $handled = 0;

            public function handle()
            {
                ++$this->handled;
            }
        };
        $messages = new class {
            public $added = [];

            public function add($order, $message)
            {
                $this->added[] = [$order->id, $message];
            }
        };
        $this->container->services['buckaroo.refund.push.handler'] = $handler;
        $this->container->services['buckaroo.refund.order.message'] = $messages;
        $this->givenOrder();
        $this->refundPush();

        self::assertSame('', $this->runPush());

        self::assertSame(1, $handler->handled);
        self::assertSame([[42, 'Buckaroo refund message (REFUNDKEY): Refunded']], $messages->added);
        self::assertSame([], $this->stateChanges());
    }

    public function testRefundServiceWithoutOrderSkipsTheOrderMessage(): void
    {
        $handler = new class {
            public $handled = 0;

            public function handle()
            {
                ++$this->handled;
            }
        };
        $this->container->services['buckaroo.refund.push.handler'] = $handler;
        $this->container->services['buckaroo.refund.order.message'] = function () {
            throw new \LogicException('must not be used without an order');
        };
        Stubs::returns('Order::getIdByCartId', 0);
        $this->refundPush();

        $this->runPush();

        self::assertSame(1, $handler->handled);
        self::assertSame([], $this->logger->messages('error'));
    }

    public function testRefundFallbackMarksAFullRefundAndCreatesANegativePayment(): void
    {
        \Configuration::updateValue('PS_OS_REFUND', 7);
        \Configuration::updateValue('PS_CHECKOUT_STATE_PARTIALLY_REFUNDED', 12);
        \Configuration::updateValue(Settings::LABEL_REFUND_CREATE_NEGATIVE_PAYMENT, 1);
        $this->givenOrder(['total_paid' => 10.0, 'id_currency' => 3]);
        $this->refundPush();

        $this->runPush();

        self::assertSame(['Refund push handler service not available, applying simple refund fallback'], $this->logger->messages('warn'));
        self::assertSame([[7, 42]], $this->stateChanges());
        $saved = Stubs::persisted('OrderPayment', 'save');
        self::assertCount(1, $saved);
        self::assertSame(-10.0, $saved[0]->amount);
        self::assertSame('REFUNDKEY', $saved[0]->transaction_id);
        self::assertSame('ideal', $saved[0]->payment_method);
        self::assertSame('REF123', $saved[0]->order_reference);
        self::assertSame(3, $saved[0]->id_currency);
        self::assertSame(
            ['42:Buckaroo refund (fallback) of 10 received. Transaction key: REFUNDKEY. Message: Refunded'],
            $this->addedMessages()
        );
    }

    /**
     * @dataProvider partialRefunds
     */
    public function testRefundFallbackMarksAPartialRefund(string $credit, $fullState): void
    {
        \Configuration::updateValue('PS_OS_REFUND', $fullState);
        \Configuration::updateValue('PS_CHECKOUT_STATE_PARTIALLY_REFUNDED', 12);
        $this->givenOrder(['total_paid' => 10.0]);
        $this->refundPush(['brq_amount_credit' => $credit]);

        $this->runPush();

        self::assertSame([[12, 42]], $this->stateChanges());
        self::assertSame([], Stubs::persisted('OrderPayment', 'save'), 'no negative payment unless configured');
    }

    public function partialRefunds(): array
    {
        return [
            'part of the total' => ['4.00', 7],
            'full amount but no refunded state' => ['10.00', 0],
        ];
    }

    public function testRefundFallbackWithOnlyTheFullStateLeavesAPartialRefundUnchanged(): void
    {
        \Configuration::updateValue('PS_OS_REFUND', 7);
        \Configuration::updateValue(Settings::LABEL_REFUND_CREATE_NEGATIVE_PAYMENT, 1);
        $this->givenOrder(['total_paid' => 10.0]);
        $this->refundPush(['brq_amount_credit' => '4.00', 'brq_transaction_method' => '']);

        $this->runPush();

        self::assertSame([], $this->stateChanges());
        self::assertSame([], Stubs::persisted('OrderPayment', 'save'), 'no payment without a refund method');
        self::assertCount(1, $this->addedMessages());
    }

    /**
     * @dataProvider rejectedRefundFallbacks
     */
    public function testRefundFallbackStopsWhenItCannotApplyTheRefund(bool $orderExists, array $config, string $credit, string $error): void
    {
        foreach ($config as $key => $value) {
            \Configuration::updateValue($key, $value);
        }
        if ($orderExists) {
            $this->givenOrder();
        } else {
            Stubs::returns('Order::getIdByCartId', 0);
        }
        $this->refundPush(['brq_amount_credit' => $credit]);

        $this->runPush();

        self::assertSame([$error], $this->logger->messages('error'));
        self::assertSame([], $this->stateChanges());
        self::assertSame([], $this->addedMessages());
    }

    public function rejectedRefundFallbacks(): array
    {
        return [
            'no order' => [false, ['PS_OS_REFUND' => 7], '10.00', 'Refund push fallback: unable to resolve Order instance'],
            'no credit amount' => [true, ['PS_OS_REFUND' => 7], '0', 'Refund push fallback: invalid or missing brq_amount_credit'],
            'no refund states' => [true, [], '10.00', 'Refund push fallback: no refund order states configured (PS_OS_REFUND / PS_CHECKOUT_STATE_PARTIALLY_REFUNDED)'],
        ];
    }

    public function testRefundFallbackFailureIsLogged(): void
    {
        \Configuration::updateValue('PS_OS_REFUND', 7);
        $this->givenOrder();
        Stubs::throws('OrderHistory->addWithemail', new \RuntimeException('db down'));
        $this->refundPush();

        $this->runPush();

        self::assertSame(['PUSH refund fallback failed'], $this->logger->messages('error'));
        self::assertSame([], $this->addedMessages());
    }

    // -------------------------------------------------------------------------
    // Giftcard partial payments
    // -------------------------------------------------------------------------

    private function partialPush(string $statusCode = '190'): void
    {
        $this->push([
            'brq_statuscode' => $statusCode,
            'brq_relatedtransaction_partialpayment' => 'GROUPKEY',
            'brq_transactions' => 'REMAINDERKEY',
            'brq_amount' => '15.00',
            'brq_statusmessage' => 'Paid',
        ]);
    }

    public function testPartialPaymentPushCompletesAFullyPaidOrder(): void
    {
        $this->givenOrder(['total_paid' => 25.0]);
        Stubs::db('/SELECT \* FROM `ps_bk_group_transaction`/', [[
            'transaction_key' => 'GCKEY',
            'amount' => 10.0,
            'refunded_amount' => 0,
            'card_code' => 'fashioncheque',
        ]]);
        $this->partialPush();

        self::assertSame('', $this->runPush());

        $updates = Stubs::sql('update');
        self::assertStringContainsString('UPDATE `ps_bk_group_transaction` {"status":190}', $updates[0]);
        self::assertStringContainsString("`transaction_key` = 'REMAINDERKEY'", $updates[0]);
        self::assertStringContainsString('{"order_id":42} WHERE `cart_id` = 5', $updates[1]);

        $saved = Stubs::persisted('OrderPayment', 'save');
        $booked = [];
        foreach ($saved as $payment) {
            $booked[$payment->transaction_id] = $payment->amount;
        }
        self::assertSame(['REMAINDERKEY' => 15.0, 'GCKEY' => 10.0], $booked);
        self::assertSame(['42:Buckaroo partial payment message (REMAINDERKEY): Paid'], $this->addedMessages());
        self::assertSame([[self::PAID, 42, true]], $this->stateChanges());
        self::assertContains('Partial payment complete: order marked as paid', $this->logger->messages('info'));
    }

    public function testPartialPaymentPushLeavesAnUnderpaidOrderPending(): void
    {
        $this->givenOrder(['total_paid' => 40.0]);
        $this->partialPush();

        $this->runPush();

        self::assertSame([], $this->stateChanges());
        self::assertCount(1, Stubs::persisted('OrderPayment', 'save'));
        self::assertNotContains('Partial payment complete: order marked as paid', $this->logger->messages('info'));
    }

    public function testPartialPaymentPushWithoutCartSkipsLinking(): void
    {
        $this->givenOrder(['total_paid' => 40.0, 'id_cart' => 0]);
        $this->partialPush();

        $this->runPush();

        self::assertCount(1, Stubs::sql('update'), 'only the group transaction status is updated');
    }

    public function testPartialPaymentPushBeforeTheOrderExistsOnlyConfirmsTheGroupTransaction(): void
    {
        Stubs::returns('Order::getIdByCartId', 0);
        $this->partialPush('792');

        self::assertSame('', $this->runPush());

        $updates = Stubs::sql('update');
        self::assertCount(1, $updates);
        self::assertStringContainsString('{"status":792}', $updates[0]);
        self::assertStringContainsString('AND `status` <> 190', $updates[0], 'a late pending push cannot undo a confirmed card');
        self::assertSame([], Stubs::persisted('OrderPayment'));
        self::assertSame([], $this->addedMessages());
    }
}
