<?php

namespace BuckarooTest\Unit\RefundAdmin;

use Buckaroo\PrestaShop\Src\Entity\BkRefundRequest;
use Buckaroo\PrestaShop\Src\Refund\Payment\Service as PaymentService;
use Buckaroo\PrestaShop\Src\Refund\Request\Response\Handler;
use Buckaroo\PrestaShop\Src\Refund\Settings;
use Buckaroo\PrestaShop\Src\Refund\StatusService;
use Buckaroo\Transaction\Response\TransactionResponse;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderException;

require_once __DIR__ . '/StubsSupport.php';

/**
 * What happens with the Buckaroo answer to a refund request: the refund is
 * recorded (it feeds the duplicate-refund guard), failures stop the shop
 * refund, and a successful refund may book a negative order payment.
 */
class RefundResponseParseTest extends TestCase
{
    /** @var RecordingEntityManager */
    private $em;

    /** @var PaymentService&\PHPUnit\Framework\MockObject\MockObject */
    private $payments;

    /** @var StatusService&\PHPUnit\Framework\MockObject\MockObject */
    private $status;

    protected function setUp(): void
    {
        parent::setUp();
        Stubs::object('Order', 326, ['reference' => 'REF', 'total_paid' => 50.0]);
        $this->em = new RecordingEntityManager();
        $this->payments = $this->createMock(PaymentService::class);
        $this->status = $this->createMock(StatusService::class);
    }

    private function handler(): Handler
    {
        return new Handler($this->em, $this->payments, $this->status);
    }

    private static function success(array $extra = []): TransactionResponse
    {
        return new TransactionResponse(null, array_merge([
            'Status' => ['Code' => ['Code' => 190]],
            'Key' => 'REFUNDKEY',
            'AmountCredit' => 12.5,
            'Services' => [['Name' => 'ideal']],
            'RelatedTransactions' => [['RelationType' => 'refund', 'RelatedTransactionKey' => 'PAYKEY']],
        ], $extra));
    }

    private function stored(): BkRefundRequest
    {
        self::assertCount(1, $this->em->persisted);
        self::assertSame(1, $this->em->flushes);

        return $this->em->persisted[0];
    }

    public function testSuccessIsStoredAndMarksTheOrderRefunded(): void
    {
        $this->status->expects(self::once())->method('setRefunded')->with(self::callback(function ($order) {
            return $order instanceof \Order && $order->id === 326;
        }));
        $this->payments->expects(self::never())->method('create');

        $this->handler()->parse(self::success(), ['amountCredit' => 12.5], 326);

        $request = $this->stored();
        self::assertSame(BkRefundRequest::STATUS_SUCCESS, $request->getStatus());
        self::assertSame(12.5, $request->getAmount());
        self::assertSame('REFUNDKEY', $request->getKey());
        self::assertSame(326, self::getProperty($request, 'orderId'));
        self::assertSame(['amountCredit' => 12.5], self::getProperty($request, 'payload'));
        self::assertSame('REFUNDKEY', self::getProperty($request, 'data')['response']['Key']);
        self::assertInstanceOf(\DateTime::class, $request->getCreatedAt());
    }

    public function testSuccessBooksANegativePaymentWhenEnabled(): void
    {
        \Configuration::updateValue(Settings::LABEL_REFUND_CREATE_NEGATIVE_PAYMENT, 1);
        $this->payments->expects(self::once())->method('create')->with(
            self::isInstanceOf(\Order::class),
            'REFUNDKEY',
            'ideal',
            -12.5
        );

        $this->handler()->parse(self::success(), [], 326);
    }

    public function paymentKeyProvider(): array
    {
        return [
            'single related refund transaction' => [['RelationType' => 'refund', 'RelatedTransactionKey' => 'ORIG'], 'ORIG'],
            'single related transaction of another type' => [['RelationType' => 'capture', 'RelatedTransactionKey' => 'ORIG'], ''],
            'list of related transactions' => [[['RelatedTransactionKey' => 'FIRST'], ['RelatedTransactionKey' => 'SECOND']], 'FIRST'],
            'list without key' => [[['RelationType' => 'refund']], ''],
            'none' => [null, ''],
        ];
    }

    /**
     * @dataProvider paymentKeyProvider
     */
    public function testPaymentKeyIsTheRefundedTransaction($related, string $expected): void
    {
        $this->handler()->parse(self::success(['RelatedTransactions' => $related]), [], 326);

        self::assertSame($expected, self::getProperty($this->stored(), 'paymentKey'));
    }

    public function testNonScalarAmountIsStoredAsZero(): void
    {
        $this->handler()->parse(self::success(['AmountCredit' => ['12.5']]), [], 326);

        self::assertSame(0.0, $this->stored()->getAmount());
    }

    public function failureProvider(): array
    {
        return [
            'gateway error message' => [
                ['RequestErrors' => ['ChannelErrors' => [['ErrorMessage' => 'Amount too high']]]],
                'Amount too high',
            ],
            'no message: link to plaza' => [
                [],
                'Cannot create refund, check Buckaroo plaza for details https://plaza.buckaroo.nl/Transaction/Transactions/Details?transactionKey=FAILKEY',
            ],
        ];
    }

    /**
     * @dataProvider failureProvider
     */
    public function testFailureIsStoredAndStopsTheRefund(array $extra, string $message): void
    {
        \Configuration::updateValue(Settings::LABEL_REFUND_CREATE_NEGATIVE_PAYMENT, 1);
        $this->status->expects(self::never())->method('setRefunded');
        $this->payments->expects(self::never())->method('create');
        $response = new TransactionResponse(null, array_merge([
            'Status' => ['Code' => ['Code' => 490]],
            'Key' => 'FAILKEY',
        ], $extra));

        try {
            $this->handler()->parse($response, ['amountCredit' => 5.0], 326);
            self::fail('A failed refund must throw');
        } catch (OrderException $e) {
            self::assertSame($message, $e->getMessage());
        }

        $request = $this->stored();
        self::assertSame(BkRefundRequest::STATUS_FAILED, $request->getStatus());
        self::assertSame(0.0, $request->getAmount());
        self::assertSame('FAILKEY', $request->getKey());
    }
}
