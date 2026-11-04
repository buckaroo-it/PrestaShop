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

namespace BuckarooTest\Unit\Module\MainModule;

use Buckaroo\PrestaShop\Src\Service\BuckarooPaymentService;
use BuckarooTest\Stubs;

require_once __DIR__ . '/MainModuleTestCase.php';

/**
 * Apple Pay express button placement on the product, cart and checkout pages.
 */
class ApplePayExpressHooksTest extends MainModuleTestCase
{
    /** @var BuckarooPaymentService|\PHPUnit\Framework\MockObject\MockObject */
    private $paymentService;

    /** @var array<string, mixed> */
    private $applePayConfig;

    protected function setUp(): void
    {
        parent::setUp();
        \Configuration::updateValue('BUCKAROO_MERCHANT_KEY', 'website');
        \Configuration::updateValue('BUCKAROO_SECRET_KEY', 'secret');
        \Configuration::updateValue('PS_GUEST_CHECKOUT_ENABLED', 1);
        \Configuration::updateValue('PS_SHOP_NAME', 'Test shop');
        \Configuration::updateValue('PS_COUNTRY_DEFAULT', 13);
        \Country::$isoById[13] = 'NL';
        $this->applePayConfig = [
            'mode' => 'live',
            'merchant_guid' => 'guid-123',
            'button_style' => 'white-outline',
            'button_type' => 'buy',
        ];
        $this->paymentService = $this->createMock(BuckarooPaymentService::class);
        $this->paymentService->method('isMethodAvailableForCart')->willReturn(true);
        $cart = $this->contextCart(7, 50.0);
        $cart->products = [['id_product' => 1]];
    }

    private function module(array $overrides = []): \Buckaroo3
    {
        return $this->createModule($overrides + [
            'buckaroo.config.api.config.service' => $this->configService(['applepay' => $this->applePayConfig]),
            'buckaroo.config.api.payment.service' => $this->paymentService,
            'buckaroo.config.api.fee.service' => new ServiceDouble(['getBuckarooFees' => []]),
            'buckaroo.config.api.clicktopay.service' => new ServiceDouble([
                'isConfigured' => false,
                'getSdkScriptUrl' => 'https://static.buckaroo.test/sdk.js',
            ]),
        ]);
    }

    public function testProductPageButtonForTheSelectedProduct(): void
    {
        $html = $this->module()->hookDisplayProductAdditionalInfo([
            'product' => ['id_product' => '42', 'add_to_cart_url' => 'https://shop.test/cart?add=1'],
        ]);

        self::assertSame('display:views/templates/hook/applepay_express.tpl', $html);
        self::assertSame('product', $this->context()->smarty->getTemplateVars('buckarooApplePayPlacement'));
        self::assertSame(42, $this->context()->smarty->getTemplateVars('buckarooApplePayProductId'));
    }

    /**
     * @dataProvider productWithoutButtonProvider
     */
    public function testProductPageWithoutButton(array $params): void
    {
        self::assertSame('', $this->module()->hookDisplayProductAdditionalInfo($params));
        self::assertSame([], $this->context()->smarty->getTemplateVars());
    }

    public function productWithoutButtonProvider(): array
    {
        return [
            'no product' => [[]],
            'not orderable' => [['product' => ['id_product' => 42, 'add_to_cart_url' => '']]],
        ];
    }

    public function testCartPageButton(): void
    {
        $html = $this->module()->hookDisplayExpressCheckout([]);

        self::assertSame('display:views/templates/hook/applepay_express.tpl', $html);
        self::assertSame('cart', $this->context()->smarty->getTemplateVars('buckarooApplePayPlacement'));
        self::assertSame(0, $this->context()->smarty->getTemplateVars('buckarooApplePayProductId'));
    }

    /**
     * @dataProvider hiddenButtonProvider
     */
    public function testCartPageButtonIsHidden(callable $arrange): void
    {
        $services = $arrange($this);
        $module = $this->module(is_array($services) ? $services : []);
        if ($services === 'inactive') {
            $module->active = false;
        }

        self::assertSame('', $module->hookDisplayExpressCheckout([]));
        self::assertNull($this->context()->smarty->getTemplateVars('buckarooApplePayPlacement'));
    }

    public function hiddenButtonProvider(): array
    {
        return [
            'module disabled' => [function () {
                return 'inactive';
            }],
            'no website key' => [function () {
                \Configuration::updateValue('BUCKAROO_MERCHANT_KEY', '');
            }],
            'no secret key' => [function () {
                \Configuration::updateValue('BUCKAROO_SECRET_KEY', '');
            }],
            'apple pay off' => [function (self $test) {
                $test->applePayConfig['mode'] = 'off';
            }],
            'no merchant guid' => [function (self $test) {
                $test->applePayConfig['merchant_guid'] = '';
            }],
            'not shown on the cart page' => [function (self $test) {
                $test->applePayConfig['express_show_on'] = ['product', 'checkout'];
            }],
            'guest checkout disabled' => [function () {
                \Configuration::updateValue('PS_GUEST_CHECKOUT_ENABLED', 0);
            }],
            'no cart' => [function () {
                \Context::getContext()->cart = null;
            }],
            'cart not saved' => [function () {
                \Context::getContext()->cart->id = null;
            }],
            'empty cart' => [function () {
                \Context::getContext()->cart->products = [];
            }],
            'gift card already applied' => [function () {
                \Context::getContext()->cookie->buckaroo_giftcard_applied = '5';
            }],
            'below the minimum purchase' => [function () {
                \Configuration::updateValue('PS_PURCHASE_MINIMUM', 75);
            }],
            'payment service unavailable' => [function () {
                return ['buckaroo.config.api.payment.service' => new ServiceDouble()];
            }],
            'apple pay not available for the cart' => [function (self $test) {
                $service = $test->createMock(BuckarooPaymentService::class);
                $service->method('isMethodAvailableForCart')->willReturn(false);

                return ['buckaroo.config.api.payment.service' => $service];
            }],
        ];
    }

    public function testGuestCheckoutIsNotNeededForALoggedInCustomer(): void
    {
        \Configuration::updateValue('PS_GUEST_CHECKOUT_ENABLED', 0);
        \Configuration::updateValue('PS_PURCHASE_MINIMUM', 20);
        $customer = new \Customer();
        $customer->id = 5;
        $customer->stub('isLogged', true);
        $this->context()->customer = $customer;

        self::assertSame('display:views/templates/hook/applepay_express.tpl', $this->module()->hookDisplayExpressCheckout([]));
    }

    public function testAvailabilityErrorsHideTheButton(): void
    {
        $module = $this->module([
            'buckaroo.config.api.config.service' => new ServiceDouble(['getConfigArrayForMethod' => function () {
                throw new \RuntimeException('config table missing');
            }]),
        ]);

        self::assertSame('', $module->hookDisplayExpressCheckout([]));
        self::assertSame(['Buckaroo: Apple Pay express availability - config table missing'], $this->loggedMessages());
    }

    public function testPaymentTopShowsTheCheckoutButton(): void
    {
        $this->frontController('order');

        $html = $this->module()->hookDisplayPaymentTop();

        self::assertSame('display:views/templates/hook/applepay_express.tpl', $html);
        self::assertSame('checkout', $this->context()->smarty->getTemplateVars('buckarooApplePayPlacement'));
    }

    /**
     * @dataProvider headerPageProvider
     */
    public function testHeaderRegistersTheExpressAssetsOnButtonPages(string $page, bool $expected): void
    {
        $controller = $this->frontController($page);
        $this->context()->language->locale = 'nl-NL';

        $this->module()->hookDisplayHeader();

        $js = $this->registeredJs($controller);
        if (!$expected) {
            self::assertArrayNotHasKey('buckarooApplePayExpress', $this->jsDefs());
            self::assertArrayNotHasKey('module-buckaroo3-applepay-express', $js);

            return;
        }
        self::assertSame(
            [
                'url' => 'https://shop.test/module/buckaroo3/applepayexpress',
                'token' => 'token',
                'merchantGuid' => 'guid-123',
                'buttonStyle' => 'white-outline',
                'buttonType' => 'buy',
                'storeName' => 'Test shop',
                'countryCode' => 'NL',
                'currencyCode' => 'EUR',
                'cultureCode' => 'nl-NL',
                'messages' => [
                    'error' => 'Apple Pay is not available at the moment. Please try again or choose another payment method.',
                ],
            ],
            $this->jsDefs()['buckarooApplePayExpress']
        );
        self::assertSame(
            ['https://static.buckaroo.test/sdk.js', ['server' => 'remote', 'position' => 'bottom', 'priority' => 190]],
            $js['module-buckaroo3-sdk']
        );
        self::assertSame(
            ['modules/buckaroo3/views/js/buckaroo-applepay-express.js', ['position' => 'bottom', 'priority' => 230]],
            $js['module-buckaroo3-applepay-express']
        );
    }

    public function headerPageProvider(): array
    {
        return [
            'product' => ['product', true],
            'cart' => ['cart', true],
            'checkout' => ['order', true],
            'one page checkout' => ['order-opc', true],
            'home' => ['index', false],
        ];
    }

    public function testHeaderUsesTheLanguageCodeWithoutLocale(): void
    {
        $this->frontController('product');
        $this->context()->language->language_code = 'de-de';

        $this->module()->hookDisplayHeader();

        self::assertSame('de-de', $this->jsDefs()['buckarooApplePayExpress']['cultureCode']);
    }

    public function testHeaderSkipsTheAssetsWhenTheButtonIsHidden(): void
    {
        $controller = $this->frontController('cart');
        $this->context()->cart = null;

        $this->module()->hookDisplayHeader();

        self::assertArrayNotHasKey('module-buckaroo3-applepay-express', $this->registeredJs($controller));
        self::assertSame(['/modules/buckaroo3/views/css/buckaroo3.css'], $this->addedCss($controller));
    }

    public function testMinimumPurchaseIsComparedWithoutTax(): void
    {
        \Configuration::updateValue('PS_PURCHASE_MINIMUM', 40);
        $this->module()->hookDisplayExpressCheckout([]);

        $totalCalls = Stubs::callsTo('Cart->getOrderTotal');
        self::assertSame([false, \Cart::ONLY_PRODUCTS], $totalCalls[count($totalCalls) - 1]);
    }
}
