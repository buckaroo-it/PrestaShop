<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Tests\Unit\Reply;

use Buckaroo\Transaction\Response\TransactionResponse;
use PHPUnit\Framework\TestCase;

/*
 * The legacy payment classes load the vendor autoloader through
 * _PS_ROOT_DIR_ . '/modules/buckaroo3/vendor/autoload.php'; tests/bootstrap.php
 * defines _PS_ROOT_DIR_ and writes a shim at that path before any test file loads.
 */
require_once __DIR__ . '/../../../api/paymentmethods/paybybank/paybybank.php';

/**
 * PayByBank double that records the gateway it would call instead of calling it.
 */
class GatewayModePayByBank extends \PayByBank
{
    /** @var string|null */
    public $clientMode;

    /** @var string|null */
    public $sdkMethod;

    /** @var array<int,array{0:string,1:string}> */
    public $recordedModes = [];

    public function getBuckarooClient($mode)
    {
        $this->clientMode = $mode;
        $request = $this;

        return new class($request) {
            private $request;

            public function __construct($request)
            {
                $this->request = $request;
            }

            public function method($type)
            {
                $this->request->sdkMethod = $type;

                return new class {
                    public function pay($payload)
                    {
                        return new TransactionResponse(null, [
                            'Status' => ['Code' => ['Code' => 791]],
                            'IsTest' => false,
                        ]);
                    }
                };
            }
        };
    }

    protected function recordGatewayMode(string $invoice, string $mode): void
    {
        $this->recordedModes[] = [$invoice, $mode];
    }
}

/**
 * Reply whose recorded gateway modes come from the test instead of the database.
 */
class GatewayModeReply extends \ResponseDefault
{
    /** @var string[] */
    public static $startedModes = [];

    protected function getStartedGatewayModes(string $invoice): array
    {
        return self::$startedModes;
    }
}

class GatewayModeTest extends TestCase
{
    private const WEBSITE_KEY = 'TESTWEBSITEKEY';
    private const SECRET_KEY = 'a-test-secret-key-not-a-real-one';

    protected function setUp(): void
    {
        $_GET = [];
        $_POST = [];
        \Configuration::$values['BUCKAROO_MERCHANT_KEY'] = self::WEBSITE_KEY;
        \Configuration::$values['BUCKAROO_SECRET_KEY'] = self::SECRET_KEY;
        GatewayModeReply::$startedModes = [];
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        unset(
            \Configuration::$values['BUCKAROO_MERCHANT_KEY'],
            \Configuration::$values['BUCKAROO_SECRET_KEY']
        );
    }

    public function testPayByBankSentAsIdealUsesPayByBanksMode(): void
    {
        $request = new GatewayModePayByBank();
        $request->setContext($this->mobileContext());
        $request->issuer = 'INGBNL2A';
        $request->invoiceId = 'XKBKNABJK_42';
        // Set by Checkout::getInstance() from the method validated in request.php.
        $request->mode = 'live';

        $request->pay();

        $this->assertSame('ideal', $request->sdkMethod);
        $this->assertSame('live', $request->clientMode);
        $this->assertSame([['XKBKNABJK_42', 'live']], $request->recordedModes);
    }

    public function testSignedTestReplyForALiveOrderIsRejected(): void
    {
        GatewayModeReply::$startedModes = ['live'];
        $_POST = $this->signedPush(['brq_test' => 'true']);

        $reply = new GatewayModeReply();

        $this->assertTrue($reply->isTest());
        $this->assertFalse($reply->isValid());
        $this->assertFalse($reply->isPaid());
    }

    public function testSignedLiveReplyForALiveOrderIsAccepted(): void
    {
        GatewayModeReply::$startedModes = ['live'];
        $_POST = $this->signedPush(['brq_test' => 'false']);

        $reply = new GatewayModeReply();

        $this->assertFalse($reply->isTest());
        $this->assertTrue($reply->isValid());
        $this->assertTrue($reply->isPaid());
    }

    public function testSignedTestReplyForATestOrderIsAccepted(): void
    {
        GatewayModeReply::$startedModes = ['test'];
        $_POST = $this->signedPush(['brq_test' => 'true']);

        $this->assertTrue((new GatewayModeReply())->isValid());
    }

    public function testIsTestReadsIsTestFromTheResponseData(): void
    {
        $test = new \ResponseDefault(new TransactionResponse(null, ['IsTest' => true]));
        $live = new \ResponseDefault(new TransactionResponse(null, ['IsTest' => false]));

        $this->assertTrue($test->isTest());
        $this->assertFalse($live->isTest());
    }

    public function testDirectResponseFromTheOtherGatewayIsRejected(): void
    {
        $response = new \ResponseDefault(new TransactionResponse(null, [
            'Status' => ['Code' => ['Code' => 190]],
            'IsTest' => true,
        ]));
        $response->setGatewayMode('live');

        $this->assertFalse($response->isValid());

        $response->setGatewayMode('test');

        $this->assertTrue($response->isValid());
    }

    private function mobileContext(): \Context
    {
        $context = new class extends \Context {
            public function isMobile()
            {
                return true;
            }
        };
        $context->cookie = new class {
            public function __set($name, $value)
            {
            }
        };

        return $context;
    }

    /**
     * @param array<string,string> $fields
     *
     * @return array<string,string>
     */
    private function signedPush(array $fields): array
    {
        $data = array_merge([
            'brq_amount' => '10.00',
            'brq_currency' => 'EUR',
            'brq_invoicenumber' => 'XKBKNABJK_42',
            'brq_payment_method' => 'ideal',
            'brq_statuscode' => '190',
            'brq_statusmessage' => 'Transaction successfully processed',
            'brq_transactions' => 'F6E5D4C3B2A1F6E5D4C3B2A1F6E5D4C3',
        ], $fields);
        $data['brq_signature'] = $this->sign($data);

        return $data;
    }

    /**
     * Same signing scheme as the SDK reply handler.
     *
     * @param array<string,string> $data
     */
    private function sign(array $data): string
    {
        $data = array_filter($data, static function ($key) {
            $key = strtolower((string) $key);

            return $key !== 'brq_signature'
                && in_array(explode('_', $key)[0], ['brq', 'add', 'cust'], true);
        }, ARRAY_FILTER_USE_KEY);

        uksort($data, static function ($a, $b): int {
            return strcmp(strtolower((string) $a), strtolower((string) $b));
        });

        $signatureString = '';
        foreach ($data as $key => $value) {
            $signatureString .= $key . '=' . html_entity_decode((string) $value);
        }

        return sha1($signatureString . trim(self::SECRET_KEY));
    }
}
