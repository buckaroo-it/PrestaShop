<?php

namespace BuckarooTest\Unit\RefundAdmin;

use Buckaroo\PrestaShop\Src\Entity\BkRefundRequest;
use Buckaroo\PrestaShop\Src\Refund\Admin\Provider;
use BuckarooTest\TestCase;
use PrestaShopBundle\Service\Routing\Router;

require_once __DIR__ . '/StubsSupport.php';

/** Data for the Buckaroo refund block on the admin order page. */
class RefundProviderTest extends TestCase
{
    private static function refund(float $amount, string $status): BkRefundRequest
    {
        return (new BkRefundRequest())->setAmount($amount)->setStatus($status);
    }

    public function availableProvider(): array
    {
        return [
            'nothing refunded' => [[], 80.0],
            'failed refunds do not count' => [[[30.0, BkRefundRequest::STATUS_FAILED]], 80.0],
            'successful refunds are deducted' => [[[30.0, BkRefundRequest::STATUS_SUCCESS], [10.125, BkRefundRequest::STATUS_SUCCESS]], 39.88],
            'fully refunded' => [[[79.996, BkRefundRequest::STATUS_SUCCESS]], 0.0],
        ];
    }

    /**
     * @dataProvider availableProvider
     */
    public function testMaxAvailableAmountExcludesSuccessfulRefunds(array $refunds, float $expected): void
    {
        $rows = array_map(function ($refund) {
            return self::refund($refund[0], $refund[1]);
        }, $refunds);
        $repository = new RecordingRepository($rows);
        $em = new RecordingEntityManager($repository);
        $order = new \Order();
        $order->id = 15;
        $order->id_currency = 2;
        $order->total_paid = 80.0;

        $data = (new Provider($em, new Router()))->get($order);

        self::assertSame(15, $data['orderId']);
        self::assertSame(2, $data['currencyId']);
        self::assertSame($rows, $data['refunds']);
        self::assertSame($expected, $data['maxAvailableAmount']);
        self::assertSame('/admin/buckaroo_refund', $data['ajaxUrl']);
        self::assertSame([BkRefundRequest::class], $em->repositoryRequests);
        self::assertSame([[['orderId' => 15], ['createdAt' => 'desc']]], $repository->criteria);
    }
}
