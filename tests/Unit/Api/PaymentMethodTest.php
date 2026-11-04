<?php

namespace BuckarooTest\Unit\Api;

use BuckarooTest\Stubs;

require_once __DIR__ . '/PaymentMethodTestCase.php';

/**
 * The shared request flow of api/paymentmethods/paymentmethod.php.
 */
class PaymentMethodTest extends PaymentMethodTestCase
{
    public function testPaySendsTheBasePayloadToTheSdkPayAction(): void
    {
        $method = $this->paymentMethod(\Alipay::class);

        $response = $method->pay();

        $call = $this->client->lastCall();
        self::assertSame('alipay', $call['type']);
        self::assertSame('pay', $call['action']);
        self::assertSame(self::basePayload(), $call['payload']);
        self::assertSame('test', $this->client->mode);
        self::assertInstanceOf(\ResponseDefault::class, $response);
        self::assertTrue($response->isSuccess());
    }

    public function testPayRecordsTheGatewayModeOfTheInvoice(): void
    {
        $method = $this->paymentMethod(\Alipay::class);
        $method->mode = 'live';

        $method->pay();

        $inserts = Stubs::$queries;
        self::assertCount(1, $inserts);
        self::assertSame('insert', $inserts[0]['method']);
        self::assertSame('bk_transaction_mode', $inserts[0]['args'][0]);
        self::assertSame('XKBKNABJK_42', $inserts[0]['args'][1]['invoice']);
        self::assertSame('live', $inserts[0]['args'][1]['mode']);
        self::assertSame(\Db::INSERT_IGNORE, $inserts[0]['args'][2]);
        self::assertSame('live', $this->client->mode);
    }

    public function testPayWithoutAnInvoiceRecordsNoGatewayMode(): void
    {
        $method = $this->paymentMethod(\Alipay::class);
        $method->invoiceId = null;

        $method->pay();

        self::assertSame([], Stubs::sql('insert'));
        self::assertSame('pay', $this->client->lastCall()['action']);
    }

    /**
     * @dataProvider configuredModes
     */
    public function testPayFallsBackToTheConfiguredModeOfTheMethod(string $stored, string $expected): void
    {
        ApiFixtures::gatewayMode($stored);
        $method = $this->paymentMethod(\Alipay::class);
        $method->mode = null;

        $method->pay();

        self::assertSame($expected, $this->client->mode);
        self::assertStringContainsString('name = "alipay"', Stubs::sql('getValue')[0]);
    }

    public function configuredModes(): array
    {
        return [
            'live' => ['live', 'live'],
            'test' => ['test', 'test'],
            'anything else is test' => ['off', 'test'],
        ];
    }

    public function testPayFailsWhenTheMethodHasNoConfiguredMode(): void
    {
        $method = $this->paymentMethod(\Alipay::class);
        $method->mode = '';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Configuration not found');

        $method->pay();
    }

    public function testPayAfterAPartialGiftcardPaymentUsesPayRemainder(): void
    {
        $method = $this->paymentMethod(\Alipay::class);
        $method->OriginalTransactionKey = 'GROUPKEY123';

        $method->pay();

        $call = $this->client->lastCall();
        self::assertSame('payRemainder', $call['action']);
        self::assertSame('GROUPKEY123', $call['payload']['originalTransactionKey']);
    }

    public function testPayGlobalTurnsPayIntoPayRemainderWithAnOriginalTransactionKey(): void
    {
        $method = $this->paymentMethod(\Alipay::class);
        $method->OriginalTransactionKey = 'GROUPKEY123';

        $method->payGlobal('pay');

        self::assertSame('payRemainder', $this->client->lastCall()['action']);
    }

    public function testExecuteCustomPayActionCallsThatSdkAction(): void
    {
        $method = $this->paymentMethod(\Alipay::class);

        $method->executeCustomPayAction('payRedirect');

        $call = $this->client->lastCall();
        self::assertSame('payRedirect', $call['action']);
        self::assertSame(self::basePayload(), $call['payload']);
    }

    public function testResponseRemembersTheGatewayModeOfTheRequest(): void
    {
        // Test gateway reply while the request was sent to live: rejected.
        $this->client->reply = ApiFixtures::reply(190, ['IsTest' => true]);
        $method = $this->paymentMethod(\Alipay::class);
        $method->mode = 'live';

        $response = $method->pay();

        self::assertFalse($response->isValid());
        self::assertFalse($response->isPaid());
    }

    public function testResponseFromTheRequestedGatewayIsValid(): void
    {
        $method = $this->paymentMethod(\Alipay::class);

        $response = $method->pay();

        self::assertTrue($response->isValid());
        self::assertTrue($response->isPaid());
        self::assertSame(190, $response->getStatusCodeValue());
    }

    public function testGetBuckarooClientCreatesAnSdkClientWithTheShopCredentials(): void
    {
        \Configuration::updateValue('BUCKAROO_MERCHANT_KEY', 'WEBSITEKEY');
        \Configuration::updateValue('BUCKAROO_SECRET_KEY', 'SECRET');

        $client = (new \Alipay())->getBuckarooClient('live');

        self::assertInstanceOf(\Buckaroo\BuckarooClient::class, $client);
        $config = $client->client()->config();
        self::assertSame('WEBSITEKEY', $config->websiteKey());
        self::assertSame('SECRET', $config->secretKey());
        self::assertTrue($config->isLiveMode());
    }

    public function testRefundSendsTheRefundPayloadToTheConfiguredGateway(): void
    {
        ApiFixtures::gatewayMode('live');
        $method = $this->paymentMethod(\Refunds::class, ['ideal']);
        $method->amountDebit = 0;
        $method->amountCredit = 4.25;
        $method->OriginalTransactionKey = 'ORIGINALKEY';

        $response = $method->refund();

        $call = $this->client->lastCall();
        self::assertSame('ideal', $call['type']);
        self::assertSame('refund', $call['action']);
        self::assertSame('live', $this->client->mode);
        self::assertSame(4.25, $call['payload']['amountCredit']);
        self::assertSame(0, $call['payload']['amountDebit']);
        self::assertSame('ORIGINALKEY', $call['payload']['originalTransactionKey']);
        self::assertSame('XKBKNABJK_42', $call['payload']['invoice']);
        self::assertSame('XKBKNABJK', $call['payload']['order']);
        self::assertSame('EUR', $call['payload']['currency']);
        self::assertSame('Order XKBKNABJK', $call['payload']['description']);
        self::assertSame(['action' => 'Refund', 'version' => 2], $call['payload']['services']['ideal']);
        self::assertArrayNotHasKey('articles', $call['payload']);
        self::assertInstanceOf(\ResponseDefault::class, $response);
    }

    /**
     * @dataProvider articleRefundTypes
     */
    public function testAfterpayAndBillinkRefundsSendOneReturnArticle(string $type, string $class): void
    {
        ApiFixtures::gatewayMode('test');
        $this->request([], ['refund_amount' => '12.345']);
        $method = $this->paymentMethod($class);
        $method->amountCredit = 99;

        $method->refund();

        $payload = $this->client->lastCall()['payload'];
        self::assertSame($type, $this->client->lastCall()['type']);
        self::assertSame([[
            'refundType' => 'Return',
            'identifier' => 1,
            'description' => 'Refund',
            'quantity' => 1,
            // Rounded half up, not truncated to 12.34.
            'price' => '12.35',
            'vatPercentage' => 0,
        ]], $payload['articles']);
        // The credited amount itself is still the property, not the request value.
        self::assertSame(99, $payload['amountCredit']);
    }

    public function articleRefundTypes(): array
    {
        return [
            'afterpay' => ['afterpay', \AfterPay::class],
            'billink' => ['billink', \Billink::class],
        ];
    }

    public function testAfterpayRefundArticleFallsBackToTheCreditedAmount(): void
    {
        ApiFixtures::gatewayMode('test');
        $method = $this->paymentMethod(\Refunds::class, ['afterpay']);
        $method->amountCredit = 7.5;

        $method->refund();

        self::assertSame('7.50', $this->client->lastCall()['payload']['articles'][0]['price']);
    }

    /**
     * Regression: the refund article price was truncated (12.345 -> 12.34).
     *
     * @dataProvider roundedRefundPrices
     */
    public function testRefundArticlePriceIsRoundedHalfUp(string $amount, string $price): void
    {
        ApiFixtures::gatewayMode('test');
        $this->request([], ['refund_amount' => $amount]);
        $method = $this->paymentMethod(\Billink::class);

        $method->refund();

        self::assertSame($price, $this->client->lastCall()['payload']['articles'][0]['price']);
    }

    public function roundedRefundPrices(): array
    {
        return [
            'half up' => ['12.345', '12.35'],
            'below half' => ['12.344', '12.34'],
            'carry' => ['9.995', '10.00'],
            'exact' => ['5', '5.00'],
        ];
    }

    public function testVerifySendsAnIdinVerifyRequest(): void
    {
        ApiFixtures::gatewayMode('test');
        $method = $this->paymentMethod(\Alipay::class);

        $method->verify();

        $call = $this->client->lastCall();
        self::assertSame('idin', $call['type']);
        self::assertSame('verify', $call['action']);
        self::assertSame([
            'returnURL' => 'https://shop.test/return',
            'pushURL' => 'https://shop.test/push',
            'platformName' => 'PrestaShop',
            'platformVersion' => '9.0.1',
            'moduleVersion' => '4.0.0',
            'moduleSupplier' => 'Buckaroo',
            'moduleName' => 'buckaroo3',
        ], $call['payload']);
    }

    /**
     * @dataProvider descriptions
     */
    public function testSetDescriptionReplacesThePlaceholders(string $label, string $expected): void
    {
        \Configuration::updateValue('BUCKAROO_TRANSACTION_LABEL', $label);
        $order = new \Order();
        $order->reference = 'XKBKNABJK';
        $order->id_customer = 17;
        Stubs::returns('Order::getByCartId', function ($cartId) use ($order) {
            return $cartId === 42 ? $order : null;
        });
        $this->context()->shop->name = 'Fashion Store';
        ApiFixtures::registerModule();

        $method = new \Alipay();
        $method->setDescription(42);

        self::assertSame($expected, $method->description);
    }

    public function descriptions(): array
    {
        return [
            'order number and shop' => ['Order {order_number} at {shop_name}', 'Order XKBKNABJK at Fashion Store'],
            'order property' => ['Customer {id_customer} order {order_number}', 'Customer 17 order XKBKNABJK'],
            'unknown placeholder is removed' => ['Pay {unknown} {order_number}', 'Pay  XKBKNABJK'],
            'no placeholder' => ['Thank you', 'Thank you'],
        ];
    }

    public function testSetDescriptionWithoutALabelIsEmpty(): void
    {
        $method = new \Alipay();
        $method->setDescription(42);

        self::assertSame('', $method->description);
        self::assertSame(0, Stubs::callCount('Order::getByCartId'));
    }

    public function testSetDescriptionWithoutAContextUsesAnEmptyShopName(): void
    {
        \Configuration::updateValue('BUCKAROO_TRANSACTION_LABEL', '{shop_name}#{order_number}');
        $order = new \Order();
        $order->reference = 'REF';
        Stubs::returns('Order::getByCartId', $order);
        // No buckaroo3 module registered: no context to read the shop name from.

        $method = new \Alipay();
        $method->setDescription(1);

        self::assertSame('#REF', $method->description);
    }
}
