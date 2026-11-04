<?php

namespace BuckarooTest\Unit\Api;

use Buckaroo\PrestaShop\Src\Exceptions\PaymentMethodNotFoundException;

require_once __DIR__ . '/PaymentMethodTestCase.php';

/**
 * Every request type of PaymentRequestFactory: the class it creates and the
 * SDK call (service, action, payload) its payment entry point makes.
 */
class PaymentMethodsTest extends PaymentMethodTestCase
{
    /**
     * @dataProvider requestTypes
     *
     * @param callable $pay receives the payment method and starts the payment
     */
    public function testEachRequestTypeSendsItsOwnSdkCall(
        string $requestType,
        string $class,
        callable $pay,
        string $sdkType,
        string $action,
        array $payloadFields,
        array $config = []
    ): void {
        ApiFixtures::registerModule($config);

        $created = \PaymentRequestFactory::create($requestType);
        self::assertSame($class, get_class($created));

        $method = $this->paymentMethod($class);
        $response = $pay($method);

        $call = $this->client->lastCall();
        self::assertSame($sdkType, $call['type']);
        self::assertSame($action, $call['action']);
        self::assertSame('test', $this->client->mode);
        foreach (self::basePayload() as $key => $value) {
            if (!array_key_exists($key, $payloadFields)) {
                self::assertSame($value, $call['payload'][$key], 'payload field ' . $key);
            }
        }
        foreach ($payloadFields as $key => $value) {
            self::assertSame($value, $call['payload'][$key] ?? null, 'payload field ' . $key);
        }
        self::assertInstanceOf(\ResponseDefault::class, $response);
        self::assertTrue($response->isSuccess());
    }

    public function requestTypes(): array
    {
        $pay = function (\PaymentMethod $method) {
            return $method->pay();
        };
        $payWith = function (array $customVars) {
            return function (\PaymentMethod $method) use ($customVars) {
                return $method->pay($customVars);
            };
        };
        $withIssuer = function (string $issuer) {
            return function (\PaymentMethod $method) use ($issuer) {
                $method->issuer = $issuer;

                return $method->pay();
            };
        };
        $billing = ['billing' => ['recipient' => ['firstName' => 'Jan']]];

        return [
            'paypal' => ['paypal', 'PayPal', $pay, 'paypal', 'pay', []],
            'paypal with seller protection' => ['paypal', 'PayPal', $payWith(['customer' => ['name' => 'Jan']]), 'paypal', 'extraInfo', ['customer' => ['name' => 'Jan']], ['paypal.seller_protection' => '1']],
            'ideal with issuer' => ['ideal', 'IDeal', $withIssuer('INGBNL2A'), 'ideal', 'pay', ['issuer' => 'INGBNL2A'], ['ideal.show_issuers' => true]],
            'ideal issuer defaults to required' => ['ideal', 'IDeal', $pay, 'ideal', 'pay', ['issuer' => '']],
            'ideal without issuer list' => ['ideal', 'IDeal', $pay, 'ideal', 'pay', ['continueOnIncomplete' => 1], ['ideal.show_issuers' => false]],
            'paybybank' => ['paybybank', 'PayByBank', $withIssuer('RABONL2U'), 'paybybank', 'pay', ['issuer' => 'RABONL2U']],
            'kbcpaymentbutton' => ['kbcpaymentbutton', 'Kbcpaymentbutton', $pay, 'kbcpaymentbutton', 'pay', []],
            'sepadirectdebit' => ['sepadirectdebit', 'SepaDirectDebit', function (\PaymentMethod $method) {
                return $method->payDirectDebit(['iban' => 'NL13TEST0123456789', 'customer' => ['name' => 'J. Jansen']]);
            }, 'sepadirectdebit', 'pay', ['iban' => 'NL13TEST0123456789', 'customer' => ['name' => 'J. Jansen']]],
            'bancontactmrcash' => ['bancontactmrcash', 'Bancontactmrcash', $pay, 'bancontactmrcash', 'pay', []],
            'blik' => ['blik', 'Blik', $pay, 'blik', 'pay', []],
            'giftcard redirect' => ['giftcard', 'GiftCard', $payWith(['servicesSelectableByClient' => 'fashioncheque,tcs']), 'giftcard', 'payRedirect', ['servicesSelectableByClient' => 'fashioncheque,tcs']],
            'creditcard' => ['creditcard', 'CreditCard', $withIssuer(' visa '), 'creditcard', 'pay', ['name' => 'visa']],
            'transfer' => ['transfer', 'Transfer', $payWith(['email' => 'buyer@example.com', 'sendMail' => true]), 'transfer', 'pay', ['email' => 'buyer@example.com', 'sendMail' => true]],
            'afterpay' => ['afterpay', 'AfterPay', function (\PaymentMethod $method) use ($billing) {
                return $method->payAfterpay($billing);
            }, 'afterpay', 'pay', $billing],
            'klarna pay' => ['klarna', 'Klarna', $payWith($billing), 'klarna', 'pay', $billing],
            'klarna reserve' => ['klarna', 'Klarna', function (\PaymentMethod $method) use ($billing) {
                return $method->reserve($billing);
            }, 'klarna', 'reserve', $billing],
            'applepay redirect' => ['applepay', 'ApplePay', $payWith(['servicesSelectableByClient' => 'applepay']), 'applepay', 'payRedirect', ['servicesSelectableByClient' => 'applepay']],
            'applepay express token' => ['applepay', 'ApplePay', function (\PaymentMethod $method) {
                return $method->payWithToken('{"paymentData":"encrypted"}', 'J. Jansen');
            }, 'applepay', 'pay', ['paymentData' => '{"paymentData":"encrypted"}', 'customerCardName' => 'J. Jansen']],
            'googlepay' => ['googlepay', 'GooglePay', $payWith(['paymentData' => 'token', 'customerCardName' => 'Jan']), 'googlepay', 'pay', ['paymentData' => 'token', 'customerCardName' => 'Jan']],
            'belfius' => ['belfius', 'Belfius', $pay, 'belfius', 'pay', []],
            'in3' => ['in3', 'In3', $payWith($billing), 'in3', 'pay', $billing],
            'in3 old' => ['in3Old', 'In3Old', $payWith($billing), 'in3Old', 'payInInstallments', $billing],
            'billink' => ['billink', 'Billink', function (\PaymentMethod $method) use ($billing) {
                return $method->payBillink($billing);
            }, 'billink', 'pay', $billing],
            'eps' => ['eps', 'Eps', $pay, 'eps', 'pay', []],
            'payperemail' => ['payperemail', 'PayPerEmail', $payWith(['email' => 'buyer@example.com']), 'payperemail', 'paymentInvitation', ['email' => 'buyer@example.com']],
            'przelewy24' => ['przelewy24', 'Przelewy24', $payWith(['email' => 'buyer@example.com']), 'przelewy24', 'pay', ['email' => 'buyer@example.com']],
            'trustly' => ['trustly', 'Trustly', $payWith(['country' => 'NL']), 'trustly', 'pay', ['country' => 'NL']],
            'wechatpay' => ['wechatpay', 'Wechatpay', $pay, 'wechatpay', 'pay', []],
            'alipay' => ['alipay', 'Alipay', $pay, 'alipay', 'pay', []],
            'multibanco' => ['multibanco', 'Multibanco', $pay, 'multibanco', 'pay', []],
            'mbway' => ['mbway', 'Mbway', $pay, 'mbway', 'pay', []],
            'twint' => ['twint', 'Twint', $pay, 'twint', 'pay', []],
            'swish' => ['swish', 'Swish', $pay, 'swish', 'pay', []],
            'bizum' => ['bizum', 'Bizum', $pay, 'bizum', 'pay', []],
            'wero with billing email' => ['wero', 'Wero', $payWith(['CustomerBillingEmail' => 'buyer@example.com']), 'wero', 'pay', ['customParameters' => ['CustomerBillingEmail' => 'buyer@example.com']]],
            'wero without email' => ['wero', 'Wero', $payWith(['CustomerBillingEmail' => '']), 'wero', 'pay', ['customParameters' => null]],
            'clicktopay' => ['clicktopay', 'ClickToPay', $payWith(['identifier' => ' id-1 ', 'transientToken' => ' tok ']), 'clicktopay', 'pay', ['identifier' => 'id-1', 'transientToken' => 'tok']],
        ];
    }

    public function testUnknownRequestTypeIsRejected(): void
    {
        $this->expectException(PaymentMethodNotFoundException::class);

        \PaymentRequestFactory::create('bitcoin');
    }

    public function testEveryRequestTypeHasAClass(): void
    {
        ApiFixtures::registerModule();

        foreach (\PaymentRequestFactory::$valid_request_types as $type => $class) {
            self::assertInstanceOf($class, \PaymentRequestFactory::create($type), $type);
            self::assertInstanceOf(\PaymentMethod::class, \PaymentRequestFactory::create($type), $type);
        }
    }

    /**
     * @dataProvider methodsWithASeparateEntryPoint
     */
    public function testPayIsANoOpForMethodsWithTheirOwnEntryPoint(string $class): void
    {
        $method = $this->paymentMethod($class);

        self::assertNull($method->pay(['anything' => 1]));
        self::assertSame([], $this->client->calls);
    }

    public function methodsWithASeparateEntryPoint(): array
    {
        return [
            'afterpay' => ['AfterPay'],
            'billink' => ['Billink'],
            'sepadirectdebit' => ['SepaDirectDebit'],
            'idin' => ['Idin'],
        ];
    }

    public function testWeroUsesADefaultDescription(): void
    {
        $method = $this->paymentMethod('Wero');
        $method->description = '';

        $method->pay();

        self::assertSame('Default Store View', $this->client->lastCall()['payload']['description']);
        self::assertArrayNotHasKey('customParameters', $this->client->lastCall()['payload']);
    }

    /**
     * @dataProvider missingCreditcardIssuers
     */
    public function testCreditcardRequiresACard($issuer): void
    {
        $method = $this->paymentMethod('CreditCard');
        $method->issuer = $issuer;

        try {
            $method->pay();
            self::fail('Expected an exception');
        } catch (\Exception $e) {
            self::assertSame('Please select a credit or debit card before continuing with payment.', $e->getMessage());
        }
        self::assertSame([], $this->client->calls);
    }

    public function missingCreditcardIssuers(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'blank' => ['  '],
            'zero option' => ['0'],
            'not a string' => [['visa']],
        ];
    }

    /**
     * @dataProvider incompleteClickToPay
     */
    public function testClickToPayRequiresTheDropInResult(array $customVars): void
    {
        $method = $this->paymentMethod('ClickToPay');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Please complete the Click to Pay checkout');

        $method->pay($customVars);
    }

    public function incompleteClickToPay(): array
    {
        return [
            'nothing' => [[]],
            'no token' => [['identifier' => 'id-1']],
            'blank identifier' => [['identifier' => ' ', 'transientToken' => 'tok']],
        ];
    }

    public function testApplePayExpressRequiresAToken(): void
    {
        $method = $this->paymentMethod('ApplePay');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The Apple Pay token was not received.');

        $method->payWithToken('', 'J. Jansen');
    }

    public function testIdealWithANonStringIssuerSendsAnEmptyIssuer(): void
    {
        ApiFixtures::registerModule(['ideal.show_issuers' => true]);
        $method = $this->paymentMethod('IDeal');
        $method->issuer = ['INGBNL2A'];

        $method->pay();

        self::assertSame('', $this->client->lastCall()['payload']['issuer']);
    }
}
