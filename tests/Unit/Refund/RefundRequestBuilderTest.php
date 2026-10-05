<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Tests\Unit\Refund;

use Buckaroo\BuckarooClient;
use Buckaroo\PaymentMethods\GiftCard\Models\Refund as GiftCardRefundModel;
use Buckaroo\PrestaShop\Src\Refund\Request\QuantityBasedBuilder;
use Buckaroo\Services\PayloadService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/RefundTestSupport.php';

/**
 * Covers the Buckaroo refund request payload produced by the plugin, with
 * particular focus on gift cards (Fashioncheque). Both refund builders share
 * AbstractBuilder::buildIssuers(), so exercising QuantityBasedBuilder validates
 * the gift-card issuer logic used by the native and custom refund flows alike.
 */
class RefundRequestBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // AbstractBuilder::getIp() reads the client IP from the request globals.
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    private function makeOrder(): \Order
    {
        return new \Order(326);
    }

    private function makePayment(string $method, float $amount = 28.80): \OrderPayment
    {
        $payment = new \OrderPayment();
        $payment->payment_method = $method;
        $payment->amount = $amount;

        return $payment;
    }

    public function testFashionchequeFullRefundBodyHasNoEmailOrLastname(): void
    {
        $body = (new QuantityBasedBuilder())->create($this->makeOrder(), $this->makePayment('fashioncheque'), 28.80);

        // Gift card is identified purely by its service code as `name`.
        $this->assertSame('fashioncheque', $body['name']);

        // The regression: email/lastname must NOT be part of a gift card refund.
        $this->assertArrayNotHasKey('email', $body);
        $this->assertArrayNotHasKey('lastname', $body);

        // Common refund fields are populated correctly.
        $this->assertEqualsWithDelta(28.80, $body['amountCredit'], 0.001);
        $this->assertSame('EUR', $body['currency']);
        $this->assertSame('ABCDEF0123456789ABCDEF0123456789', $body['originalTransactionKey']);
        $this->assertSame('GGADEYTRM_55', $body['invoice']);
        $this->assertSame('GGADEYTRM_55', $body['order']);
    }

    public function testFashionchequePartialRefundBodyUsesPartialAmount(): void
    {
        $body = (new QuantityBasedBuilder())->create($this->makeOrder(), $this->makePayment('fashioncheque'), 10.00);

        $this->assertSame('fashioncheque', $body['name']);
        $this->assertArrayNotHasKey('email', $body);
        $this->assertArrayNotHasKey('lastname', $body);
        $this->assertEqualsWithDelta(10.00, $body['amountCredit'], 0.001);
    }

    public function testNonGiftcardRefundIsUnaffected(): void
    {
        $body = (new QuantityBasedBuilder())->create($this->makeOrder(), $this->makePayment('ideal'), 28.80);

        // Plain methods carry no issuer fields at all.
        $this->assertArrayNotHasKey('name', $body);
        $this->assertArrayNotHasKey('email', $body);
        $this->assertArrayNotHasKey('lastname', $body);
        $this->assertEqualsWithDelta(28.80, $body['amountCredit'], 0.001);
    }

    public function testCreditcardRefundKeepsNameAndVersion(): void
    {
        $body = (new QuantityBasedBuilder())->create($this->makeOrder(), $this->makePayment('visa'), 28.80);

        $this->assertSame('visa', $body['name']);
        $this->assertSame(2, $body['version']);
        $this->assertArrayNotHasKey('email', $body);
        $this->assertArrayNotHasKey('lastname', $body);
    }

    /**
     * End-to-end payload validation: feed the plugin body into the real Buckaroo
     * SDK gift card refund and assert the generated Transaction request matches
     * the SDK's documented gift card refund shape — i.e. the fashioncheque
     * Refund service carries NO stray parameters (no Email/Lastname), which is
     * what caused Buckaroo to reject the request with HTTP 400.
     */
    public function testGeneratedSdkRefundRequestHasNoStrayServiceParameters(): void
    {
        $body = (new QuantityBasedBuilder())->create($this->makeOrder(), $this->makePayment('fashioncheque'), 28.80);

        $request = $this->buildSdkGiftcardRefundRequest($body);

        $service = $request['Services']['ServiceList'][0] ?? [];
        $this->assertSame('fashioncheque', $service['name'] ?? null);
        $this->assertSame('Refund', $service['action'] ?? null);

        $paramNames = array_map(
            static function ($p) {
                return $p['Name'] ?? '';
            },
            $service['parameters'] ?? []
        );
        $this->assertNotContains('Email', $paramNames);
        $this->assertNotContains('Lastname', $paramNames);

        // The identifying fields still travel at the top level of the request.
        $this->assertEqualsWithDelta(28.80, $request['AmountCredit'], 0.001);
        $this->assertSame('ABCDEF0123456789ABCDEF0123456789', $request['OriginalTransactionKey']);
    }

    /**
     * Builds the Buckaroo SDK gift card Refund request from a plugin body without
     * performing any HTTP call (mirrors Refund\Request\Handler::refund(), which
     * routes gift cards through the SDK 'giftcard' method).
     */
    private function buildSdkGiftcardRefundRequest(array $body): array
    {
        $buckaroo = new BuckarooClient(str_repeat('A', 32), str_repeat('B', 32), 'test');
        $paymentMethod = $buckaroo->method('giftcard')->paymentMethod();
        $paymentMethod->setPayload((new PayloadService($body))->toArray());

        $payloadProp = new \ReflectionProperty($paymentMethod, 'payload');
        $payloadProp->setAccessible(true);
        $model = new GiftCardRefundModel($payloadProp->getValue($paymentMethod));

        $setRefundPayload = new \ReflectionMethod($paymentMethod, 'setRefundPayload');
        $setRefundPayload->setAccessible(true);
        $setRefundPayload->invoke($paymentMethod);

        $setServiceList = new \ReflectionMethod($paymentMethod, 'setServiceList');
        $setServiceList->setAccessible(true);
        $setServiceList->invoke($paymentMethod, 'Refund', $model);

        return $paymentMethod->request()->toArray();
    }
}
