<?php

namespace BuckarooTest\Unit\RefundAdmin;

use Buckaroo\PrestaShop\Src\Entity\BkRefundRequest;
use Buckaroo\PrestaShop\Src\Refund\Payment\Service as PaymentService;
use Buckaroo\PrestaShop\Src\Refund\Push\Handler;
use Buckaroo\PrestaShop\Src\Refund\Settings;
use Buckaroo\PrestaShop\Src\Refund\StatusService;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;

require_once __DIR__ . '/StubsSupport.php';

/**
 * Refund pushes from Buckaroo: a push for a refund the shop sent updates the
 * stored request; a push for a refund made elsewhere (Plaza) is recorded and
 * may book a negative payment. Either way the order status is re-evaluated.
 */
class RefundPushHandlerTest extends TestCase
{
    /** @var RecordingRepository */
    private $repository;

    /** @var RecordingEntityManager */
    private $em;

    /** @var PaymentService&\PHPUnit\Framework\MockObject\MockObject */
    private $payments;

    /** @var StatusService&\PHPUnit\Framework\MockObject\MockObject */
    private $status;

    /** @var \Order */
    private $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new RecordingRepository();
        $this->em = new RecordingEntityManager($this->repository);
        $this->payments = $this->createMock(PaymentService::class);
        $this->status = $this->createMock(StatusService::class);
        $this->order = new \Order();
        $this->order->id = 90;
        $order = $this->order;
        Stubs::returns('Order::getByCartId', function ($cartId) use ($order) {
            return $cartId === 44 ? $order : null;
        });
    }

    private function push(array $post): Handler
    {
        $this->request([], $post);

        return new Handler($this->em, $this->payments, $this->status);
    }

    private static function refundPush(array $override = []): array
    {
        return array_merge([
            'brq_invoicenumber' => 'REF_44',
            'brq_transactions' => 'REFUNDKEY',
            'brq_relatedtransaction_refund' => 'PAYKEY',
            'brq_amount_credit' => '15.20',
            'brq_statuscode' => '190',
            'brq_transaction_method' => 'ideal',
        ], $override);
    }

    public function testPushForAKnownRefundUpdatesItsStatus(): void
    {
        $known = (new BkRefundRequest())->setStatus(BkRefundRequest::STATUS_FAILED)->setAmount(15.2);
        $this->repository->one = $known;
        \Configuration::updateValue(Settings::LABEL_REFUND_CREATE_NEGATIVE_PAYMENT, 1);
        $this->payments->expects(self::never())->method('create');
        $this->status->expects(self::once())->method('setRefunded')->with($this->order);

        $this->push(self::refundPush())->handle();

        self::assertSame([[['key' => 'REFUNDKEY'], null]], $this->repository->criteria);
        self::assertSame(BkRefundRequest::STATUS_SUCCESS, $known->getStatus());
        self::assertSame(['pushes' => [self::refundPush()]], self::getProperty($known, 'data'));
        self::assertSame([[$known]], $this->em->flushArgs);
        self::assertSame([], $this->em->persisted);
    }

    public function testPushIsAppendedToTheStoredRefundData(): void
    {
        $previousPush = ['brq_statuscode' => '791'];
        $known = (new BkRefundRequest())
            ->setStatus(BkRefundRequest::STATUS_SUCCESS)
            ->setData(['response' => ['key' => 'REFUNDKEY'], 'pushes' => [$previousPush]]);
        $this->repository->one = $known;

        $this->push(self::refundPush())->handle();

        self::assertSame(
            ['response' => ['key' => 'REFUNDKEY'], 'pushes' => [$previousPush, self::refundPush()]],
            self::getProperty($known, 'data')
        );
    }

    public function testFailedPushMarksAKnownRefundFailed(): void
    {
        $known = (new BkRefundRequest())->setStatus(BkRefundRequest::STATUS_SUCCESS);
        $this->repository->one = $known;

        $this->push(self::refundPush(['brq_statuscode' => '490']))->handle();

        self::assertSame(BkRefundRequest::STATUS_FAILED, $known->getStatus());
    }

    public function testRefundFromPlazaIsRecordedWithANegativePayment(): void
    {
        \Configuration::updateValue(Settings::LABEL_REFUND_CREATE_NEGATIVE_PAYMENT, 1);
        $this->payments->expects(self::once())->method('create')->with($this->order, 'REFUNDKEY', 'ideal', -15.2);
        $this->status->expects(self::once())->method('setRefunded')->with($this->order);

        $this->push(self::refundPush(['brq_invoicenumber' => urlencode('REF_44_x')]))->handle();

        self::assertCount(1, $this->em->persisted);
        $request = $this->em->persisted[0];
        self::assertSame(BkRefundRequest::STATUS_SUCCESS, $request->getStatus());
        self::assertSame(15.2, $request->getAmount());
        self::assertSame('REFUNDKEY', $request->getKey());
        self::assertSame('PAYKEY', self::getProperty($request, 'paymentKey'));
        self::assertSame(90, self::getProperty($request, 'orderId'));
        self::assertSame([], self::getProperty($request, 'payload'));
    }

    public function negativePaymentProvider(): array
    {
        return [
            'setting disabled' => [0, '190'],
            'refund failed' => [1, '490'],
        ];
    }

    /**
     * @dataProvider negativePaymentProvider
     */
    public function testNoNegativePaymentWhenDisabledOrFailed(int $setting, string $status): void
    {
        \Configuration::updateValue(Settings::LABEL_REFUND_CREATE_NEGATIVE_PAYMENT, $setting);
        $this->payments->expects(self::never())->method('create');

        $this->push(self::refundPush(['brq_statuscode' => $status]))->handle();

        self::assertSame(
            $status === '190' ? BkRefundRequest::STATUS_SUCCESS : BkRefundRequest::STATUS_FAILED,
            $this->em->persisted[0]->getStatus()
        );
    }

    public function invalidPushProvider(): array
    {
        return [
            'zero amount' => [['brq_amount_credit' => '0'], 'Invalid refund amount'],
            'array amount' => [['brq_amount_credit' => ['1']], 'Invalid refund amount'],
            'missing related payment' => [['brq_relatedtransaction_refund' => null], 'Invalid value for `brq_relatedtransaction_refund`'],
            'missing refund key' => [['brq_transactions' => null], 'Invalid value for `brq_transactions`'],
        ];
    }

    /**
     * @dataProvider invalidPushProvider
     */
    public function testInvalidPushForAnUnknownRefundIsRejected(array $override, string $cause): void
    {
        $this->status->expects(self::never())->method('setRefunded');

        try {
            $this->push(array_filter(self::refundPush($override), function ($value) {
                return $value !== null;
            }))->handle();
            self::fail('The push must be rejected');
        } catch (\Exception $e) {
            self::assertSame('Cannot update order with refund', $e->getMessage());
            self::assertSame($cause, $e->getPrevious()->getMessage());
        }
        self::assertSame([], $this->em->persisted);
    }

    public function noOrderProvider(): array
    {
        return [
            'no invoice number' => [['brq_invoicenumber' => null]],
            'invoice number without cart id' => [['brq_invoicenumber' => 'REF']],
        ];
    }

    /**
     * @dataProvider noOrderProvider
     */
    public function testPushWithoutOrderIsRejected(array $override): void
    {
        $this->expectExceptionMessage('Cannot determine order');

        $this->push(array_filter(self::refundPush($override), function ($value) {
            return $value !== null;
        }))->handle();
    }

    public function testRefundIsSuccessfulOnlyForStatus190(): void
    {
        self::assertTrue($this->push(['brq_statuscode' => '190'])->refundIsSuccessful());
        self::assertFalse($this->push(['brq_statuscode' => 190])->refundIsSuccessful());
        self::assertFalse($this->push([])->refundIsSuccessful());
    }
}
