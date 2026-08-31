<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Tests\Unit\Refund;

use Buckaroo\PrestaShop\Src\Entity\BkRefundRequest;
use Buckaroo\PrestaShop\Src\Refund\Payment\Service as PaymentService;
use Buckaroo\PrestaShop\Src\Refund\Request\Response\Handler as ResponseHandler;
use Buckaroo\PrestaShop\Src\Refund\StatusService;
use Buckaroo\Transaction\Response\TransactionResponse;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/RefundTestSupport.php';

/**
 * Test double for the Doctrine EntityManager that records persisted entities
 * instead of touching a database.
 */
class RecordingEntityManager extends \Doctrine\ORM\EntityManager
{
    /** @var array<int,object> */
    public $persisted = [];

    /** @var int */
    public $flushed = 0;

    public function persist($entity)
    {
        $this->persisted[] = $entity;
    }

    public function flush()
    {
        ++$this->flushed;
    }

    public function getRepository($className)
    {
        return new class {
            public function findBy($criteria)
            {
                return [];
            }
        };
    }
}

class SpyStatusService extends StatusService
{
    /** @var bool */
    public $refundedCalled = false;

    public function __construct()
    {
        // Bypass the real constructor; no EntityManager needed for the spy.
    }

    public function setRefunded(\Order $order)
    {
        $this->refundedCalled = true;
    }
}

class SpyPaymentService extends PaymentService
{
    /** @var bool */
    public $createCalled = false;

    public function create(\Order $order, string $transactionId, string $paymentMethod, float $amount)
    {
        $this->createCalled = true;
    }
}

/**
 * Verifies how the refund response handler treats Buckaroo responses:
 * a genuine error must surface (and not mark the order refunded), while a
 * successful refund must be recorded and the order status updated.
 */
class RefundResponseHandlerTest extends TestCase
{
    private function body(): array
    {
        return [
            'name' => 'fashioncheque',
            'amountCredit' => 28.80,
            'invoice' => 'GGADEYTRM_55',
            'originalTransactionKey' => 'ORIGKEY',
        ];
    }

    public function testFailedRefundThrowsAndIsRecordedAsFailed(): void
    {
        \Configuration::$values = [];
        $em = new RecordingEntityManager();
        $status = new SpyStatusService();
        $handler = new ResponseHandler($em, new SpyPaymentService(), $status);

        $failure = new TransactionResponse(null, [
            'Status' => ['Code' => ['Code' => 490]],
            'Key' => 'REFUNDTX',
            'RequestErrors' => [
                'ChannelErrors' => [
                    ['ErrorMessage' => 'An unhandled exception occurred, please contact Buckaroo Technical Support.'],
                ],
            ],
        ]);

        $thrown = null;
        try {
            $handler->parse($failure, $this->body(), 326);
        } catch (\Throwable $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'A failed refund must raise an exception');
        $this->assertStringContainsString('unhandled exception', $thrown->getMessage());
        $this->assertFalse($status->refundedCalled, 'Order must not be marked refunded on failure');
        $this->assertCount(1, $em->persisted);
        $this->assertSame(BkRefundRequest::STATUS_FAILED, $em->persisted[0]->getStatus());
    }

    public function testSuccessfulRefundIsRecordedAndOrderMarkedRefunded(): void
    {
        \Configuration::$values = []; // negative-payment creation disabled
        $em = new RecordingEntityManager();
        $status = new SpyStatusService();
        $handler = new ResponseHandler($em, new SpyPaymentService(), $status);

        $success = new TransactionResponse(null, [
            'Status' => ['Code' => ['Code' => 190]],
            'Key' => 'REFUNDTX2',
            'AmountCredit' => 28.80,
            'Services' => [['Name' => 'fashioncheque']],
            'RelatedTransactions' => [['RelatedTransactionKey' => 'ORIGKEY']],
        ]);

        $handler->parse($success, $this->body(), 326);

        $this->assertTrue($status->refundedCalled, 'Order must be marked refunded on success');
        $this->assertCount(1, $em->persisted);
        $this->assertSame(BkRefundRequest::STATUS_SUCCESS, $em->persisted[0]->getStatus());
        $this->assertEqualsWithDelta(28.80, $em->persisted[0]->getAmount(), 0.001);
    }
}
