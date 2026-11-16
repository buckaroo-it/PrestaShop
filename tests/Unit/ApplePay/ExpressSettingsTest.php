<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Tests\Unit\ApplePay;

use Buckaroo\PrestaShop\Src\ApplePay\ExpressSettings;
use PHPUnit\Framework\TestCase;

final class ExpressSettingsTest extends TestCase
{
    public function testConfigurationWithoutExpressSettingsUsesTheDefaults(): void
    {
        $settings = new ExpressSettings(['mode' => 'live']);

        self::assertSame(['product', 'cart', 'checkout'], $settings->getPlacements());
        self::assertTrue($settings->isListedInCheckout());
        self::assertFalse($settings->isConfigured());
        self::assertSame('black', $settings->getButtonStyle());
        self::assertSame('plain', $settings->getButtonType());
    }

    public function testMissingConfigurationIsNotConfigured(): void
    {
        $settings = new ExpressSettings(null);

        self::assertFalse($settings->isConfigured());
        self::assertSame('', $settings->getMerchantGuid());
    }

    public function testOnlyTheSelectedPlacementsShowTheButton(): void
    {
        $settings = new ExpressSettings(['express_show_on' => ['cart', 'unknown', 'product']]);

        self::assertSame(['product', 'cart'], $settings->getPlacements());
        self::assertTrue($settings->showsOn('product'));
        self::assertTrue($settings->showsOn('cart'));
        self::assertFalse($settings->showsOn('checkout'));
    }

    /**
     * @dataProvider clearedPlacements
     *
     * @param mixed $stored
     */
    public function testClearedPlacementsHideTheButtonEverywhere($stored): void
    {
        $settings = new ExpressSettings(['express_show_on' => $stored]);

        self::assertSame([], $settings->getPlacements());
        foreach (ExpressSettings::PLACEMENTS as $placement) {
            self::assertFalse($settings->showsOn($placement));
        }
    }

    public static function clearedPlacements(): array
    {
        return [
            'empty list' => [[]],
            'empty string' => [''],
            'null' => [null],
        ];
    }

    /**
     * @dataProvider listedValues
     *
     * @param mixed $stored
     */
    public function testListAsCheckoutPaymentMethod($stored, bool $expected): void
    {
        self::assertSame($expected, (new ExpressSettings(['checkout_method' => $stored]))->isListedInCheckout());
    }

    public static function listedValues(): array
    {
        return [
            'string 1' => ['1', true],
            'string 0' => ['0', false],
            'bool true' => [true, true],
            'bool false' => [false, false],
            'legacy TRUE' => ['TRUE', true],
            'legacy FALSE' => ['FALSE', false],
        ];
    }

    public function testConfiguredWithGuid(): void
    {
        $settings = new ExpressSettings([
            'merchant_guid' => ' 1234-ABCD ',
            'button_style' => 'white-outline',
            'button_type' => 'check-out',
        ]);

        self::assertTrue($settings->isConfigured());
        self::assertSame('1234-ABCD', $settings->getMerchantGuid());
        self::assertSame('white-outline', $settings->getButtonStyle());
        self::assertSame('check-out', $settings->getButtonType());
    }

    public function testUnknownButtonValuesFallBackToTheDefaults(): void
    {
        $settings = new ExpressSettings(['button_style' => 'red', 'button_type' => 'donate']);

        self::assertSame('black', $settings->getButtonStyle());
        self::assertSame('plain', $settings->getButtonType());
    }

    public function testSanitizeKeepsOnlyKnownValues(): void
    {
        $sanitized = ExpressSettings::sanitize([
            'mode' => 'live',
            'express_show_on' => ['checkout', '<script>', 'product'],
            'checkout_method' => false,
            'merchant_guid' => ' abc-123 ',
            'button_style' => 'pink',
            'button_type' => 'book',
        ]);

        self::assertSame([
            'mode' => 'live',
            'express_show_on' => ['product', 'checkout'],
            'checkout_method' => '0',
            'merchant_guid' => 'abc-123',
            'button_style' => 'black',
            'button_type' => 'book',
        ], $sanitized);
    }

    public function testSanitizeRejectsAGuidWithUnexpectedCharacters(): void
    {
        $sanitized = ExpressSettings::sanitize(['merchant_guid' => '"><script>alert(1)</script>']);

        self::assertSame('', $sanitized['merchant_guid']);
    }

    public function testSanitizeLeavesAbsentKeysAbsent(): void
    {
        self::assertSame(['mode' => 'off'], ExpressSettings::sanitize(['mode' => 'off']));
    }
}
