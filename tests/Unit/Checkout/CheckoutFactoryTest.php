<?php

namespace BuckarooTest\Unit\Checkout;

require_once __DIR__ . '/CheckoutTestCase.php';

/**
 * Checkout::getInstance() maps a method key to "<Method>Checkout", whose
 * initialize() asks PaymentRequestFactory for the matching PaymentMethod. Every
 * checkout type is built here and run through setCheckout() and startPayment().
 */
class CheckoutFactoryTest extends CheckoutTestCase
{
    /**
     * method => [checkout class, payment request class, redirect required, verify required, request method startPayment() calls]
     */
    public function checkoutTypes(): array
    {
        return [
            'paypal' => ['paypal', 'PayPalCheckout', 'PayPal', true, false, 'pay'],
            'ideal' => ['ideal', 'IDealCheckout', 'IDeal', true, false, 'pay'],
            'paybybank' => ['paybybank', 'PayByBankCheckout', 'PayByBank', true, false, 'pay'],
            'sepadirectdebit' => ['sepadirectdebit', 'SepaDirectdebitCheckout', 'SepaDirectDebit', false, false, 'payDirectDebit'],
            'kbcpaymentbutton' => ['kbcpaymentbutton', 'KbcpaymentbuttonCheckout', 'Kbcpaymentbutton', true, false, 'pay'],
            'bancontactmrcash' => ['bancontactmrcash', 'BancontactmrcashCheckout', 'Bancontactmrcash', true, false, 'pay'],
            'giftcard' => ['giftcard', 'GiftCardCheckout', 'GiftCard', true, false, 'pay'],
            'creditcard' => ['creditcard', 'CreditCardCheckout', 'CreditCard', true, false, 'pay'],
            'transfer' => ['transfer', 'TransferCheckout', 'Transfer', false, false, 'pay'],
            'afterpay' => ['afterpay', 'AfterPayCheckout', 'AfterPay', true, false, 'payAfterpay'],
            'klarna' => ['klarna', 'KlarnaCheckout', 'Klarna', true, false, 'reserve'],
            'applepay' => ['applepay', 'ApplePayCheckout', 'ApplePay', true, false, 'pay'],
            'googlepay' => ['googlepay', 'GooglePayCheckout', 'GooglePay', true, false, 'pay'],
            'belfius' => ['belfius', 'BelfiusCheckout', 'Belfius', true, false, 'pay'],
            'idin' => ['idin', 'IdinCheckout', 'Idin', true, true, 'pay'],
            'in3' => ['in3', 'In3Checkout', 'In3', true, false, 'pay'],
            'in3Old' => ['in3Old', 'In3OldCheckout', 'In3Old', true, false, 'pay'],
            'billink' => ['billink', 'BillinkCheckout', 'Billink', true, false, 'payBillink'],
            'eps' => ['eps', 'EpsCheckout', 'Eps', false, false, 'pay'],
            'payperemail' => ['payperemail', 'PayPerEmailCheckout', 'PayPerEmail', false, false, 'pay'],
            'przelewy24' => ['przelewy24', 'Przelewy24Checkout', 'Przelewy24', true, false, 'pay'],
            'trustly' => ['trustly', 'TrustlyCheckout', 'Trustly', true, false, 'pay'],
            'wechatpay' => ['wechatpay', 'WechatpayCheckout', 'Wechatpay', true, false, 'pay'],
            'alipay' => ['alipay', 'AlipayCheckout', 'Alipay', true, false, 'pay'],
            'mbway' => ['mbway', 'MbwayCheckout', 'Mbway', true, false, 'pay'],
            'multibanco' => ['multibanco', 'MultibancoCheckout', 'Multibanco', true, false, 'pay'],
            'blik' => ['blik', 'BlikCheckout', 'Blik', true, false, 'pay'],
            'twint' => ['twint', 'TwintCheckout', 'Twint', true, false, 'pay'],
            'swish' => ['swish', 'SwishCheckout', 'Swish', true, false, 'pay'],
            'bizum' => ['bizum', 'BizumCheckout', 'Bizum', true, false, 'pay'],
            'wero' => ['wero', 'WeroCheckout', 'Wero', true, false, 'pay'],
            'clicktopay' => ['clicktopay', 'ClickToPayCheckout', 'ClickToPay', true, false, 'pay'],
        ];
    }

    public function testEveryCheckoutTypeIsCovered(): void
    {
        self::assertEqualsCanonicalizing(
            array_keys(\Checkout::$payment_method_type),
            array_column($this->checkoutTypes(), 0)
        );
    }

    /**
     * Regression: an unknown key used to raise an undefined-index notice and try
     * to instantiate the abstract Checkout instead of throwing.
     */
    public function testGetInstanceRejectsAnUnknownPaymentMethod(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Payment method not found');
        $this->expectExceptionCode(1);

        \Checkout::getInstance('doesnotexist', $this->makeCart(), $this->context());
    }

    /**
     * @dataProvider checkoutTypes
     */
    public function testGetInstanceBuildsTheCheckoutWithItsPaymentRequest(string $method, string $checkoutClass, string $requestClass): void
    {
        $checkout = $this->makeCheckout($method);

        self::assertInstanceOf($checkoutClass, $checkout);
        self::assertSame(strtolower($checkoutClass), strtolower(get_class($checkout)));
        $request = $this->paymentRequest($checkout);
        self::assertSame($requestClass, get_class($request));
        // Gateway mode comes from the method's configuration, not from the request again.
        self::assertSame('live', $request->mode);
        self::assertSame($this->context(), $request->getContext());
        self::assertSame('REF', $checkout->getReference());
    }

    /**
     * @dataProvider checkoutTypes
     */
    public function testSetCheckoutFillsTheCommonPaymentFields(string $method): void
    {
        $this->request(['method' => $method]);
        $checkout = $this->makeCheckout($method, $this->makeCart([\Cart::BOTH => 121.5]));

        $checkout->setCheckout();

        $request = $this->paymentRequest($checkout);
        self::assertSame('121.50', $request->amountDebit);
        self::assertNull($request->OriginalTransactionKey);
        self::assertSame('EUR', $request->currency);
        self::assertSame('REF_5', $request->invoiceId);
        self::assertSame('REF_5', $request->orderId);
        self::assertSame('PrestaShop', $request->platformName);
        self::assertSame('9.0.1.0', $request->platformVersion);
        self::assertSame('Buckaroo', $request->moduleSupplier);
        self::assertSame('buckaroo3', $request->moduleName);
        self::assertSame('5.0.0', $request->moduleVersion);
        self::assertSame('https://shop.test/return', $request->returnUrl);
        self::assertSame('https://shop.test/push', $request->pushUrl);
    }

    /**
     * @dataProvider checkoutTypes
     */
    public function testStartPaymentSendsTheCustomVarsAndExposesTheResponse(
        string $method,
        string $checkoutClass,
        string $requestClass,
        bool $redirectRequired,
        bool $verifyRequired,
        string $requestMethod
    ): void {
        $this->request(['method' => $method]);
        $checkout = $this->makeCheckout($method);
        $checkout->setCheckout();
        $response = new FakeResponse(true, true, 'https://checkout.buckaroo.test/pay', 791);
        $double = $this->swapRequest($checkout, $response);

        self::assertFalse($checkout->isRequestSucceeded());
        self::assertSame(0, $checkout->getStatusCode());
        self::assertNull($checkout->getResponse());

        $checkout->startPayment();

        self::assertCount(1, $double->calls);
        self::assertSame($requestMethod, $double->calls[0][0]);
        self::assertSame([$this->customVars($checkout)], $double->calls[0][1]);
        self::assertTrue($checkout->isRequestSucceeded());
        self::assertSame(791, $checkout->getStatusCode());
        self::assertSame($response, $checkout->getResponse());
        self::assertSame($redirectRequired, $checkout->isRedirectRequired());
        self::assertSame($verifyRequired, $checkout->isVerifyRequired());

        $url = $this->expectRedirect(function () use ($checkout) {
            $checkout->doRedirect();
        });
        self::assertSame('https://checkout.buckaroo.test/pay', $url);
    }

    /**
     * Checkouts that only redirect when Buckaroo asks for it.
     */
    public function responseDrivenRedirects(): array
    {
        return [
            'afterpay' => ['afterpay'],
            'billink' => ['billink'],
            'clicktopay' => ['clicktopay'],
        ];
    }

    /**
     * @dataProvider responseDrivenRedirects
     */
    public function testRedirectFollowsTheResponse(string $method): void
    {
        $checkout = $this->makeCheckout($method);
        self::assertFalse($checkout->isRedirectRequired(), 'no response yet');

        $checkout->setCheckout();
        $this->swapRequest($checkout, new FakeResponse(true, false));
        $checkout->startPayment();

        self::assertFalse($checkout->isRedirectRequired());
    }

    public function testFailedResponseIsNotASucceededRequest(): void
    {
        $checkout = $this->makeCheckout('ideal');
        $checkout->setCheckout();
        $this->swapRequest($checkout, new FakeResponse(false, false, '', 490));
        $checkout->startPayment();

        self::assertFalse($checkout->isRequestSucceeded());
        self::assertSame(490, $checkout->getStatusCode());
    }

    public function testDoRedirectUsesAnExplicitUrl(): void
    {
        $checkout = $this->makeCheckout('ideal');

        $this->expectRedirect(function () use ($checkout) {
            $checkout->doRedirect('https://shop.test/explicit');
        }, 'https://shop.test/explicit');
    }

    public function testGatewayModeFallsBackToTestForANonLiveMethod(): void
    {
        \BuckarooTest\Stubs::$dbResponders = [];
        \BuckarooTest\Stubs::db('/bk_payment_methods/', 7);
        \BuckarooTest\Stubs::db('/bk_configuration/', json_encode(['mode' => 'test']));

        $checkout = $this->makeCheckout('paypal');

        self::assertSame('test', $this->paymentRequest($checkout)->mode);
    }

    public function testSetGatewayModeIgnoresAMissingPaymentRequest(): void
    {
        $checkout = $this->makeCheckout('paypal');
        self::setProperty($checkout, 'payment_request', null);

        $checkout->setGatewayMode('live');

        self::assertNull($this->paymentRequest($checkout));
    }

    public function testCashTicketCheckoutCannotBeBuiltBecauseItsRequestTypeDoesNotExist(): void
    {
        require_once _PS_MODULE_DIR_ . 'buckaroo3/library/checkout/cashticketcheckout.php';

        // CashTicket is not in Checkout::$payment_method_type and
        // PaymentRequestFactory has no REQUEST_TYPE_CASHTICKET constant.
        self::assertArrayNotHasKey('cashticket', \Checkout::$payment_method_type);
        $this->expectException(\Error::class);
        $this->expectExceptionMessageMatches('/REQUEST_TYPE_CASHTICKET/');

        new \CashTicketCheckout($this->makeCart(), $this->context());
    }

    public function testCashTicketCheckoutFlags(): void
    {
        require_once _PS_MODULE_DIR_ . 'buckaroo3/library/checkout/cashticketcheckout.php';
        /** @var \CashTicketCheckout $checkout */
        $checkout = self::instantiate('CashTicketCheckout');
        $this->swapRequest($checkout);
        self::setProperty($checkout, 'cart', $this->makeCart());
        self::setProperty($checkout, 'module', $this->module);
        self::setProperty($checkout, 'context', $this->context());
        $checkout->setReference('REF');

        $checkout->setCheckout();

        self::assertTrue($checkout->isRedirectRequired());
        self::assertFalse($checkout->isVerifyRequired());
        self::assertSame('100.00', $this->paymentRequest($checkout)->amountDebit);

        // CashTicket keeps the base startPayment(): pay() without custom vars.
        $checkout->startPayment();
        self::assertSame([['setDescription', [self::CART_ID]], ['pay', []]], $this->paymentRequest($checkout)->calls);
        self::assertTrue($checkout->isRequestSucceeded());
    }
}
