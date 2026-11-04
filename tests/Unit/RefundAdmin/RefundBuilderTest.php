<?php

namespace BuckarooTest\Unit\RefundAdmin;

use Buckaroo\PrestaShop\Src\Refund\Request\Builder;
use Buckaroo\PrestaShop\Src\Refund\Request\QuantityBasedBuilder;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;
use PrestaShop\PrestaShop\Adapter\Order\Refund\OrderRefundSummary;

require_once __DIR__ . '/StubsSupport.php';

/**
 * Refund request bodies sent to Buckaroo: the common fields, card/gift card
 * issuer data and the article lines required by the pay-after-delivery methods.
 */
class RefundBuilderTest extends TestCase
{
    /** @var string|null */
    private $remoteAddr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->remoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;
        $_SERVER['REMOTE_ADDR'] = '10.0.0.7';
        \Currency::$isoById = [2 => 'USD'];
        Stubs::db('/bk_giftcards/', [['code' => 'fashioncheque'], ['name' => 'no code']]);
    }

    protected function tearDown(): void
    {
        if ($this->remoteAddr === null) {
            unset($_SERVER['REMOTE_ADDR']);
        } else {
            $_SERVER['REMOTE_ADDR'] = $this->remoteAddr;
        }
        parent::tearDown();
    }

    private static function order(): \Order
    {
        $order = new \Order();
        $order->id = 77;
        $order->id_cart = 31;
        $order->id_currency = 2;

        return $order;
    }

    private static function payment(string $method): \OrderPayment
    {
        $payment = new \OrderPayment();
        $payment->order_reference = 'ORDREF';
        $payment->transaction_id = 'TXKEY';
        $payment->payment_method = $method;

        return $payment;
    }

    /**
     * @param array<string, mixed> $values method => return value
     */
    private static function summary(array $values): OrderRefundSummary
    {
        $summary = new OrderRefundSummary();
        $defaults = [
            'getRefundedAmount' => 0.0,
            'isVoucherChosen' => false,
            'getVoucherAmount' => 0.0,
            'getProductRefunds' => [],
            'getRefundedShipping' => 0.0,
            'getOrderDetailById' => null,
        ];
        foreach (array_merge($defaults, $values) as $method => $value) {
            $summary->stub($method, $value);
        }

        return $summary;
    }

    private static function detail(int $productId, string $name, $taxList): \OrderDetail
    {
        $detail = new \OrderDetail();
        $detail->product_id = $productId;
        $detail->product_name = $name;
        $detail->stub('getTaxList', $taxList);

        return $detail;
    }

    public function testCommonFieldsIdentifyTheOrderAndTheOriginalTransaction(): void
    {
        $body = (new Builder())->create(self::order(), self::payment('ideal'), self::summary(['getRefundedAmount' => 12.345]));

        self::assertSame([
            'order' => 'ORDREF_31',
            'invoice' => 'ORDREF_31',
            'amountCredit' => 12.35,
            'currency' => 'USD',
            'pushURL' => 'https://shop.test/module/buckaroo3/userreturn',
            'pushURLFailure' => 'https://shop.test/module/buckaroo3/userreturn',
            'clientIP' => ['address' => '10.0.0.7', 'type' => 0],
            'originalTransactionKey' => 'TXKEY',
            'additionalParameters' => ['orderId' => 77],
        ], $body);
    }

    public function amountProvider(): array
    {
        return [
            'refund summary amount' => [20.0, 0.0, null, 20.0],
            'payment fee is added' => [20.0, 2.5, null, 22.5],
            'override (split refund chunk) wins' => [20.0, 2.5, 7.004, 7.0],
        ];
    }

    /**
     * @dataProvider amountProvider
     */
    public function testAmountCredit(float $refunded, float $fee, ?float $override, float $expected): void
    {
        $body = (new Builder())->create(self::order(), self::payment('ideal'), self::summary(['getRefundedAmount' => $refunded]), $fee, $override);

        self::assertSame($expected, $body['amountCredit']);
        self::assertArrayNotHasKey('articles', $body);
    }

    public function issuerProvider(): array
    {
        return [
            'credit card brand' => ['visa', ['name' => 'visa', 'version' => 2]],
            'generic gift card' => ['giftcard', ['name' => 'giftcard']],
            'gift card service code' => ['fashioncheque', ['name' => 'fashioncheque']],
            'other method' => ['ideal', []],
        ];
    }

    /**
     * @dataProvider issuerProvider
     */
    public function testIssuers(string $method, array $expected): void
    {
        $body = (new QuantityBasedBuilder())->create(self::order(), self::payment($method), 5.0);

        $issuer = array_intersect_key($body, ['name' => true, 'version' => true]);
        self::assertSame($expected, $issuer);
    }

    public function testGiftCardLookupFailureMeansNotAGiftCard(): void
    {
        Stubs::$dbResponders = [];
        Stubs::db('/bk_giftcards/', function () {
            throw new \Exception('db down');
        });

        $body = (new QuantityBasedBuilder())->create(self::order(), self::payment('somecard'), 5.0);

        self::assertArrayNotHasKey('name', $body);
    }

    public function quantityArticlesProvider(): array
    {
        return [['afterpay'], ['billink']];
    }

    /**
     * @dataProvider quantityArticlesProvider
     */
    public function testQuantityBasedBuilderSendsOneAmountLineForPayAfterDelivery(string $method): void
    {
        $body = (new QuantityBasedBuilder())->create(self::order(), self::payment($method), 15.456);

        self::assertSame(15.46, $body['amountCredit']);
        self::assertSame([[
            'refundType' => 'Return',
            'identifier' => 'amount_refund',
            'description' => 'Refund amount of 15.46',
            'quantity' => 1,
            'price' => 15.46,
            'vatPercentage' => 0,
        ]], $body['articles']);
    }

    public function testQuantityBasedBuilderHasNoArticlesForOtherMethods(): void
    {
        $body = (new QuantityBasedBuilder())->create(self::order(), self::payment('ideal'), 15.0);

        self::assertArrayNotHasKey('articles', $body);
        self::assertSame(15.0, $body['amountCredit']);
    }

    public function testVoucherRefundIsSentAsAnAmountLinePlusShippingAndFee(): void
    {
        $summary = self::summary([
            'getRefundedAmount' => 25.0,
            'isVoucherChosen' => true,
            'getVoucherAmount' => 20.0,
            'getRefundedShipping' => 5.0,
        ]);

        $body = (new Builder())->create(self::order(), self::payment('afterpay'), $summary, 1.5);

        self::assertSame(26.5, $body['amountCredit']);
        self::assertSame(
            [['amount_refund', 1, 20.0], ['shipping', 1, 5.0], ['buckaroo_payment_fee', 1, 1.5]],
            self::lines($body['articles'])
        );
        self::assertSame('Refund amount of 20', $body['articles'][0]['description']);
    }

    public function testProductLinesUseUnitPricesAndBalanceRoundingDifferences(): void
    {
        $summary = self::summary([
            'getRefundedAmount' => 18.0,
            'getProductRefunds' => [
                11 => ['amount' => 10.0, 'quantity' => 6],
                13 => ['amount' => 0.0, 'quantity' => 1],
                14 => ['quantity' => 1],
            ],
            'getOrderDetailById' => function ($id) {
                return $id === 11
                    ? self::detail(501, 'Shirt', [['rate' => 21]])
                    : self::detail(502, 'Socks', false);
            },
            'getVoucherAmount' => 2.0,
            'getRefundedShipping' => 10.0,
        ]);

        $body = (new Builder())->create(self::order(), self::payment('billink'), $summary);

        self::assertSame([
            [501, 6, 1.67],
            ['amount_discount', 1, -2.0],
            ['shipping', 1, 10.0],
            ['rounding_errors', 1, -0.02],
        ], array_map(function ($line) {
            return [$line[0], $line[1], round($line[2], 2)];
        }, self::lines($body['articles'])));
        self::assertSame('Shirt', $body['articles'][0]['description']);
        self::assertEquals(0, $body['articles'][0]['vatPercentage']);
        self::assertSame('Discount amount of 2', $body['articles'][1]['description']);
        self::assertSame('Rounding errors', $body['articles'][3]['description']);

        $sum = array_sum(array_map(function ($line) {
            return $line['price'] * $line['quantity'];
        }, $body['articles']));
        self::assertEqualsWithDelta($body['amountCredit'], $sum, 0.001, 'article lines must add up to the refunded amount');
    }

    /**
     * BUG (src/Refund/Request/Builder.php, rounding check): the difference is
     * compared unrounded, so a one-cent difference (18.00 vs 17.99 in floating
     * point = 0.0099999...) is below 0.01 and no rounding line is added. The
     * article lines then add up to 17.99 while amountCredit is 18.00.
     * This test pins the current behaviour; update it when the source is fixed.
     */
    public function testOneCentRoundingDifferenceIsBalanced(): void
    {
        $summary = self::summary([
            'getRefundedAmount' => 18.0,
            'getProductRefunds' => [11 => ['amount' => 10.0, 'quantity' => 3]],
            'getOrderDetailById' => self::detail(501, 'Shirt', []),
            'getVoucherAmount' => 2.0,
            'getRefundedShipping' => 10.0,
        ]);

        $body = (new Builder())->create(self::order(), self::payment('afterpay'), $summary);

        self::assertSame(18.0, $body['amountCredit']);
        $rounding = array_values(array_filter($body['articles'], function ($line) {
            return $line['identifier'] === 'rounding_errors';
        }));
        self::assertCount(1, $rounding);
        self::assertSame(0.01, $rounding[0]['price']);
        self::assertSame(1, $rounding[0]['quantity']);
        $sum = array_sum(array_map(function ($line) {
            return $line['price'] * $line['quantity'];
        }, $body['articles']));
        self::assertEqualsWithDelta(18.0, $sum, 0.001);
    }

    public function testLineWithoutQuantityUsesTheLineTotalAsPrice(): void
    {
        $summary = self::summary([
            'getRefundedAmount' => 4.0,
            'getProductRefunds' => [12 => ['amount' => 4.0, 'quantity' => 0]],
            'getOrderDetailById' => self::detail(502, 'Socks', false),
        ]);

        $body = (new Builder())->create(self::order(), self::payment('afterpay'), $summary);

        self::assertSame([502, 0, 4.0], self::lines($body['articles'])[0]);
        self::assertEquals(0, $body['articles'][0]['vatPercentage']);
    }

    public function testNoRoundingLineWhenLinesAddUp(): void
    {
        $summary = self::summary([
            'getRefundedAmount' => 10.0,
            'getProductRefunds' => [11 => ['amount' => 10.0, 'quantity' => 2]],
            'getOrderDetailById' => self::detail(501, 'Shirt', []),
        ]);

        $body = (new Builder())->create(self::order(), self::payment('afterpay'), $summary);

        self::assertSame([[501, 2, 5.0]], self::lines($body['articles']));
    }

    /**
     * @return array<int, array{0: mixed, 1: int, 2: float}>
     */
    private static function lines(array $articles): array
    {
        return array_map(function ($line) {
            return [$line['identifier'], $line['quantity'], $line['price']];
        }, $articles);
    }
}
