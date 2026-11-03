<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Tests\Unit\Reply;

use Buckaroo\PrestaShop\Src\Service\BuckarooIdinService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../api/paymentmethods/responsefactory.php';

/**
 * Records the bk_customer_idin calls instead of running them.
 */
class RecordingIdinService extends BuckarooIdinService
{
    /** @var array<int,array<int,mixed>> */
    public $calls = [];

    /** @var bool */
    public $exists = false;

    public function checkCustomerIdExists(int $customerId)
    {
        $this->calls[] = ['check', $customerId];

        return $this->exists;
    }

    public function updateCustomerData($customerId, $consumerbin, $iseighteenorolder)
    {
        $this->calls[] = ['update', $customerId, $consumerbin, $iseighteenorolder];

        return true;
    }

    public function insertCustomerData($customerId, $consumerbin, $iseighteenorolder)
    {
        $this->calls[] = ['insert', $customerId, $consumerbin, $iseighteenorolder];

        return true;
    }
}

/**
 * iDIN reply whose cart owners and gateway modes come from the test.
 */
class TestIdinReply extends \IdinResponse
{
    /** @var array<int,int> cart id => customer id */
    public static $cartCustomers = [];

    protected function getCartCustomerId(int $cartId): int
    {
        return self::$cartCustomers[$cartId] ?? 0;
    }

    protected function getStartedGatewayModes(string $invoice): array
    {
        return [];
    }
}

class IdinReplyTest extends TestCase
{
    private const WEBSITE_KEY = 'TESTWEBSITEKEY';
    private const SECRET_KEY = 'a-test-secret-key-not-a-real-one';

    /** @var RecordingIdinService */
    private $service;

    protected function setUp(): void
    {
        $_GET = [];
        $_POST = [];
        \Configuration::$values['BUCKAROO_MERCHANT_KEY'] = self::WEBSITE_KEY;
        \Configuration::$values['BUCKAROO_SECRET_KEY'] = self::SECRET_KEY;
        TestIdinReply::$cartCustomers = [77 => 5];
        $this->service = new RecordingIdinService();
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

    public function testFactoryStillPicksIdinResponseFromThePaymentMethod(): void
    {
        $_POST = $this->idinFields();

        $this->assertInstanceOf(\IdinResponse::class, \ResponseFactory::getResponse());
    }

    public function testBuildingTheResponseWritesNothing(): void
    {
        $_POST = $this->idinFields();

        new TestIdinReply(null, $this->service);

        $this->assertSame([], $this->service->calls);
    }

    public function testUnsignedReplyIsNotStored(): void
    {
        $_POST = $this->idinFields();

        $reply = new TestIdinReply(null, $this->service);

        $this->assertFalse($reply->storeCustomerVerification());
        $this->assertSame([], $this->service->calls);
    }

    public function testForgedSignatureIsNotStored(): void
    {
        $_POST = $this->idinFields() + ['brq_signature' => sha1('forged')];

        $reply = new TestIdinReply(null, $this->service);

        $this->assertFalse($reply->storeCustomerVerification());
        $this->assertSame([], $this->service->calls);
    }

    public function testSignedReplyIsStoredForTheCartsCustomer(): void
    {
        $_POST = $this->signed($this->idinFields());

        $reply = new TestIdinReply(null, $this->service);

        $this->assertTrue($reply->storeCustomerVerification());
        $this->assertSame([
            ['check', 5],
            ['insert', 5, 'BIN-123', 'True'],
        ], $this->service->calls);
    }

    public function testSignedReplyUpdatesAnExistingRow(): void
    {
        $this->service->exists = true;
        $_POST = $this->signed($this->idinFields());

        $this->assertTrue((new TestIdinReply(null, $this->service))->storeCustomerVerification());
        $this->assertSame(['update', 5, 'BIN-123', 'True'], $this->service->calls[1]);
    }

    public function testSignedReplyForAnotherCustomersCartIsNotStored(): void
    {
        $_POST = $this->signed($this->idinFields(['ADD_cid' => '6']));

        $this->assertFalse((new TestIdinReply(null, $this->service))->storeCustomerVerification());
        $this->assertSame([], $this->service->calls);
    }

    public function testSignedReplyWithoutCartIsNotStored(): void
    {
        $fields = $this->idinFields();
        unset($fields['ADD_cart']);
        $_POST = $this->signed($fields);

        $this->assertFalse((new TestIdinReply(null, $this->service))->storeCustomerVerification());
        $this->assertSame([], $this->service->calls);
    }

    public function testSignedReplyWithNonNumericCustomerIdIsNotStored(): void
    {
        TestIdinReply::$cartCustomers = [77 => 5];
        $_POST = $this->signed($this->idinFields(['ADD_cid' => '5 OR 1=1']));

        $this->assertFalse((new TestIdinReply(null, $this->service))->storeCustomerVerification());
        $this->assertSame([], $this->service->calls);
    }

    public function testSignedFailedVerificationIsNotStored(): void
    {
        $_POST = $this->signed($this->idinFields(['brq_statuscode' => '490']));

        $this->assertFalse((new TestIdinReply(null, $this->service))->storeCustomerVerification());
        $this->assertSame([], $this->service->calls);
    }

    public function testModuleConstructorDoesNotParseReplies(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../../buckaroo3.php');
        $this->assertSame(1, preg_match('/public function __construct\(\)\s*\{(.*?)\n    \}/s', $source, $m));

        $this->assertStringNotContainsString('ResponseFactory', $m[1]);
        $this->assertStringNotContainsString('getPaymentLabel', $m[1]);
    }

    /**
     * @param array<string,string> $overrides
     *
     * @return array<string,string>
     */
    private function idinFields(array $overrides = []): array
    {
        return array_merge([
            'brq_payment_method' => 'IDIN',
            'brq_statuscode' => '190',
            'brq_statusmessage' => 'Transaction successfully processed',
            'brq_test' => 'false',
            'brq_SERVICE_idin_ConsumerBIN' => 'BIN-123',
            'brq_SERVICE_idin_IsEighteenOrOlder' => 'True',
            'ADD_cid' => '5',
            'ADD_cart' => '77',
        ], $overrides);
    }

    /**
     * Same signing scheme as the SDK reply handler.
     *
     * @param array<string,string> $data
     *
     * @return array<string,string>
     */
    private function signed(array $data): array
    {
        $signed = array_filter($data, static function ($key) {
            return in_array(explode('_', strtolower((string) $key))[0], ['brq', 'add', 'cust'], true);
        }, ARRAY_FILTER_USE_KEY);

        uksort($signed, static function ($a, $b): int {
            return strcmp(strtolower((string) $a), strtolower((string) $b));
        });

        $signatureString = '';
        foreach ($signed as $key => $value) {
            $signatureString .= $key . '=' . html_entity_decode((string) $value);
        }

        $data['brq_signature'] = sha1($signatureString . self::SECRET_KEY);

        return $data;
    }
}
