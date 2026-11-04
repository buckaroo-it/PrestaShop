<?php

namespace BuckarooTest\Unit\RefundAdmin;

use Buckaroo\PrestaShop\Controllers\admin\AdminRefundController;
use Buckaroo\PrestaShop\Src\Refund\OrderService;
use Buckaroo\PrestaShop\Src\Refund\Request\Handler as RequestHandler;
use Buckaroo\PrestaShop\Src\Refund\Request\QuantityBasedBuilder;
use Buckaroo\PrestaShop\Src\Refund\Request\Response\Handler as ResponseHandler;
use Buckaroo\PrestaShop\Src\Refund\Settings;
use Buckaroo\Transaction\Response\TransactionResponse;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\InvalidCancelProductException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

require_once __DIR__ . '/StubsSupport.php';

/**
 * The "Refund with Buckaroo" form on the admin order page: refunds each
 * capture through Buckaroo, then records one shop credit slip for the total.
 */
class AdminRefundControllerTest extends TestCase
{
    /** @var QuantityBasedBuilder&\PHPUnit\Framework\MockObject\MockObject */
    private $builder;

    /** @var RequestHandler&\PHPUnit\Framework\MockObject\MockObject */
    private $requests;

    /** @var ResponseHandler&\PHPUnit\Framework\MockObject\MockObject */
    private $responses;

    /** @var OrderService&\PHPUnit\Framework\MockObject\MockObject */
    private $orderService;

    /** @var ArraySession */
    private $session;

    /** @var array<int, array> */
    private $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = $this->createMock(QuantityBasedBuilder::class);
        $this->requests = $this->createMock(RequestHandler::class);
        $this->responses = $this->createMock(ResponseHandler::class);
        $this->orderService = $this->createMock(OrderService::class);
        $this->session = new ArraySession();

        Stubs::object('Order', 61, ['id_currency' => 1, 'total_paid' => 50.0]);
        Stubs::returns('Order->getOrderPayments', [self::payment('CAPTURE1', 30.0, 'ideal'), self::payment('CAPTURE2', 20.0, 'paypal')]);

        $sent = &$this->sent;
        $this->builder->method('create')->willReturnCallback(function ($order, $payment, $amount) {
            return ['originalTransactionKey' => $payment->transaction_id, 'amountCredit' => $amount];
        });
        $this->requests->method('refund')->willReturnCallback(function ($body, $method) use (&$sent) {
            $sent[] = [$body['originalTransactionKey'], $method, $body['amountCredit']];

            return new TransactionResponse(null, ['Key' => 'R-' . $body['originalTransactionKey']]);
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

    private function controller(): AdminRefundController
    {
        return new AdminRefundController($this->builder, $this->requests, $this->responses, $this->orderService, $this->session);
    }

    private static function refundRequest($orderId, $amount): Request
    {
        return new Request([], ['orderId' => $orderId, 'refundAmount' => $amount]);
    }

    private static function assertJsonPayload(JsonResponse $response, bool $error, string $message): void
    {
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['error' => $error, 'message' => $message], $response->data);
    }

    public function invalidInputProvider(): array
    {
        return [
            'order id' => [['61'], '10', 'Invalid value for `orderId`'],
            'missing order id' => [null, '10', 'Invalid value for `orderId`'],
            'refund amount' => ['61', ['10'], 'Invalid value for `refundAmount`'],
        ];
    }

    /**
     * @dataProvider invalidInputProvider
     */
    public function testInvalidInputIsRejected($orderId, $amount, string $message): void
    {
        $this->requests->expects(self::never())->method('refund');
        $controller = $this->controller();

        self::assertJsonPayload($controller->refund(self::refundRequest($orderId, $amount)), true, $message);
        self::assertSame([['error', $message]], $controller->flashes);
    }

    public function testRefundIsSplitOverTheCapturesThenRecordedInTheShop(): void
    {
        \Configuration::updateValue(Settings::LABEL_REFUND_CONF, 1);
        $parsed = [];
        $this->responses->expects(self::exactly(2))->method('parse')->willReturnCallback(function ($response, $body, $orderId) use (&$parsed) {
            $parsed[] = [$response->getTransactionKey(), $orderId];
        });
        $this->orderService->expects(self::once())->method('refund')->with(
            self::callback(function ($order) {
                return $order->id === 61;
            }),
            45.0
        );
        $controller = $this->controller();

        $response = $controller->refund(self::refundRequest('61', '45'));

        self::assertSame([['CAPTURE1', 'ideal', 30.0], ['CAPTURE2', 'paypal', 15.0]], $this->sent);
        self::assertSame([['R-CAPTURE1', 61], ['R-CAPTURE2', 61]], $parsed);
        self::assertJsonPayload($response, false, 'Successfully refunded amount of 45.00 EUR');
        self::assertSame([['success', 'Successfully refunded amount of 45.00 EUR']], $controller->flashes);
    }

    public function testOnlyTheRemainingAmountIsRefunded(): void
    {
        \Configuration::updateValue(Settings::LABEL_REFUND_CONF, 1);
        Stubs::db('/bk_refund_request/', function ($sql) {
            return strpos($sql, "'CAPTURE1'") !== false ? '30.00' : '15.00';
        });
        $this->orderService->expects(self::once())->method('refund')->with(self::anything(), 5.0);

        $response = $this->controller()->refund(self::refundRequest('61', '45'));

        self::assertSame([['CAPTURE2', 'paypal', 5.0]], $this->sent);
        self::assertJsonPayload($response, false, 'Successfully refunded amount of 5.00 EUR');
    }

    public function testFullyRefundedOrderIsRejected(): void
    {
        \Configuration::updateValue(Settings::LABEL_REFUND_CONF, 1);
        Stubs::returns('Order->getOrderPayments', []);
        $this->orderService->expects(self::never())->method('refund');

        $response = $this->controller()->refund(self::refundRequest('61', '10'));

        self::assertJsonPayload($response, true, 'This order has no remaining Buckaroo amount to refund.');
    }

    public function testWithoutBuckarooRefundOnlyTheShopRefundIsMade(): void
    {
        \Configuration::updateValue(Settings::LABEL_REFUND_CONF, 0);
        $this->requests->expects(self::never())->method('refund');
        $this->orderService->expects(self::once())->method('refund')->with(self::anything(), 12.5);

        $response = $this->controller()->refund(self::refundRequest('61', '12.5'));

        self::assertJsonPayload($response, false, 'Successfully refunded amount of 12.50 EUR');
    }

    public function testShopRefundWithNothingLeftToCreditIsAccepted(): void
    {
        \Configuration::updateValue(Settings::LABEL_REFUND_CONF, 1);
        $this->orderService->method('refund')->willThrowException(
            new InvalidCancelProductException('no refunds', InvalidCancelProductException::NO_REFUNDS)
        );

        $response = $this->controller()->refund(self::refundRequest('61', '10'));

        self::assertSame([['CAPTURE1', 'ideal', 10.0]], $this->sent);
        self::assertJsonPayload($response, false, 'Successfully refunded amount of 10.00 EUR');
    }

    public function failureProvider(): array
    {
        return [
            'other shop refund error' => ['shop', new InvalidCancelProductException('Quantity too high', InvalidCancelProductException::QUANTITY_TOO_HIGH), 'Quantity too high'],
            'buckaroo refused the refund' => ['buckaroo', new OrderException('Refund rejected'), 'Refund rejected'],
            'error without message' => ['buckaroo', new \RuntimeException('  '), 'The refund could not be completed. Check Buckaroo Plaza for the transaction details.'],
        ];
    }

    /**
     * @dataProvider failureProvider
     */
    public function testFailuresAreReportedToTheEmployee(string $where, \Throwable $error, string $message): void
    {
        \Configuration::updateValue(Settings::LABEL_REFUND_CONF, 1);
        if ($where === 'shop') {
            $this->orderService->method('refund')->willThrowException($error);
        } else {
            $this->responses->method('parse')->willThrowException($error);
            $this->orderService->expects(self::never())->method('refund');
        }
        $controller = $this->controller();

        self::assertJsonPayload($controller->refund(self::refundRequest('61', '10')), true, $message);
        self::assertSame([['error', $message]], $controller->flashes);
    }

    // ------------------------------------------------------------ payment fee flag

    private function feeFlag($orderId, $include): JsonResponse
    {
        return $this->controller()->setRefundFeeFlag(new Request([], ['orderId' => $orderId, 'include' => $include]));
    }

    public function testFeeIsFlaggedForTheNextRefund(): void
    {
        Stubs::db('/bk_buckaroo_fee WHERE id_order = 61/', ['buckaroo_fee_tax_incl' => '2.50', 'fee_refunded' => 0]);

        self::assertJsonPayload($this->feeFlag('61', '1'), false, 'OK');
        self::assertSame(['buckaroo_include_fee_61' => 2.5], $this->session->values);
    }

    public function testFeeFlagIsCleared(): void
    {
        $this->session->set('buckaroo_include_fee_61', 2.5);

        self::assertJsonPayload($this->feeFlag('61', '0'), false, 'OK');
        self::assertSame([], $this->session->values);
        self::assertSame([], Stubs::sql());
    }

    public function feeErrorProvider(): array
    {
        return [
            'no order' => ['0', false, 'Invalid value for `orderId`'],
            'no fee row' => ['61', false, 'No payment fee found for this order'],
            'zero fee' => ['61', ['buckaroo_fee_tax_incl' => '0', 'fee_refunded' => 0], 'No payment fee found for this order'],
            'fee already refunded' => ['61', ['buckaroo_fee_tax_incl' => '2.50', 'fee_refunded' => 1], 'Payment fee has already been refunded'],
        ];
    }

    /**
     * @dataProvider feeErrorProvider
     */
    public function testFeeCannotBeFlaggedTwiceOrWithoutFee(string $orderId, $row, string $message): void
    {
        Stubs::db('/bk_buckaroo_fee/', $row);

        self::assertJsonPayload($this->feeFlag($orderId, '1'), true, $message);
        self::assertSame([], $this->session->values);
    }
}
