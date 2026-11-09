<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Tests\Unit\PayPal;

use Buckaroo\BuckarooClient;
use Buckaroo\PrestaShop\Src\PayPal\ExpressPayment;
use Buckaroo\Services\PayloadService;
use PHPUnit\Framework\TestCase;

/**
 * The express payment request is built, not sent (manual mode).
 */
final class ExpressPaymentTest extends TestCase
{
    private const BASE_PAYLOAD = [
        'currency' => 'EUR',
        'amountDebit' => 31.61,
        'invoice' => 'ABCDEF_12',
        'order' => 'ABCDEF_12',
        'returnURL' => 'https://shop.test/return',
        'pushURL' => 'https://shop.test/push',
        'payPalOrderId' => '5O190127TN364715T',
    ];

    public function testWithSellerProtectionTheOrderAndTheSellerDataGoInOnePayExtraInfoService(): void
    {
        $service = $this->buildService(self::BASE_PAYLOAD + [
            'customer' => ['name' => 'Jane Tester'],
            'address' => ['street' => 'Hoofdstraat', 'street2' => '2', 'zipcode' => '1011 AB', 'city' => 'Amsterdam', 'country' => 'NL'],
            'phone' => ['mobile' => '0612345678'],
        ], true);

        self::assertSame('Pay,ExtraInfo', $service['action']);
        self::assertSame('paypal', $service['name']);
        self::assertSame([
            'PayPalOrderId' => '5O190127TN364715T',
            'Street1' => 'Hoofdstraat',
            'PostalCode' => '1011 AB',
            'CityName' => 'Amsterdam',
            'Country' => 'NL',
            'Street2' => '2',
            'Phone' => '0612345678',
            'Name' => 'Jane Tester',
        ], $this->parameters($service));
    }

    public function testWithoutSellerProtectionOnlyTheOrderIsPaid(): void
    {
        $service = $this->buildService(self::BASE_PAYLOAD, false);

        self::assertSame('Pay', $service['action']);
        self::assertSame(['PayPalOrderId' => '5O190127TN364715T'], $this->parameters($service));
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function buildService(array $payload, bool $sellerProtection): array
    {
        $client = new BuckarooClient('WEBSITEKEY', 'SECRETKEY', 'test');
        $payment = new ExpressPayment($client->client(), 'paypal');
        $payment->manually(true);
        $payment->setPayload((new PayloadService($payload))->toArray());

        $request = json_decode((string) json_encode($payment->payExpress($sellerProtection)->request()->toArray()), true);

        self::assertSame(31.61, $request['AmountDebit']);
        self::assertSame('ABCDEF_12', $request['Invoice']);
        self::assertCount(1, $request['Services']['ServiceList']);

        return $request['Services']['ServiceList'][0];
    }

    /**
     * @param array<string, mixed> $service
     *
     * @return array<string, string>
     */
    private function parameters(array $service): array
    {
        $parameters = [];
        foreach ($service['parameters'] as $parameter) {
            $parameters[$parameter['Name']] = $parameter['Value'];
        }

        return $parameters;
    }
}
