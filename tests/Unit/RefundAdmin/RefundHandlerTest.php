<?php

namespace BuckarooTest\Unit\RefundAdmin;

use Buckaroo\PrestaShop\Src\Refund\Decorators\IssuePartialRefundHandler;
use Buckaroo\PrestaShop\Src\Refund\Decorators\IssueStandardRefundHandler;
use Buckaroo\PrestaShop\Src\Refund\Handler;
use Buckaroo\PrestaShop\Src\Refund\Request\Builder;
use Buckaroo\PrestaShop\Src\Refund\Request\Handler as RequestHandler;
use Buckaroo\PrestaShop\Src\Refund\Request\Response\Handler as ResponseHandler;
use Buckaroo\PrestaShop\Src\Refund\Settings;
use Buckaroo\PrestaShop\Src\Refund\StatusService;
use Buckaroo\Transaction\Response\TransactionResponse;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Adapter\Order\Refund\OrderRefundCalculator;
use PrestaShop\PrestaShop\Adapter\Order\Refund\OrderRefundSummary;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\IssuePartialRefundCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\IssueStandardRefundCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\CommandHandler\IssuePartialRefundHandlerInterface;
use PrestaShop\PrestaShop\Core\Domain\Order\CommandHandler\IssueStandardRefundHandlerInterface;

require_once __DIR__ . '/StubsSupport.php';

/**
 * Refunds started from the PrestaShop order page (standard and partial
 * refunds): the decorators run the core refund, then send the same amount to
 * Buckaroo split over the order's captures.
 */
class RefundHandlerTest extends TestCase
{
    /** @var Builder&\PHPUnit\Framework\MockObject\MockObject */
    private $builder;

    /** @var RequestHandler&\PHPUnit\Framework\MockObject\MockObject */
    private $requests;

    /** @var ResponseHandler&\PHPUnit\Framework\MockObject\MockObject */
    private $responses;

    /** @var OrderRefundCalculator */
    private $calculator;

    /** @var array<int, array> */
    private $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = $this->createMock(Builder::class);
        $this->requests = $this->createMock(RequestHandler::class);
        $this->responses = $this->createMock(ResponseHandler::class);
        $this->calculator = new OrderRefundCalculator();

        Stubs::object('Order', 61, ['module' => 'buckaroo3', 'total_shipping_tax_incl' => 6.5]);
        Stubs::returns('Order->getOrderPayments', [self::payment('CAPTURE1', 30.0, 'ideal'), self::payment('CAPTURE2', 20.0, 'giftcard')]);

        $sent = &$this->sent;
        $this->builder->method('create')->willReturnCallback(function ($order, $payment, $summary, $fee, $amount) use (&$sent) {
            $sent[] = ['payment' => $payment->transaction_id, 'fee' => $fee, 'amount' => $amount, 'order' => $order->id];

            return ['originalTransactionKey' => $payment->transaction_id, 'amountCredit' => $amount];
        });
        $this->requests->method('refund')->willReturnCallback(function ($body, $method) {
            return new TransactionResponse(null, ['Key' => 'R-' . $body['originalTransactionKey'], 'Method' => $method]);
        });
    }

    private static function payment(string $key, float $amount, string $method): \OrderPayment
    {
        $payment = new \OrderPayment();
        $payment->transaction_id = $key;
        $payment->amount = $amount;
        $payment->payment_method = $method;

        return $payment;
    }

    private static function summary(float $amount): OrderRefundSummary
    {
        return (new OrderRefundSummary())->stub('getRefundedAmount', $amount);
    }

    private function handler(): Handler
    {
        return new Handler($this->calculator, $this->requests, $this->builder, $this->responses);
    }

    private static function partial(int $orderId = 61): PartialRefundCommand
    {
        return new PartialRefundCommand(new OrderIdValue($orderId), [5 => ['quantity' => 1]], new DecimalNumber('2.5'), false, true, false, 1);
    }

    public function testRefundIsSplitOverTheCapturesAndEachResponseParsed(): void
    {
        $parsed = [];
        $this->responses->expects(self::exactly(2))->method('parse')->willReturnCallback(function ($response, $body, $orderId) use (&$parsed) {
            $parsed[] = [$response->getTransactionKey(), $response->get('Method'), $body['amountCredit'], $orderId];
        });

        $this->handler()->execute(self::partial(), self::summary(40.0), 2.0);

        self::assertSame([
            ['payment' => 'CAPTURE1', 'fee' => 2.0, 'amount' => 30.0, 'order' => 61],
            ['payment' => 'CAPTURE2', 'fee' => 2.0, 'amount' => 12.0, 'order' => 61],
        ], $this->sent);
        self::assertSame([
            ['R-CAPTURE1', 'ideal', 30.0, 61],
            ['R-CAPTURE2', 'giftcard', 12.0, 61],
        ], $parsed);
    }

    public function testAlreadyRefundedCaptureIsNotRefundedAgain(): void
    {
        Stubs::db('/bk_refund_request/', function ($sql) {
            return strpos($sql, "'CAPTURE1'") !== false ? '30.00' : '0';
        });

        $this->handler()->execute(self::partial(), self::summary(10.0));

        self::assertSame([['payment' => 'CAPTURE2', 'fee' => 0.0, 'amount' => 10.0, 'order' => 61]], $this->sent);
    }

    public function testOrdersOfOtherModulesAreNotRefundedAtBuckaroo(): void
    {
        Stubs::object('Order', 61, ['module' => 'ps_wirepayment']);
        $this->requests->expects(self::never())->method('refund');

        $this->handler()->execute(self::partial(), self::summary(10.0));

        self::assertSame([], $this->sent);
    }

    public function testAmountBelowOneCentIsNeverSent(): void
    {
        $this->requests->expects(self::never())->method('refund');

        $result = self::invoke($this->handler(), 'refund', [new \Order(61), self::payment('CAPTURE1', 30.0, 'ideal'), self::summary(0.0), 0.004, 0.0]);

        self::assertNull($result);
    }

    public function testPartialRefundSummaryUsesTheRequestedShippingAmount(): void
    {
        $summary = self::summary(12.0);
        $command = self::partial();
        $command->voucherRefundAmount = new DecimalNumber('3');
        Stubs::returns(OrderRefundCalculator::class . '->computeOrderRefund', function ($order, $details, $shipping, $voucherType, $voucherAmount) use ($summary) {
            Stubs::$calls[] = ['captured', [$order->id, $details, (string) $shipping, $voucherType, (string) $voucherAmount]];

            return $summary;
        });

        self::assertSame($summary, $this->handler()->getRefundSummary($command));
        self::assertSame([[61, [5 => ['quantity' => 1]], '2.5', 1, '3']], Stubs::callsTo('captured'));
    }

    public function standardShippingProvider(): array
    {
        return [
            'shipping refunded' => [true, '6.5'],
            'shipping kept' => [false, '0'],
        ];
    }

    /**
     * @dataProvider standardShippingProvider
     */
    public function testStandardRefundSummaryRefundsTheWholeShippingOrNothing(bool $refundShipping, string $expected): void
    {
        $summary = self::summary(12.0);
        Stubs::returns(OrderRefundCalculator::class . '->computeOrderRefund', function ($order, $details, $shipping) use ($summary) {
            Stubs::$calls[] = ['shipping', [(string) $shipping, $details]];

            return $summary;
        });

        $this->handler()->getRefundSummary(new DetachedStandardRefundCommand(61, $refundShipping));

        self::assertSame([[$expected, [7 => ['quantity' => 1]]]], Stubs::callsTo('shipping'));
    }

    // ---------------------------------------------------------------- decorators

    /**
     * @return array{0: Handler&\PHPUnit\Framework\MockObject\MockObject, 1: StatusService&\PHPUnit\Framework\MockObject\MockObject, 2: ArraySession, 3: array}
     */
    private function decoratorDoubles(): array
    {
        $refundHandler = $this->createMock(Handler::class);
        $status = $this->createMock(StatusService::class);
        $session = new ArraySession();
        $log = new \ArrayObject();

        return [$refundHandler, $status, $session, $log];
    }

    private static function inner(string $interface, \ArrayObject $log)
    {
        if ($interface === 'partial') {
            return new class($log) implements IssuePartialRefundHandlerInterface {
                private $log;

                public function __construct($log)
                {
                    $this->log = $log;
                }

                public function handle(IssuePartialRefundCommand $command): void
                {
                    $this->log[] = 'core';
                }
            };
        }

        return new class($log) implements IssueStandardRefundHandlerInterface {
            private $log;

            public function __construct($log)
            {
                $this->log = $log;
            }

            public function handle(IssueStandardRefundCommand $command): void
            {
                $this->log[] = 'core';
            }
        };
    }

    private function decorate(string $type, $refundHandler, $status, ArraySession $session, \ArrayObject $log)
    {
        return $type === 'partial'
            ? new IssuePartialRefundHandler(self::inner('partial', $log), $refundHandler, $session, $status)
            : new IssueStandardRefundHandler(self::inner('standard', $log), $refundHandler, $session, $status);
    }

    private static function command(string $type)
    {
        return $type === 'partial'
            ? self::partial()
            : new StandardRefundCommand(new OrderIdValue(61), [5 => ['quantity' => 1]], true);
    }

    public function decoratorProvider(): array
    {
        return [['partial'], ['standard']];
    }

    /**
     * @dataProvider decoratorProvider
     */
    public function testBuckarooRefundFollowsTheShopRefund(string $type): void
    {
        list($refundHandler, $status, $session, $log) = $this->decoratorDoubles();
        \Configuration::updateValue(Settings::LABEL_REFUND_CONF, 1);
        $summary = self::summary(15.0);
        $command = self::command($type);
        $refundHandler->expects(self::once())->method('getRefundSummary')->with($command)->willReturnCallback(function () use ($summary, $log) {
            $log[] = 'summary';

            return $summary;
        });
        $refundHandler->expects(self::once())->method('execute')->with($command, $summary, 0.0)->willReturnCallback(function () use ($log) {
            $log[] = 'buckaroo';
        });
        $status->expects(self::never())->method('setRefunded');

        $this->decorate($type, $refundHandler, $status, $session, $log)->handle($command);

        // The summary is computed before the core refund changes the order.
        self::assertSame(['summary', 'core', 'buckaroo'], $log->getArrayCopy());
        self::assertSame([], Stubs::sql('update'));
    }

    /**
     * @dataProvider decoratorProvider
     */
    public function testPaymentFeeIsRefundedOnceAndMarked(string $type): void
    {
        list($refundHandler, $status, $session, $log) = $this->decoratorDoubles();
        \Configuration::updateValue(Settings::LABEL_REFUND_CONF, 1);
        $session->set('buckaroo_include_fee_61', '2.75');
        $refundHandler->method('getRefundSummary')->willReturn(self::summary(15.0));
        $refundHandler->expects(self::once())->method('execute')->with(self::anything(), self::anything(), 2.75);

        $this->decorate($type, $refundHandler, $status, $session, $log)->handle(self::command($type));

        self::assertFalse($session->has('buckaroo_include_fee_61'));
        self::assertSame(['UPDATE `ps_bk_buckaroo_fee` {"fee_refunded":1} WHERE id_order = 61'], Stubs::sql('update'));
    }

    /**
     * @dataProvider decoratorProvider
     */
    public function testWithoutBuckarooRefundOnlyTheStatusIsUpdated(string $type): void
    {
        list($refundHandler, $status, $session, $log) = $this->decoratorDoubles();
        \Configuration::updateValue(Settings::LABEL_REFUND_CONF, 0);
        $refundHandler->expects(self::never())->method('getRefundSummary');
        $refundHandler->expects(self::never())->method('execute');
        $status->expects(self::once())->method('setRefunded')->with(self::callback(function ($order) {
            return $order->id === 61;
        }));

        $this->decorate($type, $refundHandler, $status, $session, $log)->handle(self::command($type));

        self::assertSame(['core'], $log->getArrayCopy());
    }

    public function testPartialRefundFromTheBuckarooControllerIsNotSentTwice(): void
    {
        list($refundHandler, $status, $session, $log) = $this->decoratorDoubles();
        \Configuration::updateValue(Settings::LABEL_REFUND_CONF, 1);
        $session->set(IssuePartialRefundHandler::KEY_SKIP_REFUND_REQUEST, true);
        $refundHandler->method('getRefundSummary')->willReturn(self::summary(15.0));
        $refundHandler->expects(self::never())->method('execute');
        $status->expects(self::never())->method('setRefunded');

        $this->decorate('partial', $refundHandler, $status, $session, $log)->handle(self::partial());

        self::assertSame(['core'], $log->getArrayCopy());
        self::assertTrue($session->has(IssuePartialRefundHandler::KEY_SKIP_REFUND_REQUEST));
    }
}
