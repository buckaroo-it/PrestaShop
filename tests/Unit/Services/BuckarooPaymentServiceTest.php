<?php

declare(strict_types=1);

namespace BuckarooTest\Unit\Services;

use Buckaroo\PrestaShop\Src\Entity\BkOrdering;
use Buckaroo\PrestaShop\Src\Entity\BkPaymentMethods;
use Buckaroo\PrestaShop\Src\Service\BuckarooPaymentService;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;
use Doctrine\ORM\EntityManager;

/**
 * Covers the BuckarooPaymentService paths the flow tests in tests/Unit/Service
 * leave out: the real constructor, separately listed cards and giftcards,
 * checkout ordering, fee labels and the Riverty/Billink address helpers.
 */
final class BuckarooPaymentServiceTest extends TestCase
{
    /** @var array<string, array<string, mixed>|null> config per method */
    private $configs = [];

    /** @var array<int, object> payment method rows */
    private $details = [];

    /** @var string[] method names enabled for the country, in checkout order */
    private $ordering = [];

    /** @var array|null */
    private $country = ['id' => 1];

    /** @var string */
    private $in3Method = 'in3';

    protected function setUp(): void
    {
        parent::setUp();
        $module = new \Module();
        $module->stub('isPaymentModeActive', true);
        $module->stub('isIdinCheckout', false);
        \Module::$instances['buckaroo3'] = $module;
        $this->context()->country->iso_code = 'NL';
    }

    private static function details(string $name, string $label, string $icon = '', string $template = ''): object
    {
        return new class($name, $label, $icon, $template) {
            private $name;
            private $label;
            private $icon;
            private $template;

            public function __construct($name, $label, $icon, $template)
            {
                $this->name = $name;
                $this->label = $label;
                $this->icon = $icon;
                $this->template = $template;
            }

            public function getName()
            {
                return $this->name;
            }

            public function getLabel()
            {
                return $this->label;
            }

            public function getIcon()
            {
                return $this->icon;
            }

            public function getTemplate()
            {
                return $this->template;
            }
        };
    }

    private function service(): BuckarooPaymentService
    {
        $test = $this;

        $methods = new class($test) {
            private $test;

            public function __construct($test)
            {
                $this->test = $test;
            }

            public function findAll()
            {
                return $this->test->detailRows();
            }

            public function getActivePaymentMethods($countryId)
            {
                return [['id' => 1], ['id' => 2]];
            }
        };
        $ordering = new class($test) {
            private $test;

            public function __construct($test)
            {
                $this->test = $test;
            }

            public function fetchPositions($countryId, $ids)
            {
                return $this->test->orderingRows();
            }
        };
        $em = new EntityManager();
        $em->repositories = [BkPaymentMethods::class => $methods, BkOrdering::class => $ordering];

        $configService = new class($test) {
            private $test;

            public function __construct($test)
            {
                $this->test = $test;
            }

            public function getConfigArrayForMethod($method)
            {
                return $this->test->configFor($method);
            }

            public function getConfigValue($method, $key)
            {
                return $this->test->configFor($method)[$key] ?? null;
            }
        };
        $feeService = new class {
            public function getBuckarooFeeInputs($method)
            {
                return [['type' => 'hidden', 'name' => 'buckarooKey', 'value' => $method]];
            }
        };
        $payByBank = new class {
            public function get()
            {
                return [];
            }

            public function getSelectedIssuerLogo()
            {
                return 'issuer.svg';
            }
        };
        $in3 = new class($test) {
            private $test;

            public function __construct($test)
            {
                $this->test = $test;
            }

            public function getMethod()
            {
                return $this->test->in3Name();
            }

            public function getLogo()
            {
                return 'In3.svg';
            }
        };
        $countries = new class($test) {
            private $test;

            public function __construct($test)
            {
                $this->test = $test;
            }

            public function getCountryByIsoCode2($iso)
            {
                return $iso === 'NL' ? $this->test->countryRow() : null;
            }
        };

        return new BuckarooPaymentService($em, $feeService, $configService, $payByBank, $in3, $countries, $this->context());
    }

    /** @internal used by the repository doubles */
    public function detailRows(): array
    {
        return $this->details;
    }

    /** @internal */
    public function orderingRows(): array
    {
        return $this->ordering;
    }

    /** @internal */
    public function configFor($method)
    {
        return array_key_exists($method, $this->configs) ? $this->configs[$method] : null;
    }

    /** @internal */
    public function in3Name(): string
    {
        return $this->in3Method;
    }

    /** @internal */
    public function countryRow()
    {
        return $this->country;
    }

    private static function cart(int $invoice = 0, int $delivery = 0, float $total = 50.0): \Cart
    {
        $cart = new \Cart();
        $cart->id = 3;
        $cart->id_customer = 9;
        $cart->id_address_invoice = $invoice;
        $cart->id_address_delivery = $delivery;
        $cart->totals[\Cart::BOTH] = $total;

        return $cart;
    }

    private static function address(int $id, string $iso, string $company = '', $address1 = 'Hoofdstraat 1'): void
    {
        \Country::$isoById[$id] = $iso;
        Stubs::object('Address', $id, ['id_country' => $id, 'company' => $company, 'address1' => $address1]);
    }

    /**
     * @param array<int, object> $options
     *
     * @return array<int, array{0: string, 1: string, 2: string}> module name, title, logo
     */
    private static function summary(array $options): array
    {
        return array_map(function ($option) {
            return [$option->getModuleName(), $option->getCallToActionText(), $option->getLogo()];
        }, array_values($options));
    }

    public function testSeparateCardsAreListedInCheckoutOrder(): void
    {
        $this->details = [
            self::details('ideal', 'iDEAL', 'iDEAL.svg'),
            self::details('creditcard', 'Cards', 'Creditcards.svg'),
            self::details('giftcard', 'Giftcards', 'Giftcards.svg', 'payment_giftcard.tpl'),
        ];
        $this->ordering = ['giftcard', 'creditcard', 'ideal'];
        $this->configs = [
            'ideal' => ['payment_fee' => '-1'],
            'creditcard' => [
                'display_in_checkout' => 'separate',
                'payment_fee' => ' 2% ',
                'activeCreditcards' => [['service_code' => 'visa'], ['service_code' => 'unknowncard'], ['name' => 'no code']],
            ],
            'giftcard' => [
                'display_in_checkout' => 'separate',
                'activeGiftcards' => [
                    'giftcards' => [['code' => 'boekenbon'], ['code' => 'nologo'], ['service_code' => 'unnamed'], ['name' => 'skipped'], 'bad'],
                    'customGiftcards' => [['code' => 'remote'], ['code' => 'local'], ['code' => 'plain']],
                ],
            ],
        ];
        Stubs::db('/FROM `ps_bk_giftcards`/', [
            ['code' => 'boekenbon', 'name' => 'Boekenbon', 'logo' => 'Boekenbon.svg', 'is_custom' => 0],
            ['code' => 'nologo', 'name' => 'No logo', 'logo' => '', 'is_custom' => 0],
            ['code' => 'unnamed', 'logo' => 'Unnamed.svg', 'is_custom' => 0],
            ['code' => 'remote', 'name' => 'Remote', 'logo' => 'https://cdn.example/remote.png', 'is_custom' => 1],
            ['code' => 'local', 'name' => 'Local', 'logo' => '/img/local.png', 'is_custom' => 1],
            ['code' => 'plain', 'name' => 'Plain', 'logo' => 'plain.png', 'is_custom' => 1],
        ]);

        $options = $this->service()->getPaymentOptions(self::cart());

        $base = '/modules/buckaroo3/views/img/buckaroo/';
        self::assertSame([
            ['giftcard', 'Boekenbon', $base . 'Giftcards/SVG/Boekenbon.svg'],
            ['giftcard', 'No logo', $base . 'Payment methods/SVG/Giftcards.svg'],
            ['giftcard', 'Giftcards', $base . 'Giftcards/SVG/Unnamed.svg'],
            ['giftcard', 'Remote', 'https://cdn.example/remote.png'],
            ['giftcard', 'Local', '/img/local.png'],
            ['giftcard', 'Plain', $base . 'Giftcards/SVG/BuckarooVoucher.svg'],
            ['creditcard', 'VISA + 2%', $base . 'Creditcard issuers/SVG/Visa.svg'],
            ['creditcard', 'Cards + 2%', $base . 'Payment methods/SVG/Creditcards.svg'],
            ['ideal', 'iDEAL', $base . 'Payment methods/SVG/iDEAL.svg'],
        ], self::summary($options));

        $visa = $options[6];
        self::assertSame('https://shop.test/module/buckaroo3/request?method=creditcard&cardCode=visa', $visa->getAction());
        self::assertSame(
            [['type' => 'hidden', 'name' => 'buckarooKey', 'value' => 'creditcard'],
                ['type' => 'hidden', 'name' => 'cardCode', 'value' => 'visa'],
                ['type' => 'hidden', 'name' => 'BPE_CreditCard', 'value' => 'visa']],
            $visa->getInputs()
        );

        $boekenbon = $options[0];
        self::assertSame('https://shop.test/module/buckaroo3/applygiftcard?cardCode=boekenbon', $boekenbon->getAction());
        self::assertSame('rendered:payment_giftcard.tpl', $boekenbon->getForm());
        self::assertSame('plain', $this->context()->smarty->getTemplateVars('cardCode'), 'last rendered card');
        self::assertSame('cardCode', $boekenbon->getInputs()[1]['name']);
    }

    public function testGiftcardsFallBackToTheSeededListWhenTheTableIsUnavailable(): void
    {
        $this->details = [self::details('giftcard', 'Giftcards', 'Giftcards.svg')];
        $this->ordering = ['giftcard'];
        $this->configs = ['giftcard' => [
            'display_in_checkout' => 'separate',
            // Legacy flat list of cards.
            'activeGiftcards' => [['code' => 'boekenbon'], ['service_code' => 'mystery'], 'bad', ['name' => 'x']],
        ]];
        Stubs::db('/FROM `ps_bk_giftcards`/', false);

        $base = '/modules/buckaroo3/views/img/buckaroo/';
        self::assertSame([
            ['giftcard', 'Boekenbon', $base . 'Giftcards/SVG/Boekenbon.svg'],
            ['giftcard', 'mystery', $base . 'Giftcards/SVG/BuckarooVoucher.svg'],
        ], self::summary($this->service()->getPaymentOptions(self::cart())));
    }

    public function testSeparateGiftcardsWithoutSelectionAddNothing(): void
    {
        $this->details = [self::details('giftcard', 'Giftcards'), self::details('creditcard', 'Cards')];
        $this->ordering = ['giftcard', 'creditcard'];
        $this->configs = [
            'giftcard' => ['display_in_checkout' => 'separate', 'activeGiftcards' => 'none'],
            'creditcard' => ['display_in_checkout' => 'separate', 'activeCreditcards' => []],
        ];

        self::assertSame([], $this->service()->getPaymentOptions(self::cart()));
    }

    public function testIn3OldIsSortedAsIn3AndUsesTheIn3Label(): void
    {
        self::address(1, 'NL');
        $this->in3Method = 'in3Old';
        $this->details = [self::details('ideal', 'iDEAL', 'iDEAL.svg'), self::details('in3', 'In3', 'In3.svg')];
        $this->ordering = ['in3', 'ideal'];
        $this->configs = ['ideal' => [], 'in3' => ['frontend_label' => 'Pay in 3', 'payment_fee' => '1.5']];

        $options = $this->service()->getPaymentOptions(self::cart(1, 1));

        self::assertSame([
            ['in3Old', 'Pay in 3 + EUR 1.50', '/modules/buckaroo3/views/img/buckaroo/Payment methods/SVG/In3.svg'],
            ['ideal', 'iDEAL', '/modules/buckaroo3/views/img/buckaroo/Payment methods/SVG/iDEAL.svg'],
        ], self::summary($options));
    }

    public function testNoOptionsWhenTheShopperCountryIsUnknown(): void
    {
        $this->country = null;
        $this->details = [self::details('ideal', 'iDEAL')];
        $this->ordering = ['ideal'];
        $this->configs = ['ideal' => []];

        self::assertSame([], $this->service()->getPaymentOptions(self::cart()));
        self::assertFalse($this->service()->isMethodAvailableForCart(self::cart(), 'ideal'));
    }

    public function testOptionWhoseTemplateFailsIsDroppedAndLogged(): void
    {
        $this->details = [self::details('ideal', 'iDEAL', 'iDEAL.svg', 'broken.tpl'), self::details('paybybank', 'PayByBank')];
        $this->ordering = ['ideal', 'paybybank'];
        $this->configs = ['ideal' => [], 'paybybank' => []];
        $this->context()->smarty->stub('fetch', function ($template) {
            if (strpos((string) $template, 'broken.tpl') !== false) {
                throw new \Exception('template missing');
            }

            return 'ok';
        });

        $options = $this->service()->getPaymentOptions(self::cart());

        self::assertSame([
            ['paybybank', 'PayByBank', '/modules/buckaroo3/views/img/buckaroo/Payment methods/SVG/issuer.svg'],
        ], self::summary($options));
    }

    public function testLabelOfAMethodWithoutConfigIsNull(): void
    {
        self::assertNull($this->service()->getBuckarooLabel('missing', 'Missing'));
    }

    /**
     * @dataProvider feeLabelProvider
     */
    public function testFeeLabel($fee, string $expected): void
    {
        $this->configs = ['ideal' => ['payment_fee' => $fee]];

        self::assertSame($expected, $this->service()->getBuckarooLabel('ideal', 'iDEAL'));
    }

    public function feeLabelProvider(): array
    {
        return [
            'no fee' => ['', 'iDEAL'],
            'zero' => ['0', 'iDEAL'],
            'negative' => ['-3', 'iDEAL'],
            'not a number' => ['abc', 'iDEAL'],
            'percentage' => [' 5 % ', 'iDEAL + 5 %'],
            'amount' => ['2.5', 'iDEAL + EUR 2.50'],
            'numeric amount' => [3, 'iDEAL + EUR 3.00'],
        ];
    }

    public function testCustomerIdinIsValidOnlyWhenEighteenOrOlder(): void
    {
        Stubs::db('/bk_customer_idin/', function ($sql) {
            return strpos($sql, 'customer_id = 9') !== false ? 'True' : 'False';
        });
        $service = $this->service();

        self::assertTrue($service->isCustomerIdinValid(self::cart()));
        $other = self::cart();
        $other->id_customer = 10;
        self::assertFalse($service->isCustomerIdinValid($other));
        self::assertSame(
            'SELECT buckaroo_idin_iseighteenorolder FROM `ps_bk_customer_idin` WHERE (customer_id = 9)',
            Stubs::sql('getValue')[0]
        );
    }

    /**
     * @dataProvider afterpayProvider
     */
    public function testAfterpayAvailabilityForCompanies(string $customerType, string $company, float $total, bool $expected): void
    {
        self::address(1, 'NL', $company);
        $this->details = [self::details('afterpay', 'Riverty')];
        $this->ordering = ['afterpay'];
        $this->configs = [
            'afterpay' => ['customer_type' => $customerType],
            'AFTERPAY' => ['max_order_amount' => 100],
        ];

        self::assertSame($expected, $this->service()->isMethodAvailableForCart(self::cart(1, 1, $total), 'afterpay'));
    }

    public function afterpayProvider(): array
    {
        return [
            'both, NL company within B2B limit' => ['both', 'Acme', 50.0, true],
            'both, NL company above B2B limit' => ['both', 'Acme', 150.0, false],
            'both, consumer ignores the B2B limit' => ['both', '', 150.0, true],
            'B2C' => ['B2C', 'Acme', 150.0, true],
        ];
    }

    public function testAfterpayWithoutAddressIdsTreatsTheCustomerAsConsumer(): void
    {
        $this->details = [self::details('afterpay', 'Riverty')];
        $this->ordering = ['afterpay'];
        $this->configs = ['afterpay' => ['customer_type' => 'both']];
        $cart = self::cart();
        $cart->id_address_invoice = null;
        $cart->id_address_delivery = null;

        self::assertTrue($this->service()->isMethodAvailableForCart($cart, 'afterpay'));
    }

    /**
     * @dataProvider cocProvider
     */
    public function testChamberOfCommerceFieldIsShownForB2BOrDutchCompanies(string $type, string $company, string $iso, bool $expected): void
    {
        self::address(1, $iso, $company);
        $this->configs = ['afterpay' => ['customer_type' => $type], 'billink' => ['customer_type' => $type]];
        $service = $this->service();

        self::assertSame($expected, $service->showAfterpayCoc(self::cart(1, 1)));
        self::assertSame($expected, $service->showBillinkCoc(self::cart(1, 1)));
    }

    public function cocProvider(): array
    {
        return [
            'B2B' => ['B2B', '', 'DE', true],
            'B2C' => ['B2C', 'Acme', 'NL', false],
            'both with NL company' => ['both', 'Acme', 'NL', true],
            'both with BE company' => ['both', 'Acme', 'BE', false],
            'both without company' => ['both', ' ', 'NL', false],
        ];
    }

    public function testCocUsesTheBillingAddressCompanyToo(): void
    {
        self::address(1, 'NL', 'Acme');
        self::address(2, 'BE');
        $this->configs = ['afterpay' => ['customer_type' => 'both']];

        self::assertTrue($this->service()->showAfterpayCoc(self::cart(1, 2)));
    }

    public function testGetAddressByIdReturnsNullForAnAddressThatDoesNotExist(): void
    {
        self::address(1, 'NL');
        $service = $this->service();
        $method = new \ReflectionMethod($service, 'getAddressById');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }

        self::assertInstanceOf(\Address::class, $method->invoke($service, 1));
        self::assertSame(1, $method->invoke($service, 1)->id);
        self::assertNull($method->invoke($service, 404));
        self::assertNull($method->invoke($service, 0));
        self::assertNull($method->invoke($service, null));
        self::assertNull($method->invoke($service, ['1']));
    }

    public function testBillingCountryOfAnUnknownAddressIsNull(): void
    {
        // An unloaded Address has id_country 0; it must not be looked up as a country.
        \Country::$isoById[0] = 'NL';
        $service = $this->service();
        $method = new \ReflectionMethod($service, 'getBillingCountryIso');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }

        self::assertNull($method->invoke($service, self::cart(404, 404)));
    }

    public function testAfterpayB2BWithUnknownAddressesIsUnavailable(): void
    {
        \Country::$isoById[0] = 'NL';
        $this->details = [self::details('afterpay', 'Riverty')];
        $this->ordering = ['afterpay'];
        $this->configs = ['afterpay' => ['customer_type' => 'B2B']];

        self::assertFalse($this->service()->isMethodAvailableForCart(self::cart(404, 405), 'afterpay'));
    }

    public function testHouseNumberCheckWithUnknownAddressesPasses(): void
    {
        \Country::$isoById[0] = 'DE';

        self::assertSame(
            ['billing' => true, 'shipping' => true],
            $this->service()->areHouseNumberValidForCountryDE(self::cart(404, 405))
        );
    }

    public function testGermanAddressesNeedAHouseNumber(): void
    {
        self::address(1, 'DE', '', 'Hauptstrasse');
        self::address(2, 'DE', '', 'Hauptstrasse 5');
        self::address(3, 'NL', '', 'Dorpsstraat');
        self::address(4, 'DE', '', null);

        $service = $this->service();
        self::assertSame(['billing' => false, 'shipping' => true], $service->areHouseNumberValidForCountryDE(self::cart(1, 2)));
        self::assertSame(['billing' => true, 'shipping' => false], $service->areHouseNumberValidForCountryDE(self::cart(3, 1)));
        self::assertSame(['billing' => false, 'shipping' => false], $service->areHouseNumberValidForCountryDE(self::cart(4, 4)));
        self::assertSame(['billing' => true, 'shipping' => true], $service->areHouseNumberValidForCountryDE(self::cart(0, 3)), 'no invoice address: delivery is used');
    }

    /**
     * @dataProvider currencyProvider
     */
    public function testCurrencyBoundMethods(string $method, int $currencyId, bool $expected): void
    {
        \Currency::$isoById = [2 => 'chf', 3 => 'SEK', 4 => 'EUR'];
        $this->details = [self::details($method, $method)];
        $this->ordering = [$method];
        $this->configs = [$method => []];
        $cart = self::cart();
        $cart->id_currency = $currencyId;

        self::assertSame($expected, $this->service()->isMethodAvailableForCart($cart, $method));
    }

    public function currencyProvider(): array
    {
        return [
            'twint in CHF (case-insensitive)' => ['twint', 2, true],
            'twint in EUR' => ['twint', 4, false],
            'swish in SEK' => ['swish', 3, true],
            'wero in EUR' => ['wero', 4, true],
            'wero in SEK' => ['wero', 3, false],
            'unloaded currency' => ['wero', 0, false],
        ];
    }
}
