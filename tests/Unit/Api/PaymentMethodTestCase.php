<?php

namespace BuckarooTest\Unit\Api;

use BuckarooTest\TestCase;

require_once __DIR__ . '/ApiTestSupport.php';

/**
 * Builds payment request objects whose Buckaroo client is a RecordingBuckarooClient.
 */
abstract class PaymentMethodTestCase extends TestCase
{
    /** @var RecordingBuckarooClient */
    protected $client;

    protected function setUp(): void
    {
        parent::setUp();
        ApiFixtures::load();
        $this->client = new RecordingBuckarooClient(null, ApiFixtures::reply());
    }

    /**
     * Instantiate $class (running its constructor) with getBuckarooClient() answering the recording client.
     */
    protected function paymentMethod(string $class, array $constructorArgs = [], bool $fill = true): \PaymentMethod
    {
        $client = $this->client;
        $method = $this->getMockBuilder($class)
            ->setConstructorArgs($constructorArgs)
            ->onlyMethods(['getBuckarooClient'])
            ->getMock();
        $method->method('getBuckarooClient')->willReturnCallback(function ($mode) use ($client) {
            $client->mode = $mode;

            return $client;
        });

        if ($fill) {
            $this->fill($method);
        }

        return $method;
    }

    /** The request fields Checkout::startPayment() sets on every payment method. */
    protected function fill(\PaymentMethod $method): void
    {
        $method->currency = 'EUR';
        $method->amountDebit = 10.5;
        $method->invoiceId = 'XKBKNABJK_42';
        $method->orderId = 'XKBKNABJK';
        $method->description = 'Order XKBKNABJK';
        $method->returnUrl = 'https://shop.test/return';
        $method->pushUrl = 'https://shop.test/push';
        $method->platformName = 'PrestaShop';
        $method->platformVersion = '9.0.1';
        $method->moduleVersion = '4.0.0';
        $method->moduleSupplier = 'Buckaroo';
        $method->moduleName = 'buckaroo3';
        $method->mode = 'test';
    }

    /** @return array<string, mixed> the base payload payGlobal() sends for fill() */
    protected static function basePayload(): array
    {
        return [
            'currency' => 'EUR',
            'amountDebit' => 10.5,
            'invoice' => 'XKBKNABJK_42',
            'description' => 'Order XKBKNABJK',
            'order' => 'XKBKNABJK',
            'returnURL' => 'https://shop.test/return',
            'pushURL' => 'https://shop.test/push',
            'platformName' => 'PrestaShop',
            'platformVersion' => '9.0.1',
            'moduleVersion' => '4.0.0',
            'moduleSupplier' => 'Buckaroo',
            'moduleName' => 'buckaroo3',
        ];
    }
}
