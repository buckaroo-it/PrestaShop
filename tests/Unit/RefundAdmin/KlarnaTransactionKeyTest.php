<?php

namespace BuckarooTest\Unit\RefundAdmin;

use Buckaroo\PrestaShop\Src\Refund\KlarnaTransactionKey;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;

require_once __DIR__ . '/StubsSupport.php';

/**
 * Klarna refunds must use the capture (Pay, C339) transaction key, not the
 * reservation key. These tests cover recognising the capture push and storing
 * its key on the order payment that refunds are sent against.
 */
class KlarnaTransactionKeyTest extends TestCase
{
    public function capturePushProvider(): array
    {
        $capture = ['payment_method' => 'KlarnaKP', 'brq_transaction_type' => 'c339', 'transactions' => 'CAPTUREKEY'];

        return [
            'klarna capture' => [$capture, true, true],
            'not klarna' => [['payment_method' => 'ideal'] + $capture, true, false],
            'missing method' => [['brq_transaction_type' => 'C339', 'transactions' => 'K'], true, false],
            'refund push of a klarna order' => [$capture + ['brq_relatedtransaction_refund' => 'ORIG'], true, false],
            'reservation (other transaction type)' => [['brq_transaction_type' => 'I038'] + $capture, true, false],
            'failed capture' => [$capture, false, false],
            'capture without transaction key' => [['transactions' => '  '] + $capture, true, false],
            'missing transaction type' => [['payment_method' => 'klarna', 'transactions' => 'K'], true, false],
        ];
    }

    /**
     * @dataProvider capturePushProvider
     */
    public function testIsCapturePush(array $props, bool $succeeded, bool $expected): void
    {
        self::assertSame($expected, KlarnaTransactionKey::isCapturePush(new PushResponse($props, $succeeded)));
    }

    private static function payment(string $key, float $amount): \OrderPayment
    {
        $payment = new \OrderPayment();
        $payment->transaction_id = $key;
        $payment->amount = $amount;

        return $payment;
    }

    private static function order(): \Order
    {
        $order = new \Order();
        $order->id = 5;
        $order->reference = 'KLARNAREF';

        return $order;
    }

    public function testCaptureKeyReplacesTheReservationKeyOnTheFirstPositivePayment(): void
    {
        $refund = self::payment('REFUND', -5.0);
        $capture = self::payment('RESERVATION', 50.0);
        $other = self::payment('OTHER', 10.0);
        Stubs::returns('OrderPayment::getByOrderReference', function ($reference) use ($refund, $capture, $other) {
            return $reference === 'KLARNAREF' ? [$refund, $capture, $other] : [];
        });

        KlarnaTransactionKey::storeCaptureKey(self::order(), '  CAPTURE  ');

        self::assertSame('CAPTURE', $capture->transaction_id);
        self::assertSame('REFUND', $refund->transaction_id);
        self::assertSame('OTHER', $other->transaction_id);
        self::assertSame([$capture], Stubs::persisted('OrderPayment', 'update'));
    }

    public function testKeyAlreadyStoredIsNotUpdatedAgain(): void
    {
        $capture = self::payment('CAPTURE', 50.0);
        $other = self::payment('OTHER', 10.0);
        Stubs::returns('OrderPayment::getByOrderReference', [$capture, $other]);

        KlarnaTransactionKey::storeCaptureKey(self::order(), 'CAPTURE');

        self::assertSame([], Stubs::persisted('OrderPayment'));
        self::assertSame('OTHER', $other->transaction_id);
    }

    public function skippedProvider(): array
    {
        return [
            'blank key' => ['   ', true],
            'order not loaded' => ['CAPTURE', false],
        ];
    }

    /**
     * @dataProvider skippedProvider
     */
    public function testNothingIsStoredForBlankKeyOrMissingOrder(string $key, bool $loaded): void
    {
        $order = self::order();
        if (!$loaded) {
            $order->id = null;
        }

        KlarnaTransactionKey::storeCaptureKey($order, $key);

        self::assertSame(0, Stubs::callCount('OrderPayment::getByOrderReference'));
    }

    public function testNonArrayPaymentListIsTreatedAsEmpty(): void
    {
        Stubs::returns('OrderPayment::getByOrderReference', false);

        KlarnaTransactionKey::storeCaptureKey(self::order(), 'CAPTURE');

        self::assertSame(1, Stubs::callCount('OrderPayment::getByOrderReference'));
        self::assertSame([], Stubs::persisted('OrderPayment'));
    }
}
