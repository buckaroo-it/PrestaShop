<?php

namespace BuckarooTest\Unit\Controller\Front\ReturnFlow;

use Buckaroo\PrestaShop\Src\Config\Config;
use Buckaroo\PrestaShop\Src\ErrorReporting\ErrorReporter;
use BuckarooTest\Stubs;

require_once __DIR__ . '/StubsSupport.php';

if (!class_exists(AjaxTestModule::class, false)) {
    /** Module double exposing the core service container (PrestaShop 8+/9). */
    class AjaxTestModule extends \Module
    {
        /** @var ServiceContainer|null */
        public $coreContainer;

        public function getCoreServiceContainer()
        {
            return $this->coreContainer;
        }
    }
}

/**
 * Checkout AJAX endpoints (controllers/front/ajax.php): payment fee totals and BNPL phone status.
 */
class AjaxControllerTest extends ReturnFlowTestCase
{
    /** @var RecordingErrorReporter */
    private $reporter;

    /** @var array<int, mixed> carts passed to the presenter */
    private $presented = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->reporter = new RecordingErrorReporter();
        ErrorReporter::setInstance($this->reporter);

        $this->context()->currentLocale = new class {
            public function formatPrice($amount, $isoCode)
            {
                return $isoCode . ' ' . number_format((float) $amount, 2, '.', '');
            }
        };
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function controller(?\Module $module = null): \Buckaroo3AjaxModuleFrontController
    {
        if ($module !== null) {
            \Module::$instances['buckaroo3'] = $module;
        }
        $controller = new \Buckaroo3AjaxModuleFrontController();
        $controller->translator = new class {
            public function trans($id, array $parameters = [], $domain = null)
            {
                return $id;
            }
        };
        $controller->stub('render', function ($template) {
            return '<rendered ' . $template . '>';
        });
        $controller->stub('getTemplateVarConfiguration', ['display_prices_tax_incl' => true]);

        return $controller;
    }

    /** Module whose core container offers a cart presenter returning $presentedCart. */
    private function moduleWithPresenter($presentedCart): AjaxTestModule
    {
        $module = new AjaxTestModule();
        $module->coreContainer = new ServiceContainer([
            'prestashop.core.cart.cart_presenter' => $this->presenter($presentedCart),
        ]);

        return $module;
    }

    private function presenter($presentedCart)
    {
        $test = $this;

        return new class($presentedCart, $test) {
            private $cart;
            private $test;

            public function __construct($cart, $test)
            {
                $this->cart = $cart;
                $this->test = $test;
            }

            public function present($cart)
            {
                $this->test->recordPresented($cart);

                return $this->cart;
            }
        };
    }

    public function recordPresented($cart): void
    {
        $this->presented[] = $cart;
    }

    /** Cart 5 totalling 100.00 incl. / 82.64 excl. tax, invoice address 3. */
    private function givenCart(): \Cart
    {
        $cart = new \Cart();
        $cart->id = 5;
        $cart->id_customer = 7;
        $cart->id_address_delivery = 2;
        $cart->id_address_invoice = 3;
        $cart->stub('getOrderTotal', function ($withTaxes = true) {
            return $withTaxes ? 100.0 : 82.64;
        });
        $this->context()->cart = $cart;

        return $cart;
    }

    private function givenTaxRate(float $rate): void
    {
        Stubs::object('Address', 3);
        $calculator = new class($rate) {
            private $rate;

            public function __construct($rate)
            {
                $this->rate = $rate;
            }

            public function getTotalRate()
            {
                return $this->rate;
            }
        };
        Stubs::returns('TaxManagerFactory::getManager', function () use ($calculator) {
            return new class($calculator) {
                private $calculator;

                public function __construct($calculator)
                {
                    $this->calculator = $calculator;
                }

                public function getTaxCalculator()
                {
                    return $this->calculator;
                }
            };
        });
    }

    /**
     * Run initContent() and decode the single JSON payload. The endpoint closes
     * every output buffer; PHPUnit's own buffer level is restored afterwards.
     */
    private function runAjax(\Buckaroo3AjaxModuleFrontController $controller): array
    {
        $level = ob_get_level();
        try {
            $controller->initContent();
        } finally {
            while (ob_get_level() < $level) {
                ob_start();
            }
        }
        self::assertCount(1, $controller->ajaxOutput, 'exactly one JSON response');

        return json_decode($controller->ajaxOutput[0], true);
    }

    /** @return string[] messages logged with PrestaShopLogger::addLog */
    private function shopLogs(): array
    {
        return array_map(function ($args) {
            return (string) $args[0];
        }, Stubs::callsTo('PrestaShopLogger::addLog'));
    }

    private function assertLogged(string $prefix): void
    {
        foreach ($this->shopLogs() as $message) {
            if (strpos($message, $prefix) === 0) {
                self::assertTrue(true);

                return;
            }
        }
        self::fail('No log starting with "' . $prefix . '" in: ' . implode(' | ', $this->shopLogs()));
    }

    private function errorPayload(string $message): array
    {
        return [
            'error' => true,
            'message' => $message,
            'cart_summary_totals' => '',
            'paymentFee' => null,
            'paymentFeeTax' => null,
            'includedTaxes' => null,
        ];
    }

    // -------------------------------------------------------------------------
    // Routing and top-level errors
    // -------------------------------------------------------------------------

    public function testUnknownActionIsAnswered(): void
    {
        $this->request(['action' => 'dropTables']);

        self::assertSame($this->errorPayload('Invalid action: dropTables'), $this->runAjax($this->controller()));
    }

    public function testExceptionInAnActionIsReturnedAsJsonAndReported(): void
    {
        $this->givenCart();
        Stubs::returns('Customer->getAddresses', function () {
            throw new \RuntimeException('address table missing');
        });
        $this->request(['action' => 'getPhoneStatus']);

        $payload = $this->runAjax($this->controller());

        self::assertSame($this->errorPayload('Error processing request: address table missing'), $payload);
        $this->assertLogged('Buckaroo3 Ajax Error: address table missing');
        self::assertCount(1, $this->reporter->reports);
        self::assertSame(['error', 'Buckaroo3 Ajax Error'], [$this->reporter->reports[0][1], $this->reporter->reports[0][2]]);
    }

    public function testFatalErrorIsReturnedWithoutDetailsOutsideDevMode(): void
    {
        $this->givenCart();
        Stubs::returns('Customer->getAddresses', function () {
            throw new \Error('undefined method');
        });
        $this->request(['action' => 'getPhoneStatus']);

        $payload = $this->runAjax($this->controller());

        self::assertSame($this->errorPayload('Fatal error processing request'), $payload);
        $this->assertLogged('Buckaroo3 Ajax Fatal Error: undefined method');
        self::assertSame('critical', $this->reporter->reports[0][1]);
    }

    // -------------------------------------------------------------------------
    // getPhoneStatus
    // -------------------------------------------------------------------------

    public function testPhoneStatusWithoutCart(): void
    {
        $this->request(['action' => 'getPhoneStatus']);

        self::assertSame(
            ['error' => true, 'delivery_phone' => '', 'billing_phone' => ''],
            $this->runAjax($this->controller())
        );
    }

    /**
     * @dataProvider phoneAddresses
     */
    public function testPhoneStatusPrefersTheMobileNumber(array $addresses, string $delivery, string $billing): void
    {
        $this->givenCart();
        Stubs::returns('Customer->getAddresses', $addresses);
        $this->request(['action' => 'getPhoneStatus']);

        self::assertSame(['delivery_phone' => $delivery, 'billing_phone' => $billing], $this->runAjax($this->controller()));
    }

    public function phoneAddresses(): array
    {
        return [
            'mobile numbers' => [[
                ['id_address' => 2, 'phone' => '0201234567', 'phone_mobile' => '0612345678'],
                ['id_address' => 3, 'phone' => '0301234567', 'phone_mobile' => '0687654321'],
                ['id_address' => 9, 'phone' => '0999', 'phone_mobile' => '0999'],
            ], '0612345678', '0687654321'],
            'landlines only' => [[
                ['id_address' => 2, 'phone' => '0201234567', 'phone_mobile' => ''],
                ['id_address' => 3, 'phone' => '0301234567', 'phone_mobile' => ''],
            ], '0201234567', '0301234567'],
            'no phone on the invoice address' => [[
                ['id_address' => 2, 'phone' => '', 'phone_mobile' => '0612345678'],
                ['id_address' => 3, 'phone' => '', 'phone_mobile' => ''],
            ], '0612345678', ''],
            'addresses not found' => [[], '', ''],
        ];
    }

    // -------------------------------------------------------------------------
    // getTotalCartPrice
    // -------------------------------------------------------------------------

    public function testTotalWithoutCartIsAnError(): void
    {
        $this->request(['action' => 'getTotalCartPrice', 'paymentFee' => '2']);

        self::assertSame(
            $this->errorPayload('Error calculating payment fee: Cart is not available'),
            $this->runAjax($this->controller())
        );
        $this->assertLogged('Buckaroo3 Ajax Error: Cart is not available');
    }

    public function testTotalWithoutFeeRendersTheSummaryWithGiftcards(): void
    {
        $cart = $this->givenCart();
        Stubs::db('/SELECT \* FROM `ps_bk_group_transaction`/', [[
            'transaction_key' => 'GC1',
            'amount' => '30.00',
            'refunded_amount' => '5.00',
            'card_code' => 'fashioncheque',
        ]]);
        Stubs::db('/FROM `ps_bk_giftcards`/', ['name' => 'Fashioncheque']);
        $presented = ['totals' => ['paymentFee' => 'EUR 1.00', 'paymentFeeTax' => 'EUR 0.21', 'includedTaxes' => ['amount' => '17.36']]];
        $this->request(['action' => 'getTotalCartPrice', 'paymentFee' => '  ']);

        $payload = $this->runAjax($this->controller($this->moduleWithPresenter($presented)));

        self::assertSame([
            'cart_summary_totals' => '<rendered checkout/_partials/cart-summary-totals>',
            'paymentFee' => 'EUR 1.00',
            'paymentFeeTax' => 'EUR 0.21',
            'paymentFeeTotal' => null,
            'includedTaxes' => ['amount' => '17.36'],
            'alreadyPaid' => 25,
            'remainingAmount' => 75,
            'giftcardItems' => [[
                'label' => 'Paid with Fashioncheque',
                'amount' => 25,
                'card_code' => 'fashioncheque',
                'transaction_key' => 'GC1',
            ]],
            'currencySign' => '€',
        ], $payload);
        self::assertSame([$cart], $this->presented);
        self::assertSame($presented, $this->context()->smarty->getTemplateVars('cart'));
        self::assertFalse($this->context()->smarty->getTemplateVars('display_transaction_updated_info'));
    }

    public function testSummaryWithoutAnyPresenterIsAnError(): void
    {
        $this->givenCart();
        $this->request(['action' => 'getTotalCartPrice']);

        $payload = $this->runAjax($this->controller());

        self::assertSame($this->errorPayload('Error rendering cart summary'), $payload);
        $this->assertLogged('Buckaroo3 Render Error: Cart presenter is not available');
        self::assertSame('Buckaroo3 Render Error', $this->reporter->reports[0][2]);
    }

    public function testSummaryWithoutCurrencySendsAnEmptySign(): void
    {
        $this->givenCart();
        $this->context()->currency = null;
        $this->request(['action' => 'getTotalCartPrice']);

        $payload = $this->runAjax($this->controller($this->moduleWithPresenter(['totals' => []])));

        self::assertSame('', $payload['currencySign']);
        self::assertNull($payload['paymentFee']);
    }

    public function testFixedFeeIsAddedWithTaxOnTop(): void
    {
        $this->givenCart();
        $this->givenTaxRate(10.0);
        \Configuration::updateValue(Config::PAYMENT_FEE_FRONTEND_LABEL, 'Handling');
        $presented = [
            'totals' => ['paymentFee' => 'old', 'paymentFeeTax' => 'old', 'products' => ['amount' => 100]],
            'subtotals' => [
                'products' => ['type' => 'products', 'label' => 'Subtotal'],
                'oldFee' => ['type' => 'fee', 'label' => 'Payment Fee'],
                'translated' => ['type' => 'fee', 'label' => [1 => 'Old payment fee']],
                'noLabel' => ['type' => 'discount'],
            ],
        ];
        $this->request(['action' => 'getTotalCartPrice', 'paymentFee' => '2.50']);

        $payload = $this->runAjax($this->controller($this->moduleWithPresenter($presented)));

        self::assertSame('EUR 2.50', $payload['paymentFee']);
        self::assertSame('EUR 0.25', $payload['paymentFeeTax']);
        self::assertSame('EUR 2.50+EUR 0.25', $payload['paymentFeeTotal']);
        self::assertNull($payload['includedTaxes'], 'included taxes only in subtotal mode');

        $cart = $this->context()->smarty->getTemplateVars('cart');
        self::assertSame(['products', 'noLabel', 'paymentFee'], array_keys($cart['subtotals']), 'old fee lines are removed');
        self::assertSame([
            'type' => 'paymentFee',
            'label' => 'Handling (inc taxes)',
            'amount' => 'EUR 2.75',
            'value' => 'EUR 2.50+EUR 0.25',
        ], $cart['subtotals']['paymentFee']);
        self::assertSame(['amount' => 100], $cart['totals']['products']);
        self::assertSame(
            ['type' => 'total', 'label' => 'Total', 'amount' => '102.75', 'value' => 'EUR 102.75'],
            $cart['totals']['total']
        );
        self::assertSame('85.14', $cart['totals']['total_excluding_tax']['amount']);
        self::assertSame('Total (tax incl.)', $cart['totals']['total_including_tax']['label']);
        self::assertSame('EUR 2.50+EUR 0.25', $cart['totals']['paymentFeeTotal']);
        self::assertArrayNotHasKey('paymentFee', $cart['totals']);
    }

    public function testPercentageFeeInSubtotalInclTaxModeSplitsOutTheTax(): void
    {
        $this->givenCart();
        $this->givenTaxRate(10.0);
        \Configuration::updateValue(Config::PAYMENT_FEE_MODE, 'subtotal_incl_tax');
        $this->request(['action' => 'getTotalCartPrice', 'paymentFee' => ' 2% ']);

        $payload = $this->runAjax($this->controller($this->moduleWithPresenter(['totals' => []])));

        self::assertSame('EUR 1.81', $payload['paymentFee']);
        self::assertSame('EUR 0.18', $payload['paymentFeeTax']);
        $cart = $this->context()->smarty->getTemplateVars('cart');
        self::assertSame('102.00', $cart['totals']['total']['amount']);
        self::assertSame('84.64', $cart['totals']['total_excluding_tax']['amount']);
        self::assertSame('Payment Fee (inc taxes)', $cart['subtotals']['paymentFee']['label']);
    }

    public function testSubtotalModeAddsTheIncludedTaxes(): void
    {
        $this->givenCart();
        \Configuration::updateValue(Config::PAYMENT_FEE_MODE, 'subtotal');
        $this->request(['action' => 'getTotalCartPrice', 'paymentFee' => '5']);

        $payload = $this->runAjax($this->controller($this->moduleWithPresenter(new \ArrayObject(['label' => 'x']))));

        self::assertSame('EUR 5.00', $payload['paymentFee']);
        self::assertSame('EUR 5.00', $payload['paymentFeeTotal'], 'no tax part without a tax rate');
        self::assertSame([
            'type' => 'tax',
            'label' => 'Included taxes',
            'amount' => '17.36',
            'value' => 'EUR 17.36',
        ], $payload['includedTaxes']);
        $cart = $this->context()->smarty->getTemplateVars('cart');
        self::assertSame('x', $cart['label'], 'a presented cart object is converted to an array');
        self::assertSame('105.00', $cart['totals']['total']['amount']);
    }

    /**
     * @dataProvider invalidFees
     */
    public function testInvalidFeeCountsAsZero(string $fee, string $log): void
    {
        $this->givenCart();
        $this->request(['action' => 'getTotalCartPrice', 'paymentFee' => $fee]);

        $payload = $this->runAjax($this->controller($this->moduleWithPresenter(['totals' => []])));

        self::assertSame('EUR 0.00', $payload['paymentFee']);
        self::assertSame('100.00', $this->context()->smarty->getTemplateVars('cart')['totals']['total']['amount']);
        if ($log !== '') {
            self::assertContains($log, $this->shopLogs());
        } else {
            self::assertSame([], $this->shopLogs());
        }
    }

    public function invalidFees(): array
    {
        return [
            'text percentage' => ['abc%', 'Buckaroo3: Invalid percentage fee value: abc%'],
            'text amount' => ['abc', 'Buckaroo3: Invalid fixed fee value: abc'],
            'negative amount' => ['-3', ''],
        ];
    }

    public function testTaxLookupFailureFallsBackToNoTax(): void
    {
        $this->givenCart();
        Stubs::object('Address', 3);
        Stubs::throws('TaxManagerFactory::getManager', new \RuntimeException('no tax rules'));
        $this->request(['action' => 'getTotalCartPrice', 'paymentFee' => '2']);

        $payload = $this->runAjax($this->controller($this->moduleWithPresenter(['totals' => []])));

        self::assertSame('EUR 0.00', $payload['paymentFeeTax']);
        self::assertContains('Buckaroo3 Tax Calculation Error: no tax rules', $this->shopLogs());
    }

    /**
     * @dataProvider missingTaxSources
     */
    public function testFeeWithoutTaxSourceHasNoTax(array $cartProps, bool $managerFound): void
    {
        $cart = $this->givenCart();
        $cart->hydrate($cartProps);
        if ($managerFound) {
            Stubs::object('Address', 2);
            Stubs::returns('TaxManagerFactory::getManager', new class {
                public function getTaxCalculator()
                {
                    return null;
                }
            });
        }
        $this->request(['action' => 'getTotalCartPrice', 'paymentFee' => '2']);

        $payload = $this->runAjax($this->controller($this->moduleWithPresenter(['totals' => []])));

        self::assertSame('EUR 2.00', $payload['paymentFee']);
        self::assertSame('EUR 0.00', $payload['paymentFeeTax']);
    }

    public function missingTaxSources(): array
    {
        return [
            'no address on the cart' => [['id_address_invoice' => 0, 'id_address_delivery' => 0], false],
            'unknown address' => [['id_address_invoice' => 0], false],
            'no tax calculator' => [['id_address_invoice' => 0], true],
        ];
    }

    public function testTotalsFailureFallsBackToTheCartTotalsWithoutFee(): void
    {
        $cart = $this->givenCart();
        $calls = 0;
        $cart->stub('getOrderTotal', function ($withTaxes = true) use (&$calls) {
            if (++$calls === 2) {
                throw new \RuntimeException('totals unavailable');
            }

            return $withTaxes ? 100.0 : 82.64;
        });
        $this->request(['action' => 'getTotalCartPrice', 'paymentFee' => '2']);

        $payload = $this->runAjax($this->controller($this->moduleWithPresenter(['totals' => []])));

        self::assertSame('EUR 0.00', $payload['paymentFee']);
        self::assertContains('Buckaroo3 CalculateOrderTotals Error: totals unavailable', $this->shopLogs());
        self::assertSame('100', $this->context()->smarty->getTemplateVars('cart')['totals']['total']['amount']);
    }

    public function testPresenterFromTheControllerContainerIsUsed(): void
    {
        $cart = $this->givenCart();
        $controller = $this->controller();
        Stubs::returns('Buckaroo3AjaxModuleFrontController->get', $this->presenter(['totals' => []]));
        $this->request(['action' => 'getTotalCartPrice', 'paymentFee' => '1']);

        $payload = $this->runAjax($controller);

        self::assertSame('EUR 1.00', $payload['paymentFee']);
        self::assertSame([$cart], $this->presented);
    }

    /**
     * Regression: renderCartSummary() fetched the controller's presenter but
     * discarded it, so without a module core container the summary failed.
     */
    public function testSummaryUsesThePresenterOfTheControllerContainer(): void
    {
        $cart = $this->givenCart();
        $controller = $this->controller();
        Stubs::returns('Buckaroo3AjaxModuleFrontController->get', $this->presenter(['totals' => []]));
        $this->request(['action' => 'getTotalCartPrice']);

        $payload = $this->runAjax($controller);

        self::assertArrayNotHasKey('error', $payload);
        self::assertSame('<rendered checkout/_partials/cart-summary-totals>', $payload['cart_summary_totals']);
        self::assertSame([$cart], $this->presented);
        self::assertSame(['totals' => []], $this->context()->smarty->getTemplateVars('cart'));
    }

    public function testFeeWithoutPresenterEndsInTheRenderError(): void
    {
        $this->givenCart();
        $module = new AjaxTestModule();
        $module->coreContainer = new ServiceContainer();
        $this->request(['action' => 'getTotalCartPrice', 'paymentFee' => '1']);

        $payload = $this->runAjax($this->controller($module));

        self::assertSame($this->errorPayload('Error rendering cart summary'), $payload);
        self::assertContains('Buckaroo3 UpdatePresentedCart Error: Cart presenter is not available', $this->shopLogs());
        $this->assertLogged('Buckaroo3 Render Error: Cart presenter is not available');
    }

    public function testFailingPriceFormatterShowsZero(): void
    {
        $this->givenCart();
        $this->context()->currentLocale = new class {
            public function formatPrice($amount, $isoCode)
            {
                throw new \RuntimeException('no CLDR data');
            }
        };
        $this->request(['action' => 'getTotalCartPrice', 'paymentFee' => '1']);

        $payload = $this->runAjax($this->controller($this->moduleWithPresenter(['totals' => []])));

        self::assertSame('0', $payload['paymentFee']);
        self::assertContains('Buckaroo3 FormatPrice Error: no CLDR data Amount: 1.00', $this->shopLogs());
    }

    public function testNonNumericAmountIsFormattedAsZero(): void
    {
        self::assertSame('0', self::invoke($this->controller(), 'formatPrice', ['n/a']));
    }
}
