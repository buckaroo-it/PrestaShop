<?php

declare(strict_types=1);

namespace BuckarooTest\Unit\Services;

require_once __DIR__ . '/StubsSupport.php';

use Buckaroo\PrestaShop\Src\Service\BuckarooClickToPayService;
use Buckaroo\PrestaShop\Src\Service\BuckarooConfigService;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;
use Doctrine\ORM\EntityManager;

final class BuckarooClickToPayServiceTest extends TestCase
{
    private const CACHE_KEY = 'BUCKAROO_CLICKTOPAY_TOKEN';

    protected function setUp(): void
    {
        parent::setUp();
        FakeCurl::reset();
    }

    protected function tearDown(): void
    {
        FakeCurl::reset();
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $config Click to Pay config values
     * @param mixed $context
     */
    private function service(array $config, $context = null): BuckarooClickToPayService
    {
        $configService = new class(new EntityManager(), $config) extends BuckarooConfigService {
            /** @var array<string, mixed> */
            private $values;

            public function __construct(EntityManager $entityManager, array $values)
            {
                parent::__construct($entityManager);
                $this->values = $values;
            }

            public function getConfigValue($method, $key)
            {
                return $method === BuckarooClickToPayService::METHOD_NAME ? ($this->values[$key] ?? null) : null;
            }
        };

        return new BuckarooClickToPayService($configService, $context);
    }

    private function configured(array $overrides = []): array
    {
        return array_merge([
            'client_id' => 'client',
            'client_secret' => 'secret',
            'merchant_identifier' => 'merchant-guid',
        ], $overrides);
    }

    private static function credentialHash(string $id = 'client', string $secret = 'secret'): string
    {
        return hash('sha256', $id . ':' . $secret . ':clicktopay:save');
    }

    public function testSdkScriptUrlIsTheBuckarooCheckoutScript(): void
    {
        self::assertSame(
            'https://checkout.buckaroo.nl/api/buckaroosdk/script',
            $this->service([])->getSdkScriptUrl()
        );
    }

    /**
     * @dataProvider configurationProvider
     */
    public function testIsConfiguredNeedsAllThreeSettings(array $config, bool $expected): void
    {
        self::assertSame($expected, $this->service($config)->isConfigured());
    }

    public function configurationProvider(): array
    {
        return [
            'complete' => [['client_id' => 'a', 'client_secret' => 'b', 'merchant_identifier' => 'c'], true],
            'values are trimmed' => [['client_id' => ' a ', 'client_secret' => 'b', 'merchant_identifier' => ' c'], true],
            'missing client id' => [['client_secret' => 'b', 'merchant_identifier' => 'c'], false],
            'blank secret' => [['client_id' => 'a', 'client_secret' => '   ', 'merchant_identifier' => 'c'], false],
            'missing merchant' => [['client_id' => 'a', 'client_secret' => 'b'], false],
            'nothing' => [[], false],
        ];
    }

    public function testCheckoutConfigUsesInvoiceAddressCurrencyAndFee(): void
    {
        $context = $this->context();
        $context->shop = new class {
            public function getBaseURL($ssl = false)
            {
                return 'https://shop.example:8443/nl/';
            }
        };
        $context->language->locale = 'de-DE';
        \Country::$isoById[21] = 'be';
        \Currency::$isoById[2] = 'usd';
        Stubs::object('Address', 7, ['id_country' => 21]);

        $module = new \Module();
        $module->stub('getBuckarooFee', function ($method, $total) {
            return $method === 'clicktopay' ? ['buckaroo_fee_tax_incl' => '1.25'] : [];
        });
        \Module::$instances['buckaroo3'] = $module;

        $cart = new \Cart();
        $cart->id_address_invoice = 7;
        $cart->id_address_delivery = 99;
        $cart->id_currency = 2;
        $cart->totals[\Cart::BOTH] = 10.5;

        $config = $this->service($this->configured(), $context)->getCheckoutConfig($cart);

        self::assertSame([
            'merchantIdentifier' => 'merchant-guid',
            'targetOrigins' => ['https://shop.example:8443'],
            'country' => 'BE',
            'locale' => 'de_DE',
            'currency' => 'USD',
            'totalAmount' => '11.75',
        ], $config);
        self::assertSame([['clicktopay', 10.5]], Stubs::callsTo('Module->getBuckarooFee'));
    }

    public function testCheckoutConfigFallsBackToContextCountryAndLanguageIso(): void
    {
        $context = $this->context();
        $context->country->iso_code = 'fr';
        $context->language->iso_code = 'fr';
        Stubs::object('Address', 8, ['id_country' => 55]); // country without ISO code

        $module = new \Module();
        $module->stub('getBuckarooFee', 'not-an-array');
        \Module::$instances['buckaroo3'] = $module;

        $cart = new \Cart();
        $cart->id_address_invoice = 0;
        $cart->id_address_delivery = 8;
        $cart->id_currency = 0;
        $cart->totals[\Cart::BOTH] = 5;

        $config = $this->service($this->configured(), $context)->getCheckoutConfig($cart);

        self::assertSame(['https://shop.test'], $config['targetOrigins']);
        self::assertSame('FR', $config['country']);
        self::assertSame('fr_FR', $config['locale']);
        self::assertSame('', $config['currency'], 'an unloaded currency has no ISO code');
        self::assertSame('5.00', $config['totalAmount']);
    }

    public function testCheckoutConfigWithoutAnyContextUsesDutchDefaults(): void
    {
        // No context injected and no module registered: nothing to resolve.
        $cart = new \Cart();
        $cart->id_address_invoice = 404; // not a loaded address
        $cart->totals[\Cart::BOTH] = 3.333;

        $config = $this->service($this->configured(), null)->getCheckoutConfig($cart);

        self::assertSame([''], $config['targetOrigins']);
        self::assertSame('NL', $config['country']);
        self::assertSame('nl_NL', $config['locale']);
        self::assertSame('EUR', $config['currency']);
        self::assertSame('3.33', $config['totalAmount'], 'no module means no fee');
    }

    public function testShopOriginKeepsAnUnparsableBaseUrlAsIs(): void
    {
        $context = $this->context();
        $context->shop = new class {
            public function getBaseURL($ssl = false)
            {
                return '/relative/shop/';
            }
        };

        $config = $this->service($this->configured(), $context)->getCheckoutConfig(new \Cart());

        self::assertSame(['/relative/shop'], $config['targetOrigins']);
    }

    public function testAccessTokenNeedsCredentials(): void
    {
        self::assertNull($this->service(['client_id' => 'client'])->getAccessToken());
        self::assertNull($this->service(['client_secret' => 'secret'])->getAccessToken());
        self::assertSame([], FakeCurl::$calls, 'no request without credentials');
    }

    public function testValidCachedTokenIsReusedWithoutARequest(): void
    {
        \Configuration::$globalValues[self::CACHE_KEY] = json_encode([
            'hash' => self::credentialHash(),
            'access_token' => 'cached-token',
            'expires_at' => time() + 300,
        ]);

        $token = $this->service($this->configured())->getAccessToken();

        self::assertSame('cached-token', $token['access_token']);
        self::assertGreaterThan(295, $token['expires_in']);
        self::assertLessThanOrEqual(300, $token['expires_in']);
        self::assertSame([], FakeCurl::$calls);
    }

    /**
     * @dataProvider unusableCacheProvider
     */
    public function testUnusableCacheTriggersANewTokenRequest($cached): void
    {
        \Configuration::$globalValues[self::CACHE_KEY] = is_array($cached) ? json_encode($cached) : $cached;
        FakeCurl::$enabled = true;
        FakeCurl::$body = json_encode(['access_token' => 'fresh', 'expires_in' => 3600]);

        $token = $this->service($this->configured())->getAccessToken();

        self::assertSame(['access_token' => 'fresh', 'expires_in' => 3540], $token);
        self::assertCount(1, FakeCurl::callsTo('curl_exec'));
    }

    public function unusableCacheProvider(): array
    {
        return [
            'nothing cached' => [false],
            'not json' => ['garbage'],
            'other credentials' => [['hash' => 'other', 'access_token' => 'x', 'expires_at' => time() + 600]],
            'no token' => [['hash' => self::credentialHash(), 'access_token' => '', 'expires_at' => time() + 600]],
            'expired' => [['hash' => self::credentialHash(), 'access_token' => 'old', 'expires_at' => time() - 1]],
            'no expiry' => [['hash' => self::credentialHash(), 'access_token' => 'old']],
        ];
    }

    public function testTokenRequestSendsClientCredentialsAndCachesTheToken(): void
    {
        FakeCurl::$enabled = true;
        FakeCurl::$body = json_encode(['access_token' => 'abc', 'expires_in' => '120']);

        $before = time();
        $token = $this->service($this->configured(['client_id' => ' client ']))->getAccessToken();

        self::assertSame(['access_token' => 'abc', 'expires_in' => 60], $token);

        self::assertSame([['https://auth.buckaroo.io/oauth/token']], FakeCurl::callsTo('curl_init'));
        $options = FakeCurl::callsTo('curl_setopt_array')[0][1];
        self::assertTrue($options[CURLOPT_POST]);
        self::assertTrue($options[CURLOPT_RETURNTRANSFER]);
        self::assertSame(10, $options[CURLOPT_TIMEOUT]);
        self::assertSame(10, $options[CURLOPT_CONNECTTIMEOUT]);
        self::assertSame(CURLAUTH_BASIC, $options[CURLOPT_HTTPAUTH]);
        self::assertSame('client:secret', $options[CURLOPT_USERPWD]);
        self::assertSame('grant_type=client_credentials&scope=clicktopay%3Asave', $options[CURLOPT_POSTFIELDS]);
        // curl_close() is deprecated in PHP 8.5 and a no-op since 8.0: only called on PHP 7.
        self::assertCount(PHP_VERSION_ID < 80000 ? 1 : 0, FakeCurl::callsTo('curl_close'));

        $cached = json_decode((string) \Configuration::$globalValues[self::CACHE_KEY], true);
        self::assertSame(self::credentialHash(), $cached['hash']);
        self::assertSame('abc', $cached['access_token']);
        self::assertGreaterThanOrEqual($before + 60, $cached['expires_at']);
        self::assertLessThanOrEqual(time() + 60, $cached['expires_at']);
    }

    public function testShortLivedTokenIsReturnedButNotCached(): void
    {
        FakeCurl::$enabled = true;
        FakeCurl::$body = json_encode(['access_token' => 'brief']);

        $token = $this->service($this->configured())->getAccessToken();

        self::assertSame(['access_token' => 'brief', 'expires_in' => 0], $token);
        self::assertArrayNotHasKey(self::CACHE_KEY, \Configuration::$globalValues);
    }

    public function testHandleThatCannotBeCreatedYieldsNoToken(): void
    {
        FakeCurl::$enabled = false;

        self::assertNull($this->service($this->configured())->getAccessToken());
        self::assertSame([], FakeCurl::callsTo('curl_exec'));
        self::assertSame(0, Stubs::callCount('PrestaShopLogger::addLog'));
    }

    /**
     * @dataProvider failedResponseProvider
     */
    public function testFailedTokenResponsesAreLogged($body, string $error, string $expectedLog): void
    {
        FakeCurl::$enabled = true;
        FakeCurl::$body = $body;
        FakeCurl::$error = $error;

        self::assertNull($this->service($this->configured())->getAccessToken());

        self::assertSame([['Buckaroo: ' . $expectedLog, 3]], Stubs::callsTo('PrestaShopLogger::addLog'));
        self::assertArrayNotHasKey(self::CACHE_KEY, \Configuration::$globalValues);
    }

    public function failedResponseProvider(): array
    {
        return [
            'transport error' => [false, 'Could not resolve host', 'Click to Pay token request failed: Could not resolve host'],
            'empty body' => ['', '', 'Click to Pay token request failed: empty response'],
            'not json' => ['<html>', '', 'Click to Pay token request returned an unexpected response.'],
            'no token' => ['{"error":"invalid_client"}', '', 'Click to Pay token request returned an unexpected response.'],
        ];
    }
}
