<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Tests\Unit\Klarna;

use Buckaroo\PrestaShop\Src\Klarna\ReservationKey;
use PHPUnit\Framework\TestCase;

class ReservationKeyTest extends TestCase
{
    public function testResolveReturnsKeyWhileItIsStillThePaymentKey(): void
    {
        $key = ReservationKey::resolve(
            [ReservationKey::message('ABC123')],
            ['ABC123']
        );

        $this->assertSame('ABC123', $key);
    }

    public function testResolveReturnsNullAfterCaptureReplacesThePaymentKey(): void
    {
        $key = ReservationKey::resolve(
            [ReservationKey::message('ABC123')],
            ['CAPTURE999']
        );

        $this->assertNull($key);
    }

    public function testResolveIgnoresOtherOrderMessages(): void
    {
        $key = ReservationKey::resolve(
            [
                'Transaction Key: OTHER',
                ReservationKey::message('ABC123'),
                'Push message received.',
            ],
            ['', 'ABC123']
        );

        $this->assertSame('ABC123', $key);
    }

    public function testResolveReturnsNullWithoutAReserveMessage(): void
    {
        $this->assertNull(ReservationKey::resolve(['Transaction Key: ABC123'], ['ABC123']));
    }
}
