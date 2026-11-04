<?php

namespace BuckarooTest\Unit\RefundAdmin;

use Buckaroo\Exceptions\BuckarooException;
use Buckaroo\PrestaShop\Src\Refund\Request\Handler;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;

require_once __DIR__ . '/StubsSupport.php';

/**
 * The request handler builds a real BuckarooClient. Every case below stops
 * before an HTTP request is made: either the credentials are missing (the SDK
 * refuses to build a client) or the method code is unknown to the SDK.
 */
class RefundRequestHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Stubs::db('/bk_giftcards/', [['code' => 'mycustomcard']]);
        Stubs::db('/FROM `ps_bk_payment_methods`/', function ($sql) {
            return preg_match('/name = "([^"]+)"/', $sql, $m) ? 'id-' . $m[1] : false;
        });
        Stubs::db('/FROM `ps_bk_configuration`/', '{"mode":"test"}');
    }

    public function testMissingCredentialsStopBeforeAnyRequest(): void
    {
        try {
            (new Handler())->refund(['amountCredit' => 1.0], 'iDEAL');
            self::fail('A client without credentials must not be built');
        } catch (\Throwable $e) {
            self::assertNotInstanceOf(\PHPUnit\Framework\AssertionFailedError::class, $e);
        }
        self::assertStringContainsString('name = "ideal"', implode("\n", Stubs::sql()));
    }

    public function unknownMethodProvider(): array
    {
        // 'cartebleue' is a card brand for the plugin (config lookup uses 'creditcard')
        // but no SDK method, so the SDK rejects it before sending anything.
        return [
            'credit card brand without SDK mapping' => ['cartebleue', 'creditcard'],
            'unknown method (lower-cased, not compacted)' => [' Not A Method ', 'not a method'],
        ];
    }

    /**
     * @dataProvider unknownMethodProvider
     */
    public function testConfigLookupUsesTheNormalisedMethodCode(string $method, string $configMethod): void
    {
        \Configuration::updateValue('BUCKAROO_MERCHANT_KEY', 'website');
        \Configuration::updateValue('BUCKAROO_SECRET_KEY', 'secret');

        try {
            (new Handler())->refund(['amountCredit' => 1.0], $method);
            self::fail('The SDK should reject the method');
        } catch (BuckarooException $e) {
            self::assertStringContainsString('Wrong payment method code', $e->getMessage());
        }

        $lookups = implode("\n", Stubs::sql());
        self::assertStringContainsString('name = "' . $configMethod . '"', $lookups);
    }
}
