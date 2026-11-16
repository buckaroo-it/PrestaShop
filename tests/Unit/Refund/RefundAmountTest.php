<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Tests\Unit\Refund;

use Buckaroo\PrestaShop\Src\Refund\RefundAmount;
use PHPUnit\Framework\TestCase;

/**
 * The merchant-entered refund amount is checked against the maximum shown in
 * the refunds block before any refund is sent. An amount above the maximum is
 * refused, never capped to the maximum.
 */
class RefundAmountTest extends TestCase
{
    public function testAmountsUpToTheMaximumAreValid(): void
    {
        $cases = [
            'partial' => '9.99',
            'one cent below' => '24.95',
            'equal' => '24.96',
            'equal with trailing zero' => '24.960',
            'smallest amount' => '0.01',
            'float input' => 24.96,
        ];

        foreach ($cases as $case => $requested) {
            $this->assertSame(RefundAmount::VALID, RefundAmount::validate($requested, 24.96), $case);
        }
    }

    public function testAmountsAboveTheMaximumAreRefused(): void
    {
        $cases = [
            'typo' => '999',
            'one cent above' => '24.97',
            'trailing zeros above' => '24.9700',
        ];

        foreach ($cases as $case => $requested) {
            $this->assertSame(RefundAmount::EXCEEDS_MAXIMUM, RefundAmount::validate($requested, 24.96), $case);
        }
    }

    public function testInvalidAmountsAreRefused(): void
    {
        $cases = [
            'empty' => '',
            'whitespace' => '  ',
            'non-numeric' => 'abc',
            'zero' => '0',
            'zero decimals' => '0.00',
            'negative' => '-5',
            'more than two decimals' => '9.999',
            'exponent' => '1e3',
            'comma' => '9,99',
            'null' => null,
            'bool' => true,
            'array' => ['10'],
        ];

        foreach ($cases as $case => $requested) {
            $this->assertSame(RefundAmount::INVALID, RefundAmount::validate($requested, 24.96), $case);
        }
    }

    public function testNothingLeftToRefund(): void
    {
        $this->assertSame(RefundAmount::NOTHING_TO_REFUND, RefundAmount::validate('1', 0.0));
    }

    public function testToCentsKeepsTheEnteredAmount(): void
    {
        $this->assertSame(999, RefundAmount::toCents('9.99'));
        $this->assertSame(2496, RefundAmount::toCents('24.960'));
        $this->assertSame(99900, RefundAmount::toCents('999'));
        $this->assertSame(10, RefundAmount::toCents('0.1'));
        $this->assertSame(2496, RefundAmount::floatToCents(24.96));
        $this->assertSame(29, RefundAmount::floatToCents(0.29));
    }
}
