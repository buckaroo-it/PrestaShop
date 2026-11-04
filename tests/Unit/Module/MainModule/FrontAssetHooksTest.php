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

use BuckarooTest\Stubs;

require_once __DIR__ . '/MainModuleTestCase.php';

/**
 * Front office asset hooks: checkout JS definitions, gift card already-paid
 * block and the Click to Pay Drop-in UI loading.
 */
class FrontAssetHooksTest extends MainModuleTestCase
{
    public function testCheckoutHeaderRegistersTheBuckarooScripts(): void
    {
        $controller = $this->frontController('order');
        $this->contextCart(7, 100.0);
        $this->giftcardRows(
            [['amount' => '30', 'refunded_amount' => '0', 'card_code' => 'fashioncheque', 'transaction_key' => 'T1']],
            ['fashioncheque' => 'Fashion Cheque']
        );
        \Configuration::updateValue('BUCKAROO_PAYMENT_FEE_FRONTEND_LABEL', 'Payment Fee');
        $module = $this->createModule($this->checkoutServices());

        $module->hookDisplayHeader();

        $defs = $this->jsDefs();
        self::assertSame('https://shop.test/module/buckaroo3/ajax', $defs['buckarooAjaxUrl']);
        self::assertSame(['ideal' => '1.00'], $defs['buckarooFees']);
        self::assertSame('Payment Fee', $defs['paymentFeeLabel']);
        self::assertSame('Please select your bank', $defs['buckarooMessages']['validation']['bank']);
        self::assertSame(
            [
                'alreadyPaid' => 30.0,
                'remainingAmount' => 70.0,
                'giftcardItems' => [[
                    'label' => 'Paid with Fashion Cheque',
                    'amount' => 30.0,
                    'card_code' => 'fashioncheque',
                    'transaction_key' => 'T1',
                ]],
                'currencySign' => '€',
                'cartTotal' => 100.0,
            ],
            $defs['buckarooAlreadyPaidData']
        );
        self::assertSame(['paidWith' => 'Paid with Giftcard', 'remaining' => 'Remaining Amount'], $defs['buckarooAlreadyPaidLabels']);
        self::assertArrayNotHasKey('buckarooClickToPayConfig', $defs);

        $js = $this->registeredJs($controller);
        self::assertSame(['modules/buckaroo3/views/js/buckaroo.js', ['position' => 'bottom', 'priority' => 200]], $js['module-buckaroo3']);
        self::assertSame('modules/buckaroo3/views/js/already-paid.js', $js['module-buckaroo3-already-paid'][0]);
        self::assertSame(['/modules/buckaroo3/views/css/buckaroo3.css'], $this->addedCss($controller));
    }

    public function testHeaderOnOtherPagesOnlyAddsTheStylesheet(): void
    {
        $controller = $this->frontController('index');
        $module = $this->createModule();

        $module->hookDisplayHeader();

        self::assertSame([], Stubs::callsTo('Media::addJsDef'));
        self::assertSame(['/modules/buckaroo3/views/css/buckaroo3.css'], $this->addedCss($controller));
        self::assertSame([], $this->registeredJs($controller));
    }

    public function testHeaderWithoutAControllerDoesNothing(): void
    {
        $this->context()->controller = null;
        $this->request(['controller' => 'order', 'buckaroo_error' => '1', 'buckaroo_error_msg' => 'x']);

        self::assertNull($this->createModule()->hookDisplayHeader());
        self::assertSame([], Stubs::callsTo('Media::addJsDef'));
    }

    public function testHeaderShowsThePaymentErrorMessage(): void
    {
        $controller = $this->frontController('index');
        $this->request(['controller' => 'order', 'buckaroo_error' => '1', 'buckaroo_error_msg' => 'Payment%20cancelled']);

        $this->createModule()->hookDisplayHeader();

        self::assertSame(['buckaroo_error_msg' => 'Payment cancelled'], $this->jsDefs());
        self::assertSame(
            ['modules/buckaroo3/views/js/buckaroo-error.js', ['position' => 'bottom', 'priority' => 150]],
            $this->registeredJs($controller)['module-buckaroo-error']
        );
    }

    public function testAlreadyPaidDataFallsBackToTheCookieWhenTheTableFails(): void
    {
        $this->frontController('order');
        $this->contextCart(7, 80.0);
        Stubs::db('/bk_group_transaction/', function () {
            throw new \RuntimeException('table missing');
        });
        $this->context()->cookie->buckaroo_giftcard_applied = '25';
        $this->context()->cookie->buckaroo_giftcard_remainder = '55';
        $this->context()->currency = null;
        $module = $this->createModule($this->checkoutServices());

        $module->hookDisplayHeader();

        $data = $this->jsDefs()['buckarooAlreadyPaidData'];
        self::assertSame(25.0, $data['alreadyPaid']);
        self::assertSame(55.0, $data['remainingAmount']);
        self::assertSame([], $data['giftcardItems']);
        self::assertSame('', $data['currencySign']);
        self::assertSame(
            ['getGiftcardAlreadyPaid: table missing', 'getGiftcardRemainingAmount: table missing', 'getGiftcardDisplayItems: table missing'],
            array_column($module->logger->callsTo('logError'), 0)
        );
    }

    public function testCoreDefinitionsSurviveAGiftcardDataError(): void
    {
        $this->frontController('order');
        $cart = $this->contextCart();
        $cart->stub('getOrderTotal', function () {
            throw new \RuntimeException('no currency');
        });
        $module = $this->createModule($this->checkoutServices());

        $module->hookDisplayHeader();

        self::assertArrayHasKey('buckarooAjaxUrl', $this->jsDefs());
        self::assertSame(0, $this->jsDefs()['buckarooAlreadyPaidData']['cartTotal']);
        self::assertSame(['Buckaroo: giftcard data error in ensureBuckarooJsLoaded() - no currency'], $this->loggedMessages());
    }

    public function testScriptErrorsAreLogged(): void
    {
        $this->frontController('order');
        $module = $this->createModule($this->checkoutServices([
            'buckaroo.config.api.fee.service' => new ServiceDouble(['getBuckarooFees' => function () {
                throw new \RuntimeException('fee table missing');
            }]),
        ]));

        $module->hookDisplayHeader();

        self::assertSame([], Stubs::callsTo('Media::addJsDef'));
        self::assertSame(['Buckaroo: ERROR in ensureBuckarooJsLoaded() - fee table missing'], $this->loggedMessages());
    }

    public function testPaymentTopWithoutControllerLogsTheMissingController(): void
    {
        $module = $this->createModule($this->checkoutServices());

        self::assertSame('', $module->hookDisplayPaymentTop());

        self::assertArrayHasKey('buckarooAjaxUrl', $this->jsDefs());
        self::assertSame(['Buckaroo: ERROR - No controller available to register script'], $this->loggedMessages());
    }

    public function testClickToPayIsLoadedOncePerRequest(): void
    {
        $controller = $this->frontController('order');
        $this->contextCart();
        $clickToPay = new ServiceDouble([
            'isConfigured' => true,
            'getCheckoutConfig' => ['merchantIdentifier' => 'M1', 'currency' => 'EUR'],
            'getSdkScriptUrl' => 'https://static.buckaroo.test/sdk.js',
        ]);
        $module = $this->createModule($this->checkoutServices([
            'buckaroo.config.api.config.service' => $this->configService(['clicktopay' => ['mode' => 'live']]),
            'buckaroo.config.api.clicktopay.service' => $clickToPay,
        ]));

        $module->hookDisplayHeader();
        $module->hookDisplayPaymentTop();

        self::assertCount(1, $clickToPay->callsTo('getCheckoutConfig'));
        self::assertSame(
            [
                'merchantIdentifier' => 'M1',
                'currency' => 'EUR',
                'tokenUrl' => 'https://shop.test/module/buckaroo3/clicktopaytoken',
                'token' => 'token',
                'messages' => [
                    'initError' => 'An error occurred, please try another payment method or try again later.',
                    'acceptTerms' => 'Please accept the terms of service to complete your payment.',
                ],
            ],
            $this->jsDefs()['buckarooClickToPayConfig']
        );
        $js = $this->registeredJs($controller);
        self::assertSame(
            ['https://static.buckaroo.test/sdk.js', ['server' => 'remote', 'position' => 'bottom', 'priority' => 190]],
            $js['module-buckaroo3-sdk']
        );
        self::assertSame('modules/buckaroo3/views/js/buckaroo-clicktopay.js', $js['module-buckaroo3-clicktopay'][0]);
    }

    /**
     * @dataProvider clickToPaySkippedProvider
     */
    public function testClickToPayIsSkipped(bool $withCart, string $mode, bool $configured): void
    {
        $controller = $this->frontController('order');
        if ($withCart) {
            $this->contextCart();
        }
        $clickToPay = new ServiceDouble(['isConfigured' => $configured]);
        $module = $this->createModule($this->checkoutServices([
            'buckaroo.config.api.config.service' => $this->configService(['clicktopay' => ['mode' => $mode]]),
            'buckaroo.config.api.clicktopay.service' => $clickToPay,
        ]));

        $module->hookDisplayHeader();

        self::assertArrayNotHasKey('buckarooClickToPayConfig', $this->jsDefs());
        self::assertArrayNotHasKey('module-buckaroo3-clicktopay', $this->registeredJs($controller));
    }

    public function clickToPaySkippedProvider(): array
    {
        return [
            'no cart' => [false, 'live', true],
            'disabled' => [true, 'off', true],
            'not configured' => [true, 'test', false],
        ];
    }

    public function testClickToPayErrorsAreLogged(): void
    {
        $controller = $this->frontController('order');
        $this->contextCart();
        $module = $this->createModule($this->checkoutServices([
            'buckaroo.config.api.config.service' => $this->configService(['clicktopay' => ['mode' => 'live']]),
            'buckaroo.config.api.clicktopay.service' => new ServiceDouble([
                'isConfigured' => true,
                'getCheckoutConfig' => function () {
                    throw new \RuntimeException('no address');
                },
            ]),
        ]));

        $module->hookDisplayHeader();

        self::assertSame(['Buckaroo: ERROR in ensureClickToPayJsLoaded() - no address'], $this->loggedMessages());
        self::assertArrayHasKey('module-buckaroo3', $this->registeredJs($controller));
    }

    // ----- gift card already-paid block ------------------------------------------

    public function testShoppingCartFooterShowsTheGiftcardsAlreadyPaid(): void
    {
        $this->contextCart(7, 100.0);
        $this->giftcardRows([
            ['amount' => '30', 'refunded_amount' => '0', 'card_code' => 'vvv', 'transaction_key' => 'T1'],
            ['amount' => '10', 'refunded_amount' => '10', 'card_code' => '', 'transaction_key' => 'T2'],
            ['amount' => '15', 'refunded_amount' => '5', 'card_code' => '', 'transaction_key' => 'T3'],
        ]);

        $html = $this->createModule()->hookDisplayShoppingCartFooter([]);

        self::assertSame('display:views/templates/hook/already-paid.tpl', $html);
        $vars = $this->context()->smarty->getTemplateVars();
        self::assertSame(40.0, $vars['buckarooAlreadyPaid']);
        self::assertSame('EUR 40.00', $vars['buckarooAlreadyPaidFormatted']);
        self::assertSame(60.0, $vars['buckarooRemainingAmount']);
        self::assertSame('EUR 60.00', $vars['buckarooRemainingAmountFormatted']);
        self::assertSame(['Paid with Vvv Giftcard', 'Giftcard'], array_column($vars['buckarooGiftcardItems'], 'label'));
        self::assertSame(['EUR 30.00', 'EUR 10.00'], array_column($vars['buckarooGiftcardItems'], 'amountFormatted'));
        self::assertSame($this->context()->currency, $vars['currency']);
    }

    public function testShoppingCartFooterUsesTheCookieRightAfterApplyingAGiftcard(): void
    {
        $this->contextCart(7, 100.0);
        $this->context()->cookie->buckaroo_giftcard_applied = '20';
        $this->context()->cookie->buckaroo_giftcard_remainder = '80';

        $html = $this->createModule()->hookDisplayShoppingCartFooter([]);

        self::assertSame('display:views/templates/hook/already-paid.tpl', $html);
        self::assertSame(20.0, $this->context()->smarty->getTemplateVars('buckarooAlreadyPaid'));
        self::assertSame(80.0, $this->context()->smarty->getTemplateVars('buckarooRemainingAmount'));
        self::assertSame([], $this->context()->smarty->getTemplateVars('buckarooGiftcardItems'));
    }

    /**
     * @dataProvider noAlreadyPaidBlockProvider
     */
    public function testShoppingCartFooterIsEmpty(?int $cartId): void
    {
        if ($cartId !== null) {
            $cart = $this->contextCart();
            $cart->id = $cartId;
        }

        self::assertSame('', $this->createModule()->hookDisplayShoppingCartFooter([]));
        self::assertSame([], $this->context()->smarty->getTemplateVars());
    }

    public function noAlreadyPaidBlockProvider(): array
    {
        return [
            'no cart' => [null],
            'cart not saved yet' => [0],
            'nothing paid' => [7],
        ];
    }

    private function checkoutServices(array $overrides = []): array
    {
        return $overrides + [
            'buckaroo.config.api.fee.service' => new ServiceDouble(['getBuckarooFees' => ['ideal' => '1.00']]),
            'buckaroo.config.api.config.service' => $this->configService(),
            'buckaroo.config.api.clicktopay.service' => new ServiceDouble(['isConfigured' => false]),
        ];
    }
}
