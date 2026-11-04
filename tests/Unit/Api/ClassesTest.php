<?php

namespace BuckarooTest\Unit\Api;

use Buckaroo\PrestaShop\Classes\CapayableIn3;
use Buckaroo\PrestaShop\Classes\Config;
use Buckaroo\PrestaShop\Classes\Issuers\Issuers;
use Buckaroo\PrestaShop\Classes\Issuers\PayByBank;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;

require_once __DIR__ . '/ApiTestSupport.php';

/**
 * classes/: gateway mode lookup, In3 metadata, Sendcloud service points and
 * the PayByBank issuer list.
 */
class ClassesTest extends TestCase
{
    private const DEFAULT_ISSUERS = ['NTSBDEB1', 'INGBNL2A', 'ABNANL2A', 'RABONL2U', 'KNABNL2H', 'SNSBNL2A', 'RBRBNL21', 'ASNBNL21'];

    protected function setUp(): void
    {
        parent::setUp();
        ApiFixtures::load();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTPS_PROXY'], $_SERVER['NO_PROXY']);
        \SendcloudServicePoint::$details = [];
        parent::tearDown();
    }

    /**
     * @dataProvider modes
     */
    public function testGetModeReadsTheModeOfThePaymentMethod(string $stored, string $expected): void
    {
        ApiFixtures::gatewayMode($stored);

        self::assertSame($expected, Config::getMode('ideal'));
        $sql = Stubs::sql('getValue');
        self::assertStringContainsString('name = "ideal"', $sql[0]);
        self::assertStringContainsString('configurable_id = 7', $sql[1]);
    }

    public function modes(): array
    {
        return [
            'live' => ['live', 'live'],
            'test' => ['test', 'test'],
            'off' => ['off', 'test'],
        ];
    }

    public function testGetModeFailsWithoutConfiguration(): void
    {
        $this->expectException(\Exception::class);

        Config::getMode('ideal');
    }

    public function testCapayableIn3AlwaysUsesV3(): void
    {
        $in3 = new CapayableIn3(new ConfigServiceDouble(['in3.version' => 'V2']));

        self::assertTrue($in3->isV3());
        self::assertSame('In3.svg?v', $in3->getLogo());
        self::assertSame('in3', $in3->getMethod());
        self::assertSame('V2', CapayableIn3::VERSION_V2);
    }

    public function sendcloudCountries(): array
    {
        $country = new \Country();
        $country->iso_code = 'be';

        return [
            'ISO code' => ['NL', 'NL'],
            'lowercase ISO code' => ['nl', 'NL'],
            // Regression: consumers read ->iso_code, so a Country object must work as well.
            'Country object' => [$country, 'BE'],
        ];
    }

    /**
     * @dataProvider sendcloudCountries
     */
    public function testCarrierHandlerReturnsTheSendcloudServicePoint($country, string $iso): void
    {
        \Configuration::updateValue('PS_LANG_DEFAULT', 1);
        Stubs::object('Carrier', 3, ['external_module_name' => 'sendcloud']);
        $cart = new \Cart();
        $cart->id = 42;
        $cart->id_carrier = 3;
        \SendcloudServicePoint::$details[42] = (object) [
            'street' => 'Pickup Street',
            'house_number' => '12',
            'postal_code' => '1011AB',
            'city' => 'Amsterdam',
            'country' => $country,
        ];

        $address = (new \CarrierHandler($cart))->handleSendCloud();

        self::assertSame([
            'street' => 'Pickup Street',
            'houseNumber' => '12',
            'houseNumberSuffix' => '',
            'zipcode' => '1011AB',
            'city' => 'Amsterdam',
            'country' => $iso,
        ], $address);
        self::assertSame([[42]], Stubs::callsTo('SendcloudServicePoint::getFromCart'));
    }

    public function testCarrierHandlerIgnoresOtherCarriers(): void
    {
        Stubs::object('Carrier', 3, ['external_module_name' => 'postnl']);
        $cart = new \Cart();
        $cart->id = 42;
        $cart->id_carrier = 3;

        self::assertNull((new \CarrierHandler($cart))->handleSendCloud());
        self::assertSame(0, Stubs::callCount('SendcloudServicePoint::getFromCart'));
    }

    public function testCachedIssuersAreMergedWithTheDefaultBanks(): void
    {
        // No credentials are configured: a cache miss would throw, so this also
        // proves PayByBank reads its own cache keys.
        $this->cacheIssuers('BUCKAROO_PAYBYBANK_ISSUERS_CACHE', 'BUCKAROO_PAYBYBANK_ISSUERS_CACHE_DATE', [
            'BUNQNL2A' => ['name' => 'bunq', 'logo' => 'Bunq.svg'],
            'INGBNL2A' => ['name' => 'ING Bank', 'logo' => 'ING.svg'],
        ]);

        $issuers = $this->payByBank($this->context())->get();

        self::assertSame(array_merge(self::DEFAULT_ISSUERS, ['BUNQNL2A']), array_keys($issuers));
        self::assertSame(['name' => 'ING Bank', 'logo' => 'ING.svg', 'selected' => false], $issuers['INGBNL2A']);
        self::assertSame(['name' => 'bunq', 'logo' => 'Bunq.svg', 'selected' => false], $issuers['BUNQNL2A']);
    }

    public function testTheLastUsedBankComesFirstAndIsSelected(): void
    {
        $this->cacheIssuers('BUCKAROO_PAYBYBANK_ISSUERS_CACHE', 'BUCKAROO_PAYBYBANK_ISSUERS_CACHE_DATE', []);
        $this->context()->cookie->BUCKAROO_LAST_PAYBYBANK_ISSUER = 'RABONL2U';

        $payByBank = $this->payByBank($this->context());
        $issuers = $payByBank->get();

        self::assertSame('RABONL2U', array_key_first($issuers));
        self::assertTrue($issuers['RABONL2U']['selected']);
        self::assertCount(1, array_filter(array_column($issuers, 'selected')));
        self::assertSame('../../PayByBank issuers/Rabobank.svg', $payByBank->getSelectedIssuerLogo());
    }

    public function testWithoutASelectedBankTheGenericLogoIsUsed(): void
    {
        $this->cacheIssuers('BUCKAROO_PAYBYBANK_ISSUERS_CACHE', 'BUCKAROO_PAYBYBANK_ISSUERS_CACHE_DATE', []);

        self::assertSame('PayByBank.svg?v', $this->payByBank($this->context())->getSelectedIssuerLogo());
    }

    public function testWithoutAContextNoBankIsSelected(): void
    {
        $this->cacheIssuers('BUCKAROO_PAYBYBANK_ISSUERS_CACHE', 'BUCKAROO_PAYBYBANK_ISSUERS_CACHE_DATE', []);

        $issuers = $this->payByBank()->get();

        self::assertSame(self::DEFAULT_ISSUERS, array_keys($issuers));
        self::assertSame([], array_filter(array_column($issuers, 'selected')));
    }

    public function testStaleCacheWithoutCredentialsFails(): void
    {
        \Configuration::updateValue('BUCKAROO_PAYBYBANK_ISSUERS_CACHE', json_encode([]));
        \Configuration::updateValue('BUCKAROO_PAYBYBANK_ISSUERS_CACHE_DATE', '2000-01-01');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Buckaroo master settings not found.');

        $this->payByBank($this->context())->get();
    }

    /**
     * The SDK request is sent through an HTTPS proxy on a closed loopback port,
     * so it fails at once without leaving the machine; the SDK then answers no
     * issuers and the stale cache is kept.
     */
    public function testUnreachableGatewayKeepsTheStaleCache(): void
    {
        if (getenv('NO_PROXY') || getenv('no_proxy')) {
            self::markTestSkipped('NO_PROXY is set: the request could bypass the loopback proxy.');
        }
        $_SERVER['HTTPS_PROXY'] = 'http://127.0.0.1:9';
        $_SERVER['NO_PROXY'] = '';
        \Configuration::updateValue('BUCKAROO_MERCHANT_KEY', 'TESTWEBSITEKEY');
        \Configuration::updateValue('BUCKAROO_SECRET_KEY', 'SECRET');
        ApiFixtures::gatewayMode('test');
        $this->cacheIssuers('BUCKAROO_PAYBYBANK_ISSUERS_CACHE', 'BUCKAROO_PAYBYBANK_ISSUERS_CACHE_DATE', [
            'BUNQNL2A' => ['name' => 'bunq', 'logo' => 'Bunq.svg'],
        ]);
        \Configuration::updateValue('BUCKAROO_PAYBYBANK_ISSUERS_CACHE_DATE', '2000-01-01');

        $issuers = $this->payByBank()->get();

        self::assertArrayHasKey('BUNQNL2A', $issuers);
        self::assertStringContainsString('name = "paybybank"', Stubs::sql('getValue')[0]);
        self::assertSame('2000-01-01', \Configuration::get('BUCKAROO_PAYBYBANK_ISSUERS_CACHE_DATE'));
    }

    public function testRetrievedIssuersGetALogoAndUnknownBanksAreDropped(): void
    {
        $formatted = $this->invokeIssuers('formatIssuers', [[
            ['id' => 'ABNANL2A', 'name' => 'ABN AMRO'],
            ['id' => 'NNBANL2G', 'name' => 'Nationale-Nederlanden'],
            ['id' => 'XXXXNL2X', 'name' => 'Unknown bank'],
            ['name' => 'No id'],
        ]]);

        self::assertSame([
            'ABNANL2A' => ['name' => 'ABN AMRO', 'logo' => 'ABNAMRO.svg'],
            'NNBANL2G' => ['name' => 'Nationale-Nederlanden', 'logo' => 'NN.svg'],
        ], $formatted);
    }

    public function testSavedIssuersAreCachedForToday(): void
    {
        $issuers = ['ABNANL2A' => ['name' => 'ABN AMRO', 'logo' => 'ABNAMRO.svg']];

        $this->invokeIssuers('saveIssuers', [$issuers]);
        $this->invokeIssuers('saveIssuers', ['not an array']);

        self::assertSame(json_encode($issuers), \Configuration::get('BUCKAROO_PAYBYBANK_ISSUERS_CACHE'));
        self::assertSame(date('Y-m-d'), \Configuration::get('BUCKAROO_PAYBYBANK_ISSUERS_CACHE_DATE'));
        self::assertFalse(\Configuration::get('BUCKAROO_ISSUERS_CACHE'));
        // Regression: the cache is read back from the keys it was written to.
        self::assertSame($issuers, $this->invokeIssuers('getCacheIssuers'));
        self::assertSame(date('Y-m-d'), $this->invokeIssuers('getCacheDate'));
    }

    /**
     * Regression: PayByBank used to read the generic BUCKAROO_ISSUERS_CACHE
     * keys while writing its own, so its cache never hit.
     */
    public function testPayByBankIgnoresTheGenericIssuerCache(): void
    {
        $this->cacheIssuers('BUCKAROO_ISSUERS_CACHE', 'BUCKAROO_ISSUERS_CACHE_DATE', [
            'BUNQNL2A' => ['name' => 'bunq', 'logo' => 'Bunq.svg'],
        ]);

        self::assertNull($this->invokeIssuers('getCacheIssuers'));
        self::assertFalse($this->invokeIssuers('getCacheDate'));
        $this->expectExceptionMessage('Buckaroo master settings not found.');
        $this->payByBank()->get();
    }

    public function testNonStringCacheIsIgnored(): void
    {
        \Configuration::updateValue('BUCKAROO_PAYBYBANK_ISSUERS_CACHE', ['not', 'json']);

        self::assertNull($this->invokeIssuers('getCacheIssuers'));
    }

    /**
     * Regression (PHP 8.2+): Issuers::$method is declared, so constructing an
     * issuer list raises no "Creation of dynamic property" deprecation.
     */
    public function testIssuersDeclaresTheMethodProperty(): void
    {
        [$payByBank, $errors] = ErrorCollector::run(function () {
            return new PayByBank();
        });

        self::assertSame([], $errors);
        self::assertTrue(property_exists(Issuers::class, 'method'));
        $property = new \ReflectionProperty(Issuers::class, 'method');
        if (PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }
        self::assertSame('paybybank', $property->getValue($payByBank));
    }

    private function payByBank($context = null): PayByBank
    {
        return new PayByBank($context);
    }

    private function cacheIssuers(string $key, string $dateKey, array $issuers): void
    {
        \Configuration::updateValue($key, json_encode($issuers));
        \Configuration::updateValue($dateKey, date('Y-m-d'));
    }

    /** Call a private method of the abstract Issuers class on a PayByBank instance. */
    private function invokeIssuers(string $method, array $args = [])
    {
        $reflection = new \ReflectionMethod(Issuers::class, $method);
        if (PHP_VERSION_ID < 80100) {
            $reflection->setAccessible(true);
        }

        return $reflection->invokeArgs($this->payByBank(), $args);
    }
}
