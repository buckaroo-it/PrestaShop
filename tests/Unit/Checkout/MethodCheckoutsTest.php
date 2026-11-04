<?php

namespace BuckarooTest\Unit\Checkout;

use BuckarooTest\Stubs;

require_once __DIR__ . '/CheckoutTestCase.php';

/**
 * Method specific payload of the smaller checkouts.
 */
class MethodCheckoutsTest extends CheckoutTestCase
{
    private function setUpCheckout(string $method, ?\Cart $cart = null): \Checkout
    {
        $checkout = $this->makeCheckout($method, $cart);
        $checkout->setCheckout();

        return $checkout;
    }

    // ------------------------------------------------------------------
    // PayPal
    // ------------------------------------------------------------------

    public function testPayPalWithoutSellerProtectionSendsNoCustomer(): void
    {
        $this->configValues['paypal']['seller_protection'] = 0;

        self::assertSame([], $this->customVars($this->setUpCheckout('paypal')));
    }

    public function states(): array
    {
        return [
            'no state' => [0, null, null],
            'translated name' => [4, [1 => 'Noord-Holland', 2 => 'North Holland'], 'Noord-Holland'],
            'plain name' => [4, 'Utah', 'Utah'],
            'empty translations' => [4, [], null],
        ];
    }

    /**
     * @dataProvider states
     */
    public function testPayPalSellerProtectionSendsCustomerAndAddress(int $stateId, $stateName, ?string $expectedState): void
    {
        $this->configValues['paypal']['seller_protection'] = 1;
        $this->registerAddress(self::INVOICE_ADDRESS_ID, ['id_state' => $stateId]);
        Stubs::object('State', 4, ['name' => $stateName]);

        $vars = $this->customVars($this->setUpCheckout('paypal'));

        $address = [
            'street' => 'Hoofdstraat',
            'street2' => '12',
            'zipcode' => '1234AB',
            'city' => 'Amsterdam',
            'country' => 'NL',
        ];
        if ($expectedState !== null) {
            $address['state'] = $expectedState;
        }
        self::assertSame([
            'customer' => ['name' => 'Jan Jansen'],
            'address' => $address,
            'phone' => ['mobile' => '020-1234567'],
        ], $vars);
    }

    public function testPayPalHouseNumberFallsBackToAddress2(): void
    {
        $this->configValues['paypal']['seller_protection'] = '1';
        $this->registerAddress(self::INVOICE_ADDRESS_ID, ['address1' => 'Hoofdstraat', 'address2' => '9']);

        $vars = $this->customVars($this->setUpCheckout('paypal'));

        self::assertSame('Hoofdstraat', $vars['address']['street']);
        self::assertSame('9', $vars['address']['street2']);
    }

    // ------------------------------------------------------------------
    // Credit card
    // ------------------------------------------------------------------

    public function issuers(): array
    {
        return [
            'BPE_CreditCard' => [['BPE_CreditCard' => ' VISA '], 'visa'],
            'cardCode fallback' => [['BPE_CreditCard' => '', 'cardCode' => 'MasterCard'], 'mastercard'],
            'zero is no issuer' => [['BPE_CreditCard' => '0', 'cardCode' => 'amex'], 'amex'],
            'array is ignored' => [['BPE_CreditCard' => ['visa']], ''],
            'numeric value' => [['cardCode' => 123], '123'],
            'nothing' => [[], ''],
        ];
    }

    /**
     * @dataProvider issuers
     */
    public function testCreditCardIssuerIsResolvedFromTheRequest(array $post, string $issuer): void
    {
        $_POST = $post;

        self::assertSame($issuer, \CreditCardCheckout::resolveIssuer());
        self::assertSame($issuer, $this->paymentRequest($this->setUpCheckout('creditcard'))->issuer);
    }

    public function testCreditCardValidatedIssuerWins(): void
    {
        $_POST = ['BPE_CreditCard' => 'amex'];
        $checkout = $this->makeCheckout('creditcard');
        $checkout->setIssuer('visa');
        $checkout->setCheckout();

        self::assertSame('visa', $this->paymentRequest($checkout)->issuer);
    }

    // ------------------------------------------------------------------
    // Apple Pay
    // ------------------------------------------------------------------

    public function testApplePayCheckoutUsesTheHostedPage(): void
    {
        $checkout = $this->setUpCheckout('applepay');

        self::assertSame(['servicesSelectableByClient' => 'applepay', 'continueOnIncomplete' => '1'], $this->customVars($checkout));
        self::assertFalse(\ApplePayCheckout::isExpressRequest());
        self::assertTrue($checkout->isRedirectRequired());
    }

    public function testApplePayExpressPaysWithTheToken(): void
    {
        $token = ['paymentData' => ['version' => 'EC_v1', 'data' => 'abc'], 'transactionIdentifier' => 'T1'];
        $_POST = ['buckaroo_express' => 'applepay', 'applepay_token' => json_encode($token)];
        $checkout = $this->setUpCheckout('applepay');
        $double = $this->swapRequest($checkout, new FakeResponse(true, false));

        self::assertSame([], $this->customVars($checkout));
        self::assertTrue(\ApplePayCheckout::isExpressRequest());

        $checkout->startPayment();

        self::assertSame('payWithToken', $double->calls[0][0]);
        self::assertSame([base64_encode(json_encode($token)), 'Jan Jansen'], $double->calls[0][1]);
        self::assertFalse($checkout->isRedirectRequired());
    }

    public function testApplePayExpressFollowsARequestedRedirect(): void
    {
        $_POST = ['buckaroo_express' => 'applepay', 'applepay_token' => '{"paymentData":{"a":1}}'];
        $checkout = $this->setUpCheckout('applepay');
        self::assertFalse($checkout->isRedirectRequired(), 'no response yet');

        $this->swapRequest($checkout, new FakeResponse(true, true));
        $checkout->startPayment();

        self::assertTrue($checkout->isRedirectRequired());
    }

    public function invalidTokens(): array
    {
        return [
            'missing' => [null],
            'empty' => [''],
            'not json' => ['{oops'],
            'no payment data' => ['{"transactionIdentifier":"T1"}'],
            'array' => [['paymentData' => 'x']],
        ];
    }

    /**
     * @dataProvider invalidTokens
     */
    public function testApplePayInvalidTokenFallsBackToTheHostedPage($token): void
    {
        $_POST = ['buckaroo_express' => 'applepay'];
        if ($token !== null) {
            $_POST['applepay_token'] = $token;
        }

        self::assertSame('', \ApplePayCheckout::resolveExpressPaymentData());
        self::assertSame('applepay', $this->customVars($this->setUpCheckout('applepay'))['servicesSelectableByClient']);
    }

    // ------------------------------------------------------------------
    // Click to Pay
    // ------------------------------------------------------------------

    public function testClickToPaySendsTheDropInTokens(): void
    {
        $_POST = ['clicktopay_identifier' => ' id-1 ', 'clicktopay_transient_token' => "tok\n"];

        self::assertSame(
            ['identifier' => 'id-1', 'transientToken' => 'tok'],
            $this->customVars($this->setUpCheckout('clicktopay'))
        );
    }

    public function testClickToPayIgnoresNonStringTokens(): void
    {
        $_POST = ['clicktopay_identifier' => ['x']];

        self::assertSame(['identifier' => '', 'transientToken' => ''], \ClickToPayCheckout::resolveDropInPaymentData());
    }

    // ------------------------------------------------------------------
    // Issuer based methods and iDIN
    // ------------------------------------------------------------------

    public function issuerMethods(): array
    {
        return [
            'ideal' => ['ideal', 'INGBNL2A', true],
            'idin' => ['idin', 'BANKNL2Y', true],
            'paybybank' => ['paybybank', 'RABONL2U', true],
            'paybybank n26 needs no redirect' => ['paybybank', 'NTSBDEB1', false],
        ];
    }

    /**
     * @dataProvider issuerMethods
     */
    public function testIssuerIsTakenFromTheRequest(string $method, string $issuer, bool $redirect): void
    {
        $_POST = ['BPE_Issuer' => $issuer];
        $checkout = $this->setUpCheckout($method);

        self::assertSame($issuer, $this->paymentRequest($checkout)->issuer);
        self::assertSame($redirect, $checkout->isRedirectRequired());
    }

    public function testIdinStartVerifyCallsVerify(): void
    {
        $checkout = $this->setUpCheckout('idin');
        $response = new FakeResponse(true, true, 'https://idin.test');
        $double = $this->swapRequest($checkout, $response);

        $checkout->startVerify(['cid' => 3]);

        self::assertSame([['verify', [['cid' => 3]]]], $double->calls);
        self::assertSame($response, $checkout->getResponse());
    }

    // ------------------------------------------------------------------
    // Customer data methods
    // ------------------------------------------------------------------

    public function testPayPerEmail(): void
    {
        $this->configValues['payperemail'] = ['allowed_payments' => 'ideal,paypal', 'due_days' => '5', 'send_instruction_email' => '1'];
        $_POST = ['bpe_payperemail_person_gender' => '2'];

        self::assertSame([
            'customer' => ['gender' => '2', 'firstName' => 'Jan', 'lastName' => 'Jansen'],
            'email' => 'jan@example.com',
            'merchantSendsEmail' => '1',
            'expirationDate' => date('Y-m-d', strtotime('+5day')),
            'paymentMethodsAllowed' => 'ideal,paypal',
            'attachment' => '',
        ], $this->customVars($this->setUpCheckout('payperemail')));
    }

    public function testPrzelewy24(): void
    {
        self::assertSame([
            'customer' => ['firstName' => 'Jan', 'lastName' => 'Jansen'],
            'email' => 'jan@example.com',
        ], $this->customVars($this->setUpCheckout('przelewy24')));
    }

    public function testTrustly(): void
    {
        $this->registerAddress(self::INVOICE_ADDRESS_ID, ['id_country' => self::COUNTRY_DE]);

        self::assertSame([
            'customer' => ['firstName' => 'Jan', 'lastName' => 'Jansen'],
            'email' => 'jan@example.com',
            'country' => 'DE',
        ], $this->customVars($this->setUpCheckout('trustly')));
    }

    public function testSepaDirectDebit(): void
    {
        $_POST = [
            'bpe_sepadirectdebit_iban' => 'NL13TEST0123456789',
            'bpe_sepadirectdebit_bic' => 'TESTNL2A',
            'bpe_sepadirectdebit_bank_account_holder' => 'J. Jansen',
        ];

        self::assertSame([
            'iban' => 'NL13TEST0123456789',
            'bic' => 'TESTNL2A',
            'collectdate' => date('d-m-Y'),
            'customer' => ['name' => 'J. Jansen'],
        ], $this->customVars($this->setUpCheckout('sepadirectdebit')));
    }

    public function transferMails(): array
    {
        return [
            'send' => ['1', 'TRUE'],
            'do not send' => ['0', 'FALSE'],
            'not configured' => [null, 'FALSE'],
        ];
    }

    /**
     * @dataProvider transferMails
     */
    public function testTransfer($sendMail, string $expected): void
    {
        $this->configValues['transfer'] = ['send_instruction_email' => $sendMail, 'due_days' => 14];

        self::assertSame([
            'customer' => ['firstName' => 'Jan', 'lastName' => 'Jansen'],
            'email' => 'jan@example.com',
            'country' => 'NL',
            'dateDue' => date('Y-m-d', strtotime('now + 14 day')),
            'sendMail' => $expected,
        ], $this->customVars($this->setUpCheckout('transfer')));
    }

    public function testGooglePayUsesTheHostedPage(): void
    {
        self::assertSame(
            ['servicesSelectableByClient' => 'googlepay', 'continueOnIncomplete' => '1'],
            $this->customVars($this->setUpCheckout('googlepay'))
        );
    }

    public function testWeroSendsTheBillingEmail(): void
    {
        self::assertSame(['CustomerBillingEmail' => 'jan@example.com'], $this->customVars($this->setUpCheckout('wero')));
    }

    public function testWeroWithoutCustomerSendsNothing(): void
    {
        $checkout = $this->makeCheckout('wero');
        self::setProperty($checkout, 'customer', null);
        $checkout->setCheckout();

        self::assertSame([], $this->customVars($checkout));
        self::assertSame('100.00', $this->paymentRequest($checkout)->amountDebit);
    }
}
