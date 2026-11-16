<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Src\Service;

use PHPUnit\Framework\TestCase;

/**
 * The checkout summary, the payment request total, the Buckaroo amount and the fee
 * stored on the order all come from BuckarooFeeCalculator. These tests pin the
 * rounding so the shopper never sees a total that differs from the order.
 */
class BuckarooFeeCalculatorTest extends TestCase
{
    private const PRECISION = 2;

    /**
     * Reported case: iDEAL fee 1.50 at 21% VAT, cart 23.14 + 34.75.
     */
    public function testReportedScenarioMatchesAcrossCheckoutOrderAndBuckaroo(): void
    {
        $cartTotal = 23.14 + 34.75;
        $fee = BuckarooFeeCalculator::calculate('1.50', $cartTotal, 0.21, self::PRECISION);

        $this->assertSame(1.5, $fee['buckaroo_fee_tax_excl']);
        $this->assertSame(0.32, $fee['buckaroo_fee_tax']);
        $this->assertSame(1.82, $fee['buckaroo_fee_tax_incl']);

        // Checkout summary (ajax controller) and payment request (request controller).
        $checkoutTotal = BuckarooFeeCalculator::addToTotal($cartTotal, $fee, self::PRECISION);
        $this->assertSame(59.71, $checkoutTotal);

        // Amount sent to Buckaroo: AbstractCheckout adds the fee incl. to the cart total and
        // passes it through roundBuckarooPrice(), i.e. DecimalNumber::toPrecision() (truncating).
        $amountDebit = self::truncate(self::truncate($cartTotal) + $fee['buckaroo_fee_tax_incl']);
        $this->assertSame('59.71', $amountDebit);
        $this->assertSame((string) $checkoutTotal, $amountDebit);
    }

    /** Mirrors DecimalNumber::toPrecision(2) with its default truncating mode. */
    private static function truncate($amount): string
    {
        preg_match('/^-?\d+(?:\.\d{0,2})?/', (string) $amount, $m);

        return number_format((float) $m[0], self::PRECISION, '.', '');
    }

    /**
     * @dataProvider vatRoundingBoundaries
     */
    public function testFeeTaxIsRoundedHalfUp(string $configured, float $taxRate, float $expectedTax, float $expectedIncl): void
    {
        $fee = BuckarooFeeCalculator::calculate($configured, 0.0, $taxRate, self::PRECISION);

        $this->assertSame($expectedTax, $fee['buckaroo_fee_tax']);
        $this->assertSame($expectedIncl, $fee['buckaroo_fee_tax_incl']);
    }

    public function vatRoundingBoundaries(): array
    {
        return [
            'VAT fraction below .005' => ['1.02', 0.21, 0.21, 1.23],   // 0.2142
            'VAT fraction exactly .005' => ['2.50', 0.21, 0.53, 3.03], // 0.525
            'VAT fraction exactly .005 (float noise)' => ['1.50', 0.21, 0.32, 1.82], // 0.315
            'VAT fraction above .005' => ['1.55', 0.21, 0.33, 1.88],   // 0.3255
            'reduced rate' => ['0.05', 0.09, 0.0, 0.05],               // 0.0045
            'no VAT' => ['1.50', 0.0, 0.0, 1.5],
        ];
    }

    /**
     * Excl. + tax must always equal incl. to the cent, so the stored fee rows add up.
     */
    public function testFeeComponentsAlwaysAddUp(): void
    {
        foreach ([0.0, 0.06, 0.09, 0.19, 0.20, 0.21] as $taxRate) {
            for ($cents = 1; $cents <= 1000; ++$cents) {
                $fee = BuckarooFeeCalculator::calculate(sprintf('%.2f', $cents / 100), 0.0, $taxRate, self::PRECISION);

                $this->assertSame(
                    (int) round($fee['buckaroo_fee_tax_excl'] * 100) + (int) round($fee['buckaroo_fee_tax'] * 100),
                    (int) round($fee['buckaroo_fee_tax_incl'] * 100),
                    sprintf('fee %d cents at %.2f', $cents, $taxRate)
                );
                $this->assertSame(
                    (int) round($cents * $taxRate, 0, PHP_ROUND_HALF_UP),
                    (int) round($fee['buckaroo_fee_tax'] * 100),
                    sprintf('tax for fee %d cents at %.2f', $cents, $taxRate)
                );
            }
        }
    }

    public function testPercentageFeeIsRoundedBeforeTax(): void
    {
        // 2% of 57.89 = 1.1578 -> 1.16; tax 0.2436 -> 0.24
        $fee = BuckarooFeeCalculator::calculate('2%', 57.89, 0.21, self::PRECISION);

        $this->assertSame(1.16, $fee['buckaroo_fee_tax_excl']);
        $this->assertSame(0.24, $fee['buckaroo_fee_tax']);
        $this->assertSame(1.4, $fee['buckaroo_fee_tax_incl']);
        $this->assertSame(59.29, BuckarooFeeCalculator::addToTotal(57.89, $fee, self::PRECISION));
    }

    public function testPercentageFeeAcceptsCommaDecimal(): void
    {
        // 2.5% of 100 = 2.50; tax 0.525 -> 0.53
        $fee = BuckarooFeeCalculator::calculate(' 2,5 % ', 100.0, 0.21, self::PRECISION);

        $this->assertSame(2.5, $fee['buckaroo_fee_tax_excl']);
        $this->assertSame(0.53, $fee['buckaroo_fee_tax']);
        $this->assertSame(3.03, $fee['buckaroo_fee_tax_incl']);
    }

    public function testCurrencyWithoutDecimals(): void
    {
        $fee = BuckarooFeeCalculator::calculate('150', 0.0, 0.1, 0);

        $this->assertSame(150.0, $fee['buckaroo_fee_tax_excl']);
        $this->assertSame(15.0, $fee['buckaroo_fee_tax']);
        $this->assertSame(165.0, $fee['buckaroo_fee_tax_incl']);
    }

    /**
     * @dataProvider noFeeValues
     */
    public function testNoFeeReturnsNull($configured, float $base): void
    {
        $this->assertNull(BuckarooFeeCalculator::calculate($configured, $base, 0.21, self::PRECISION));
    }

    public function noFeeValues(): array
    {
        return [
            'null' => [null, 100.0],
            'empty' => ['', 100.0],
            'zero' => ['0', 100.0],
            'negative' => ['-1.50', 100.0],
            'not numeric' => ['abc', 100.0],
            'zero percentage' => ['0%', 100.0],
            'percentage without base' => ['2%', 0.0],
        ];
    }

    public function testZeroFeeLeavesTotalUnchanged(): void
    {
        $this->assertSame(57.89, BuckarooFeeCalculator::addToTotal(57.89, null, self::PRECISION));
    }

    public function testTotalExcludingTaxAddsFeeExcludingTax(): void
    {
        $fee = BuckarooFeeCalculator::calculate('1.50', 0.0, 0.21, self::PRECISION);

        $this->assertSame(49.34, BuckarooFeeCalculator::addToTotal(47.84, $fee, self::PRECISION, false));
    }
}
