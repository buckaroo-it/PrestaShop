<?php

namespace BuckarooTest\Unit\RefundAdmin;

use Buckaroo\PrestaShop\Src\Refund\RefundSplit;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;

require_once __DIR__ . '/StubsSupport.php';

/**
 * RefundSplit decides how much of a refund goes to each Buckaroo capture. It is
 * the guard against refunding a transaction twice, so the cases below cover
 * amounts already refunded, captures without a key and rounding at the cent.
 */
class RefundSplitTest extends TestCase
{
    private static function payment(string $key, float $amount): \OrderPayment
    {
        $payment = new \OrderPayment();
        $payment->transaction_id = $key;
        $payment->amount = $amount;
        $payment->payment_method = 'ideal';

        return $payment;
    }

    /**
     * @param array<string, float> $alreadyRefunded transaction key => refunded sum
     */
    private function refunded(array $alreadyRefunded): void
    {
        Stubs::db('/bk_refund_request/', function ($sql) use ($alreadyRefunded) {
            foreach ($alreadyRefunded as $key => $sum) {
                if (strpos($sql, "'" . $key . "'") !== false) {
                    return (string) $sum;
                }
            }

            return '0';
        });
    }

    public function splitProvider(): array
    {
        return [
            'single capture covers the refund' => [
                [['A', 50.0]], [], 20.0, [['A', 20.0]],
            ],
            'refund spills over to the second capture' => [
                [['A', 30.0], ['B', 20.0]], [], 45.0, [['A', 30.0], ['B', 15.0]],
            ],
            'already refunded part of the first capture is skipped' => [
                [['A', 30.0], ['B', 20.0]], ['A' => 25.0], 10.0, [['A', 5.0], ['B', 5.0]],
            ],
            'fully refunded capture is skipped' => [
                [['A', 30.0], ['B', 20.0]], ['A' => 30.0], 10.0, [['B', 10.0]],
            ],
            'negative (refund) payments and payments without key are ignored' => [
                [['R', -10.0], ['', 40.0], ['A', 0.0], ['B', 25.0]], [], 25.0, [['B', 25.0]],
            ],
            'refund larger than everything left is capped per capture' => [
                [['A', 10.0], ['B', 5.0]], ['B' => 2.5], 100.0, [['A', 10.0], ['B', 2.5]],
            ],
            'amount is rounded to cents' => [
                [['A', 10.0]], [], 3.333, [['A', 3.33]],
            ],
            'less than a cent left on a capture is not refunded' => [
                [['A', 10.0], ['B', 10.0]], ['A' => 9.996], 5.0, [['B', 5.0]],
            ],
            'nothing to refund' => [
                [['A', 10.0]], [], 0.004, [],
            ],
            'no captures at all' => [
                [], [], 10.0, [],
            ],
        ];
    }

    /**
     * @dataProvider splitProvider
     */
    public function testChunksSplitTheRefundOverTheCaptures(array $payments, array $alreadyRefunded, float $amount, array $expected): void
    {
        $this->refunded($alreadyRefunded);
        $objects = [];
        foreach ($payments as $payment) {
            $objects[] = self::payment($payment[0], $payment[1]);
        }

        $chunks = RefundSplit::chunks($objects, $amount);

        $actual = array_map(function ($chunk) {
            return [(string) $chunk['payment']->transaction_id, $chunk['amount']];
        }, $chunks);
        self::assertSame($expected, $actual);
    }

    public function testOnlySuccessfulRefundsOfThatTransactionCount(): void
    {
        $this->refunded([]);

        RefundSplit::chunks([self::payment("KEY'1", 10.0)], 5.0);

        $sql = Stubs::sql('getValue')[0];
        self::assertStringContainsString('FROM `ps_bk_refund_request`', $sql);
        self::assertStringContainsString("`payment_key` = 'KEY\\'1'", $sql);
        self::assertStringContainsString("`status` = 'success'", $sql);
    }

    public function testStopsQueryingOnceTheRefundIsCovered(): void
    {
        $this->refunded([]);

        RefundSplit::chunks([self::payment('A', 10.0), self::payment('B', 10.0)], 10.0);

        self::assertCount(1, Stubs::sql('getValue'));
    }

    public function testBlankTransactionKeyCountsAsNothingRefunded(): void
    {
        $method = new \ReflectionMethod(RefundSplit::class, 'alreadyRefunded');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }

        self::assertSame(0.0, $method->invoke(null, '   '));
        self::assertSame([], Stubs::sql());
    }
}
