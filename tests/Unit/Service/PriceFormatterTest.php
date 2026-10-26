<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * It is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this file
 *
 *  @author    Buckaroo.nl <plugins@buckaroo.nl>
 *  @copyright Copyright (c) Buckaroo B.V.
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Tests\Unit\Service;

use Buckaroo\PrestaShop\Src\Service\PriceFormatter;
use PHPUnit\Framework\TestCase;

/**
 * The bootstrap Tools stub formats prices as "<ISO> <amount with two decimals>",
 * which makes the chosen currency and the numeric input observable.
 */
final class PriceFormatterTest extends TestCase
{
    protected function setUp(): void
    {
        \Module::$instances = [];
        \Currency::$isoById = [];
    }

    public function testFormatsWithTheContextLocaleAndContextCurrency(): void
    {
        $formatter = new PriceFormatter($this->context('EUR'));

        self::assertSame('EUR 12.50', $formatter->format(12.5));
    }

    public function testExplicitCurrencyOverridesTheContextCurrency(): void
    {
        $formatter = new PriceFormatter($this->context('EUR'));

        self::assertSame('USD 10.00', $formatter->format(10, 'USD'));
        self::assertSame('EUR 10.00', $formatter->format(10, ''));
    }

    public function testNegativeAndZeroAmounts(): void
    {
        $formatter = new PriceFormatter($this->context('EUR'));

        self::assertSame('EUR -1.50', $formatter->format(-1.5));
        self::assertSame('EUR 0.00', $formatter->format(0));
    }

    public function testNumericStringsAndIntegersAreTreatedAsAmounts(): void
    {
        $formatter = new PriceFormatter($this->context('EUR'));

        self::assertSame('EUR 3.00', $formatter->format('3'));
        self::assertSame('EUR 3.00', $formatter->format(3));
        self::assertSame('EUR 1234.56', $formatter->format('1234.56'));
    }

    public function testFormatsInTheCurrencyOfAnOrder(): void
    {
        \Currency::$isoById[7] = 'GBP';
        $formatter = new PriceFormatter($this->context('EUR'));

        self::assertSame('GBP 2.00', $formatter->formatForCurrencyId(2, 7));
        // Unknown currency id: falls back to the context currency instead of failing.
        self::assertSame('EUR 2.00', $formatter->formatForCurrencyId(2, 0));
    }

    public function testResolvesTheContextFromAProvider(): void
    {
        $context = $this->context('EUR');
        $provider = new class($context) {
            private $context;

            public function __construct($context)
            {
                $this->context = $context;
            }

            public function getContext()
            {
                return $this->context;
            }
        };

        self::assertSame('EUR 1.00', (new PriceFormatter($provider))->format(1));
    }

    public function testFallsBackToANeutralRepresentationWithoutAnyContext(): void
    {
        $formatter = new PriceFormatter(null);

        self::assertSame('1.25 EUR', $formatter->format(1.25, 'EUR'));
        self::assertSame('1.25', $formatter->format(1.25));
    }

    private function context(string $isoCode): \Context
    {
        $context = new \Context();
        $context->currency = new \Currency();
        $context->currency->iso_code = $isoCode;

        return $context;
    }
}
