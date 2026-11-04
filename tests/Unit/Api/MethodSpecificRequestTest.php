<?php

namespace BuckarooTest\Unit\Api;

require_once __DIR__ . '/PaymentMethodTestCase.php';

/**
 * Request building that is specific to one payment method: giftcard direct
 * pay, PayByBank's iDEAL fallback and the iDIN verify request.
 */
class MethodSpecificRequestTest extends PaymentMethodTestCase
{
    /**
     * @dataProvider giftcardCards
     */
    public function testGiftcardPayDirectMapsCardNumberAndPinPerBrand(array $customVars, array $expected, array $absent): void
    {
        $method = $this->paymentMethod(\GiftCard::class);

        $method->payDirect($customVars);

        $call = $this->client->lastCall();
        self::assertSame('giftcard', $call['type']);
        self::assertSame('pay', $call['action']);
        foreach ($expected as $key => $value) {
            self::assertSame($value, $call['payload'][$key] ?? null, $key);
        }
        foreach ($absent as $key) {
            self::assertArrayNotHasKey($key, $call['payload']);
        }
        self::assertSame('XKBKNABJK_42', $call['payload']['invoice']);
    }

    public function giftcardCards(): array
    {
        return [
            'fashioncheque' => [
                ['name' => 'FashionCheque', 'cardNumber' => '6010', 'pin' => '1234'],
                ['name' => 'FashionCheque', 'fashionChequeCardNumber' => '6010', 'fashionChequePin' => '1234'],
                ['cardNumber', 'pin', 'intersolveCardnumber'],
            ],
            'tcs' => [
                ['name' => 'tcs', 'cardNumber' => '7020', 'pin' => '99'],
                ['tcsCardnumber' => '7020', 'tcsValidationCode' => '99'],
                ['cardNumber', 'pin'],
            ],
            'custom giftcard keeps the generic parameters' => [
                ['name' => 'customgiftcard1', 'cardNumber' => '1111', 'pin' => '2222'],
                ['cardNumber' => '1111', 'pin' => '2222'],
                ['intersolveCardnumber'],
            ],
            'other brands are intersolve' => [
                ['name' => 'boekenbon', 'cardNumber' => '3333', 'pin' => '4444'],
                ['intersolveCardnumber' => '3333', 'intersolvePIN' => '4444'],
                ['cardNumber', 'pin'],
            ],
            'no brand is intersolve' => [
                ['cardNumber' => '5555', 'pin' => '6666'],
                ['intersolveCardnumber' => '5555', 'intersolvePIN' => '6666'],
                ['cardNumber', 'pin'],
            ],
            'already mapped by the caller' => [
                ['name' => 'boekenbon', 'intersolveCardnumber' => '7777', 'intersolvePIN' => '8888'],
                ['intersolveCardnumber' => '7777', 'intersolvePIN' => '8888'],
                ['cardNumber', 'pin'],
            ],
            'fashioncheque already mapped, raw values dropped' => [
                ['name' => 'fashioncheque', 'cardNumber' => '1', 'pin' => '2', 'fashionChequeCardNumber' => '9', 'fashionChequePin' => '8'],
                ['fashionChequeCardNumber' => '9', 'fashionChequePin' => '8'],
                ['cardNumber', 'pin'],
            ],
            'no card data' => [
                ['name' => 'boekenbon'],
                ['name' => 'boekenbon'],
                ['cardNumber', 'pin', 'intersolveCardnumber', 'intersolvePIN'],
            ],
        ];
    }

    public function testGiftcardPayDirectAfterAPartialPaymentPaysTheRemainder(): void
    {
        $method = $this->paymentMethod(\GiftCard::class);
        $method->OriginalTransactionKey = 'GROUP-1';

        $method->payDirect(['name' => 'boekenbon', 'cardNumber' => '1', 'pin' => '2']);

        $call = $this->client->lastCall();
        self::assertSame('payRemainder', $call['action']);
        self::assertSame('GROUP-1', $call['payload']['originalTransactionKey']);
        self::assertSame('1', $call['payload']['intersolveCardnumber']);
    }

    public function testGiftcardPartialPaymentReplyExposesTheRemainder(): void
    {
        $this->client->reply = ApiFixtures::reply(792, [
            'RequiredAction' => [
                'Name' => 'PayRemainder',
                'PayRemainderDetails' => [
                    'RemainderAmount' => '7.25',
                    'Currency' => 'EUR',
                    'GroupTransaction' => 'GROUP-1',
                ],
            ],
        ]);
        $method = $this->paymentMethod(\GiftCard::class);

        $response = $method->payDirect(['name' => 'boekenbon', 'cardNumber' => '1', 'pin' => '2']);

        self::assertTrue($response->isPartialPayment());
        self::assertSame('GROUP-1', $response->getGroupTransaction());
        self::assertSame(7.25, $response->getRemainderAmount());
        self::assertTrue($response->isAwaitingConsumer());
        self::assertFalse($response->isPaid());
        self::assertTrue($response->hasSucceeded());
    }

    public function testPayByBankRemembersTheIssuerInTheCookie(): void
    {
        ApiFixtures::registerModule();
        $method = $this->paymentMethod(\PayByBank::class);
        $method->issuer = 'ABNANL2A';

        $method->pay();

        self::assertSame('ABNANL2A', $this->context()->cookie->values['BUCKAROO_LAST_PAYBYBANK_ISSUER']);
        $call = $this->client->lastCall();
        self::assertSame('paybybank', $call['type']);
        self::assertSame('ABNANL2A', $call['payload']['issuer']);
    }

    /**
     * @dataProvider payByBankDevices
     */
    public function testPayByBankSendsIngOnMobileAsIdeal(string $issuer, bool $mobile, string $expectedType): void
    {
        $context = new class extends \Context {
            /** @var bool */
            public $mobile = false;

            public function isMobile()
            {
                return $this->mobile;
            }
        };
        $context->mobile = $mobile;
        $context->shop = new \Shop();
        $context->cookie = new \Cookie();
        $method = $this->paymentMethod(\PayByBank::class);
        $method->setContext($context);
        $method->issuer = $issuer;
        ApiFixtures::gatewayMode('live');
        $method->mode = null;

        $method->pay();

        $call = $this->client->lastCall();
        self::assertSame($expectedType, $call['type']);
        self::assertSame($issuer, $call['payload']['issuer']);
        // The mode of PayByBank itself, also when the request is sent as iDEAL.
        self::assertSame('live', $this->client->mode);
        self::assertStringContainsString('name = "paybybank"', \BuckarooTest\Stubs::sql('getValue')[0]);
        self::assertSame($issuer, $context->cookie->values['BUCKAROO_LAST_PAYBYBANK_ISSUER']);
    }

    public function payByBankDevices(): array
    {
        return [
            'ING on mobile' => ['INGBNL2A', true, 'ideal'],
            'ING on desktop' => ['INGBNL2A', false, 'paybybank'],
            'other bank on mobile' => ['RABONL2U', true, 'paybybank'],
        ];
    }

    public function testPayByBankWithoutAContextStillPays(): void
    {
        $method = $this->paymentMethod(\PayByBank::class);
        $method->issuer = null;

        $method->pay();

        $call = $this->client->lastCall();
        self::assertSame('paybybank', $call['type']);
        self::assertSame('', $call['payload']['issuer']);
    }

    /**
     * @dataProvider idinIssuers
     */
    public function testIdinVerifySendsTheBicOfTheBank(?string $issuer, string $bic): void
    {
        ApiFixtures::gatewayMode('test');
        $method = $this->paymentMethod(\Idin::class);
        $method->issuer = $issuer;

        $response = $method->verify(['cid' => 17, 'cart' => 42]);

        $call = $this->client->lastCall();
        self::assertSame('idin', $call['type']);
        self::assertSame('verify', $call['action']);
        self::assertSame($bic, $call['payload']['issuer']);
        self::assertSame(['cid' => 17, 'cart' => 42], $call['payload']['additionalParameters']);
        self::assertSame('https://shop.test/push', $call['payload']['pushURL']);
        self::assertInstanceOf(\ResponseDefault::class, $response);
    }

    public function idinIssuers(): array
    {
        return [
            'BANKNL2Y' => ['BANKNL2Y', 'BANKNL2Y'],
            'ABNAMRO' => ['ABNAMRO', 'ABNANL2A'],
            'ASNBANK' => ['ASNBANK', 'ASNBNL21'],
            'INGBANK' => ['INGBANK', 'INGBNL2A'],
            'RABOBANK' => ['RABOBANK', 'RABONL2U'],
            'SNSBANK' => ['SNSBANK', 'SNSBNL2A'],
            'SNSREGIO' => ['SNSREGIO', 'RBRBNL21'],
            'TRIODOS' => ['TRIODOS', 'TRIONL2U'],
            'LANSCHOT' => ['LANSCHOT', 'FVLBNL22'],
            'KNAB' => ['KNAB', 'KNABNL2H'],
            'BUNQ' => ['BUNQ', 'BUNQNL2A'],
            'MOYONL21' => ['MOYONL21', 'MOYONL21'],
            'HANDNL2A' => ['HANDNL2A', 'HANDNL2A'],
            'REVOLT21' => ['REVOLT21', 'REVOLT21'],
            'unknown bank' => ['UNKNOWN', ''],
            'no bank' => [null, ''],
        ];
    }

    public function testIdinVerifyWithoutCustomerOrCartSendsNoAdditionalParameters(): void
    {
        ApiFixtures::gatewayMode('test');
        $method = $this->paymentMethod(\Idin::class);
        $method->issuer = 'KNAB';

        $method->verify();

        self::assertArrayNotHasKey('additionalParameters', $this->client->lastCall()['payload']);
    }

    public function testIdinReplyIsWrappedInAnIdinResponse(): void
    {
        ApiFixtures::gatewayMode('test');
        $this->client->reply = ApiFixtures::reply(791, ['Services' => [['Name' => 'IDIN', 'Action' => 'verify']]]);
        $method = $this->paymentMethod(\Idin::class);

        $response = $method->verify();

        self::assertInstanceOf(\IdinResponse::class, $response);
        self::assertTrue($response->isPendingProcessing());
    }
}
