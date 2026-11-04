<?php

namespace BuckarooTest\Unit\RefundAdmin;

use Buckaroo\PrestaShop\Src\Entity\BkRefundRequest;
use Buckaroo\PrestaShop\Src\Refund\StatusService;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;

require_once __DIR__ . '/StubsSupport.php';

/**
 * StatusService moves an order to "Refunded" or "Partially refunded" based on
 * the successful Buckaroo refunds (or, when the Buckaroo refund is disabled,
 * on the PrestaShop credit slips).
 */
class RefundStatusServiceTest extends TestCase
{
    private const REFUNDED = 7;
    private const PARTIAL = 31;

    /** @var RecordingRepository */
    private $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new RecordingRepository();
        \Configuration::updateValue('PS_OS_REFUND', self::REFUNDED);
        \Configuration::updateValue('PS_CHECKOUT_STATE_PARTIALLY_REFUNDED', self::PARTIAL);
    }

    private function order(int $currentState = 2): \Order
    {
        $order = new \Order();
        $order->id = 61;
        $order->id_customer = 4;
        $order->total_paid = 100.0;
        $state = null;
        if ($currentState > 0) {
            $state = new \OrderState();
            $state->id = $currentState;
        }
        $order->stub('getCurrentOrderState', $state);

        return $order;
    }

    private function refunds(array $amounts): void
    {
        $this->repository->rows = array_map(function ($amount) {
            return (new BkRefundRequest())->setAmount($amount)->setStatus(BkRefundRequest::STATUS_SUCCESS);
        }, $amounts);
    }

    private function setRefunded(\Order $order): void
    {
        (new StatusService(new RecordingEntityManager($this->repository)))->setRefunded($order);
    }

    /** @return array<int, int> new states written to the order history */
    private static function newStates(): array
    {
        return array_map(function ($args) {
            return $args[0];
        }, Stubs::callsTo('OrderHistory->changeIdOrderState'));
    }

    public function statusProvider(): array
    {
        return [
            'fully refunded' => [[60.0, 40.0], 2, [self::REFUNDED]],
            'refunded within half a cent' => [[99.996], 2, [self::REFUNDED]],
            'partially refunded' => [[30.0], 2, [self::PARTIAL]],
            'partial refund of an order in partial state' => [[30.0], self::PARTIAL, []],
            'fully refunded order already refunded' => [[100.0], self::REFUNDED, []],
            'partial becomes full' => [[30.0, 70.0], self::PARTIAL, [self::REFUNDED]],
            'nothing refunded' => [[], 2, []],
            'order without current state' => [[100.0], 0, [self::REFUNDED]],
        ];
    }

    /**
     * @dataProvider statusProvider
     */
    public function testOrderStatusFollowsTheBuckarooRefunds(array $refunds, int $currentState, array $expected): void
    {
        $this->refunds($refunds);

        $this->setRefunded($this->order($currentState));

        self::assertSame($expected, self::newStates());
        self::assertSame(['orderId' => 61, 'status' => BkRefundRequest::STATUS_SUCCESS], $this->repository->criteria[0][0]);
        if ($expected) {
            $history = Stubs::callsTo('OrderHistory->addWithemail');
            self::assertSame([[false]], $history);
            self::assertSame(61, Stubs::callsTo('OrderHistory->changeIdOrderState')[0][1]);
        }
    }

    public function slipProvider(): array
    {
        return [
            'credit slips cover the order' => [[['total_products_tax_incl' => '90.00', 'total_shipping_tax_incl' => '10.00']], [self::REFUNDED]],
            'credit slip for part of the order' => [[['total_products_tax_incl' => '20.00', 'total_shipping_tax_incl' => '0']], [self::PARTIAL]],
            'no credit slips' => [[], []],
        ];
    }

    /**
     * @dataProvider slipProvider
     */
    public function testCreditSlipsAreUsedWhenThereAreNoBuckarooRefunds(array $slips, array $expected): void
    {
        Stubs::returns('OrderSlip::getOrdersSlip', function ($customerId, $orderId) use ($slips) {
            return $customerId === 4 && $orderId === 61 ? $slips : [['total_products_tax_incl' => 1000, 'total_shipping_tax_incl' => 0]];
        });

        $this->setRefunded($this->order());

        self::assertSame($expected, self::newStates());
    }

    public function missingStatesProvider(): array
    {
        return [
            'no states configured' => [false, false, [100.0], []],
            'only partial state: full refund leaves the order alone' => [false, self::PARTIAL, [100.0], []],
            'only partial state: partial refund' => [false, self::PARTIAL, [10.0], [self::PARTIAL]],
            'only refunded state: partial refund leaves the order alone' => [self::REFUNDED, false, [10.0], []],
        ];
    }

    /**
     * @dataProvider missingStatesProvider
     */
    public function testUnconfiguredStatesAreNeverWritten($refunded, $partial, array $refunds, array $expected): void
    {
        \Configuration::$values = [];
        if ($refunded) {
            \Configuration::updateValue('PS_OS_REFUND', $refunded);
        }
        if ($partial) {
            \Configuration::updateValue('PS_CHECKOUT_STATE_PARTIALLY_REFUNDED', $partial);
        }
        $this->refunds($refunds);

        $this->setRefunded($this->order());

        self::assertSame($expected, self::newStates());
    }

    public function testUpdateWritesAnOrderHistoryEntry(): void
    {
        (new StatusService(new RecordingEntityManager()))->update(61, '7');

        self::assertSame([[7, 61]], Stubs::callsTo('OrderHistory->changeIdOrderState'));
        self::assertSame([[false]], Stubs::callsTo('OrderHistory->addWithemail'));
    }

    public function testUpdateIgnoresAnInvalidState(): void
    {
        (new StatusService(new RecordingEntityManager()))->update(61, 0);

        self::assertSame([], Stubs::callsTo('OrderHistory->changeIdOrderState'));
    }
}
