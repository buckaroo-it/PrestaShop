<?php

namespace BuckarooTest\Unit\Api;

use Buckaroo\PrestaShop\Src\Service\BuckarooIdinService;
use Buckaroo\Transaction\Response\TransactionResponse;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;

require_once __DIR__ . '/ApiTestSupport.php';

/**
 * ResponseFactory picks the reply class; IdinResponse stores the iDIN result.
 */
class ResponseFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ApiFixtures::load();
        \Configuration::updateValue('BUCKAROO_MERCHANT_KEY', 'TESTWEBSITEKEY');
        \Configuration::updateValue('BUCKAROO_SECRET_KEY', 'a-test-secret-key-not-a-real-one');
    }

    /**
     * @dataProvider directReplies
     */
    public function testDirectReplyClass(array $data, string $class): void
    {
        $response = \ResponseFactory::getResponse(new TransactionResponse(null, $data));

        self::assertSame($class, get_class($response));
    }

    public function directReplies(): array
    {
        return [
            'idin service' => [['Services' => [['Name' => 'IDIN']]], \IdinResponse::class],
            'idin service code' => [['ServiceCode' => 'IDIN'], \IdinResponse::class],
            'ideal service' => [['Services' => [['Name' => 'ideal']], 'ServiceCode' => 'IDIN'], \ResponseDefault::class],
            'no service' => [[], \ResponseDefault::class],
        ];
    }

    /**
     * @dataProvider pushes
     */
    public function testPushReplyClass(array $request, string $class): void
    {
        $_POST = $request;

        self::assertSame($class, get_class(\ResponseFactory::getResponse()));
    }

    public function pushes(): array
    {
        return [
            'idin payment method' => [['brq_payment_method' => 'IDIN'], \IdinResponse::class],
            'idin primary service' => [['brq_primary_service' => 'IDIN'], \IdinResponse::class],
            'ideal' => [['brq_payment_method' => 'ideal', 'brq_primary_service' => 'IDIN'], \ResponseDefault::class],
            'nothing' => [[], \ResponseDefault::class],
        ];
    }

    public function testIdinResultIsInsertedForTheCustomerOwningTheCart(): void
    {
        Stubs::object('Cart', 42, ['id_customer' => 17]);
        Stubs::db('/COUNT\(\*\)/', '0');
        $_POST = $this->idinPush();

        $stored = (new \IdinResponse())->storeCustomerVerification();

        self::assertTrue($stored);
        $sql = Stubs::sql('execute');
        self::assertCount(1, $sql);
        self::assertStringContainsString('INSERT INTO ps_bk_customer_idin', $sql[0]);
        self::assertStringContainsString('VALUES (17, "BIN-123", "True")', $sql[0]);
    }

    public function testIdinResultUpdatesAKnownCustomer(): void
    {
        Stubs::object('Cart', 42, ['id_customer' => 17]);
        Stubs::db('/COUNT\(\*\)/', '1');
        $_POST = $this->idinPush();

        self::assertTrue((new \IdinResponse(null, new BuckarooIdinService()))->storeCustomerVerification());

        $sql = Stubs::sql('execute');
        self::assertCount(1, $sql);
        self::assertStringContainsString('UPDATE ps_bk_customer_idin SET buckaroo_idin_consumerbin="BIN-123"', $sql[0]);
        self::assertStringContainsString('WHERE customer_id=17', $sql[0]);
    }

    /**
     * @dataProvider rejectedIdinReplies
     */
    public function testIdinResultIsNotStored(array $fields, bool $sign): void
    {
        Stubs::object('Cart', 42, ['id_customer' => 17]);
        $_POST = $this->idinPush($fields, $sign);

        self::assertFalse((new \IdinResponse())->storeCustomerVerification());
        self::assertSame([], Stubs::sql('execute'));
    }

    public function rejectedIdinReplies(): array
    {
        return [
            'unsigned' => [[], false],
            'failed verification' => [['brq_statuscode' => '490'], true],
            'customer missing' => [['ADD_cid' => ''], true],
            'customer not a number' => [['ADD_cid' => '17abc'], true],
            'cart missing' => [['ADD_cart' => '0'], true],
            'consumer bin missing' => [['brq_SERVICE_idin_ConsumerBIN' => ''], true],
            'age missing' => [['brq_SERVICE_idin_IsEighteenOrOlder' => ''], true],
            'cart of another customer' => [['ADD_cid' => '18'], true],
            'unknown cart' => [['ADD_cart' => '43'], true],
        ];
    }

    public function testIntegerCustomerIdsAreAccepted(): void
    {
        $response = new \IdinResponse(new TransactionResponse(null, []), new BuckarooIdinService());

        self::assertSame(17, self::invoke($response, 'toPositiveInt', [17]));
        self::assertSame(0, self::invoke($response, 'toPositiveInt', [-3]));
        self::assertSame(0, self::invoke($response, 'toPositiveInt', [null]));
    }

    private function idinPush(array $fields = [], bool $sign = true): array
    {
        $data = array_merge([
            'ADD_cart' => '42',
            'ADD_cid' => '17',
            'brq_invoicenumber' => 'IDIN_42',
            'brq_primary_service' => 'IDIN',
            'brq_SERVICE_idin_ConsumerBIN' => 'BIN-123',
            'brq_SERVICE_idin_IsEighteenOrOlder' => 'True',
            'brq_statuscode' => '190',
            'brq_statusmessage' => 'Transaction successfully processed',
            'brq_test' => 'true',
        ], $fields);
        $data['brq_signature'] = $sign ? PushSigner::sign($data) : 'invalid';

        return $data;
    }
}
