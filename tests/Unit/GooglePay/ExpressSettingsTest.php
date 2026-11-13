<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Tests\Unit\GooglePay;

use Buckaroo\PrestaShop\Src\Express\Wallet;
use Buckaroo\PrestaShop\Src\GooglePay\ExpressSettings;
use PHPUnit\Framework\TestCase;

final class ExpressSettingsTest extends TestCase
{
    public function testConfigurationWithoutExpressSettingsUsesTheDefaults(): void
    {
        $settings = new ExpressSettings(['mode' => 'test']);

        self::assertSame(['product', 'cart', 'checkout'], $settings->getPlacements());
        self::assertTrue($settings->isListedInCheckout());
        self::assertFalse($settings->isConfigured());
        self::assertSame('black', $settings->getButtonStyle());
        self::assertSame('pay', $settings->getButtonType());
        self::assertSame('googlepay', $settings->getWallet()->getMethod());
    }

    public function testTestModeOnlyNeedsTheGatewayMerchantId(): void
    {
        $settings = new ExpressSettings(['mode' => 'test', 'gatewayMerchantId' => 'ABC123']);

        self::assertTrue($settings->isConfigured());
        self::assertFalse($settings->isLiveMode());
    }

    public function testLiveModeAlsoNeedsTheGoogleMerchantId(): void
    {
        self::assertFalse((new ExpressSettings(['mode' => 'live', 'gatewayMerchantId' => 'ABC123']))->isConfigured());
        self::assertTrue((new ExpressSettings([
            'mode' => 'live',
            'gatewayMerchantId' => 'ABC123',
            'merchantId' => 'BCR2DN4T1234567',
        ]))->isConfigured());
    }

    public function testWithoutGatewayMerchantIdItIsNotConfigured(): void
    {
        self::assertFalse((new ExpressSettings(['mode' => 'live', 'merchantId' => 'BCR2DN4T1234567']))->isConfigured());
        self::assertFalse((new ExpressSettings(null))->isConfigured());
    }

    public function testCredentialsAreTrimmed(): void
    {
        $settings = new ExpressSettings([
            'gatewayMerchantId' => '  ABC123 ',
            'merchantId' => ' BCR2DN4T1234567 ',
            'merchantName' => ' My Shop ',
        ]);

        self::assertSame('ABC123', $settings->getGatewayMerchantId());
        self::assertSame('BCR2DN4T1234567', $settings->getGoogleMerchantId());
        self::assertSame('My Shop', $settings->getMerchantName());
    }

    public function testOnlyTheSelectedPlacementsShowTheButton(): void
    {
        $settings = new ExpressSettings(['express_show_on' => ['cart']]);

        self::assertFalse($settings->showsOn('product'));
        self::assertTrue($settings->showsOn('cart'));
        self::assertFalse($settings->showsOn('checkout'));
    }

    public function testClearedPlacementsHideTheButtonEverywhere(): void
    {
        self::assertSame([], (new ExpressSettings(['express_show_on' => []]))->getPlacements());
    }

    public function testNotListedInCheckout(): void
    {
        self::assertFalse((new ExpressSettings(['checkout_method' => '0']))->isListedInCheckout());
    }

    public function testAppleOnlyButtonValuesFallBackToTheGoogleDefaults(): void
    {
        $settings = new ExpressSettings(['button_style' => 'white-outline', 'button_type' => 'check-out']);

        self::assertSame('black', $settings->getButtonStyle());
        self::assertSame('pay', $settings->getButtonType());
    }

    public function testEveryGoogleButtonTypeIsKept(): void
    {
        foreach (['plain', 'pay', 'buy', 'checkout', 'order', 'book', 'subscribe'] as $type) {
            self::assertSame($type, (new ExpressSettings(['button_type' => $type]))->getButtonType());
        }
        self::assertSame('white', (new ExpressSettings(['button_style' => 'white']))->getButtonStyle());
    }

    public function testSanitizeKeepsOnlyKnownValues(): void
    {
        $data = ExpressSettings::sanitize([
            'mode' => 'test',
            'express_show_on' => ['checkout', 'footer', 'product'],
            'checkout_method' => 'false',
            'gatewayMerchantId' => ' ABC-123_x.y ',
            'merchantId' => 'BCR2DN4T1234567',
            'merchantName' => ' <b>My Shop</b> ',
            'button_style' => 'white-outline',
            'button_type' => 'donate',
        ]);

        self::assertSame('test', $data['mode']);
        self::assertSame(['product', 'checkout'], $data['express_show_on']);
        self::assertSame('0', $data['checkout_method']);
        self::assertSame('ABC-123_x.y', $data['gatewayMerchantId']);
        self::assertSame('BCR2DN4T1234567', $data['merchantId']);
        self::assertSame('My Shop', $data['merchantName']);
        self::assertSame('black', $data['button_style']);
        self::assertSame('pay', $data['button_type']);
    }

    public function testSanitizeRejectsCredentialsWithUnexpectedCharacters(): void
    {
        $data = ExpressSettings::sanitize([
            'gatewayMerchantId' => 'abc"><script>',
            'merchantId' => 'BCR2 DN4T',
        ]);

        self::assertSame('', $data['gatewayMerchantId']);
        self::assertSame('', $data['merchantId']);
    }

    public function testSanitizeLeavesAbsentKeysAbsent(): void
    {
        self::assertSame(['mode' => 'live'], ExpressSettings::sanitize(['mode' => 'live']));
    }

    public function testWallet(): void
    {
        self::assertSame('Google Pay', Wallet::googlePay()->getLabel());
        self::assertSame('Apple Pay', Wallet::fromMethod('applepay')->getLabel());
        self::assertTrue(Wallet::isExpressMethod('googlepay'));
        self::assertFalse(Wallet::isExpressMethod('ideal'));
        self::assertSame(['applepay', 'googlepay', 'paypal'], Wallet::methods());

        $this->expectException(\InvalidArgumentException::class);
        Wallet::fromMethod('ideal');
    }
}
