<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Tests\Unit\PayPal;

use Buckaroo\PrestaShop\Src\Express\Wallet;
use Buckaroo\PrestaShop\Src\PayPal\ExpressSettings;
use PHPUnit\Framework\TestCase;

final class ExpressSettingsTest extends TestCase
{
    public function testExistingPayPalConfigurationShowsNoExpressButton(): void
    {
        $settings = new ExpressSettings(['mode' => 'live', 'seller_protection' => '0']);

        self::assertSame([], $settings->getPlacements());
        self::assertFalse($settings->showsOn('product'));
        self::assertTrue($settings->isListedInCheckout());
        self::assertSame('gold', $settings->getButtonStyle());
        self::assertSame('paypal', $settings->getButtonType());
        self::assertFalse($settings->isButtonRounded());
        self::assertSame('paypal', $settings->getWallet()->getMethod());
    }

    public function testOnlyTheSelectedPlacementsShowTheButton(): void
    {
        $settings = new ExpressSettings(['express_show_on' => ['cart', 'checkout', 'nowhere']]);

        self::assertSame(['cart', 'checkout'], $settings->getPlacements());
        self::assertTrue($settings->showsOn('cart'));
        self::assertFalse($settings->showsOn('product'));
        self::assertSame([], (new ExpressSettings(['express_show_on' => []]))->getPlacements());
    }

    public function testPayPalStaysListedInCheckout(): void
    {
        self::assertTrue((new ExpressSettings(['checkout_method' => '0']))->isListedInCheckout());
    }

    public function testSellerProtectionIsOnUnlessSwitchedOff(): void
    {
        self::assertTrue((new ExpressSettings([]))->isSellerProtectionEnabled());
        self::assertTrue((new ExpressSettings(['seller_protection' => '1']))->isSellerProtectionEnabled());
        self::assertFalse((new ExpressSettings(['seller_protection' => '0']))->isSellerProtectionEnabled());
    }

    public function testLiveModeNeedsTheMerchantId(): void
    {
        self::assertFalse((new ExpressSettings(['mode' => 'live', 'express_sandbox_merchant_id' => 'SANDBOX1']))->isConfigured());

        $settings = new ExpressSettings(['mode' => 'live', 'express_merchant_id' => ' LIVEMERCHANT1 ', 'express_sandbox_merchant_id' => 'SANDBOX1']);
        self::assertTrue($settings->isConfigured());
        self::assertSame('LIVEMERCHANT1', $settings->getMerchantId());
        self::assertSame('https://checkout.buckaroo.nl', $settings->getCheckoutUrl());
        self::assertSame('NL_BUCKAROO_PPCP', $settings->getPartnerAttributionId());
    }

    public function testTestModeUsesTheOptionalSandboxMerchantId(): void
    {
        $withoutSandbox = new ExpressSettings(['mode' => 'test', 'express_merchant_id' => 'LIVEMERCHANT1']);
        self::assertTrue($withoutSandbox->isConfigured());
        self::assertSame('', $withoutSandbox->getMerchantId());
        self::assertSame('https://testcheckout.buckaroo.nl', $withoutSandbox->getCheckoutUrl());

        $withSandbox = new ExpressSettings(['mode' => 'test', 'express_sandbox_merchant_id' => 'SANDBOX1']);
        self::assertSame('SANDBOX1', $withSandbox->getSdkMerchantId());
        self::assertNotSame((new ExpressSettings(['mode' => 'live']))->getClientId(), $withSandbox->getClientId());
    }

    public function testCollectingMerchantIdUsesTheCollectingClient(): void
    {
        $regular = new ExpressSettings(['mode' => 'live', 'express_merchant_id' => 'LIVEMERCHANT1']);
        $collecting = new ExpressSettings(['mode' => 'live', 'express_merchant_id' => 'c_LIVEMERCHANT1']);

        self::assertSame('c_LIVEMERCHANT1', $collecting->getMerchantId());
        self::assertSame('LIVEMERCHANT1', $collecting->getSdkMerchantId());
        self::assertNotSame($regular->getClientId(), $collecting->getClientId());
        self::assertSame('Buckaroo_PSP_MP', $collecting->getPartnerAttributionId());
    }

    public function testButtonSettings(): void
    {
        $settings = new ExpressSettings(['button_style' => 'black', 'button_type' => 'buynow', 'button_rounded' => '1']);

        self::assertSame('black', $settings->getButtonStyle());
        self::assertSame('buynow', $settings->getButtonType());
        self::assertTrue($settings->isButtonRounded());

        $googleValues = new ExpressSettings(['button_style' => 'white-outline', 'button_type' => 'subscribe']);
        self::assertSame('gold', $googleValues->getButtonStyle());
        self::assertSame('paypal', $googleValues->getButtonType());
    }

    public function testSanitizeKeepsOnlyKnownValues(): void
    {
        $sanitized = ExpressSettings::sanitize([
            'mode' => 'live',
            'express_show_on' => ['product', 'elsewhere'],
            'button_style' => 'pink',
            'button_type' => 'donate',
            'button_rounded' => 'yes',
            'seller_protection' => 'off',
            'express_merchant_id' => ' c_ABC123 ',
            'express_sandbox_merchant_id' => 'not valid!',
        ]);

        self::assertSame([
            'mode' => 'live',
            'express_show_on' => ['product'],
            'button_style' => 'gold',
            'button_type' => 'paypal',
            'button_rounded' => '1',
            'seller_protection' => '0',
            'express_merchant_id' => 'c_ABC123',
            'express_sandbox_merchant_id' => '',
        ], $sanitized);
    }

    public function testSanitizeLeavesAbsentKeysAbsent(): void
    {
        self::assertSame(['mode' => 'test'], ExpressSettings::sanitize(['mode' => 'test']));
    }

    public function testWallet(): void
    {
        self::assertSame('PayPal', Wallet::payPal()->getLabel());
        self::assertSame('PayPal', Wallet::fromMethod('paypal')->getLabel());
        self::assertTrue(Wallet::isExpressMethod('paypal'));
    }
}
