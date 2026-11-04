<?php

namespace BuckarooTest\Unit\Controller\Front\ReturnFlow;

use Buckaroo\PrestaShop\Src\Service\ReturnStateSigner;
use BuckarooTest\RedirectException;
use BuckarooTest\Stubs;

require_once __DIR__ . '/StubsSupport.php';

/**
 * Customer return after payment (controllers/front/userreturn.php).
 *
 * Note: initContent() wraps everything in catch (Throwable). In PrestaShop the
 * redirect exits, but the test stub of Tools::redirect throws, so a redirect
 * made inside the try block is caught and followed by the "fatal" error
 * redirect. Tests through initContent() assert the FIRST redirect; the private
 * handlers are also called directly where the exact redirect matters.
 */
class UserReturnControllerTest extends ReturnFlowTestCase
{
    private const PENDING = 10;
    private const PAID = 2;
    private const DEFAULT_ERROR = 'Your payment was unsuccessful. Please try again or choose another payment method.';

    /** @var RecordingLogger */
    private $logger;

    protected function setUp(): void
    {
        parent::setUp();
        \Configuration::updateValue('BUCKAROO_ORDER_STATE_DEFAULT', self::PENDING);
        \Configuration::updateValue('BUCKAROO_ORDER_STATE_SUCCESS', self::PAID);
        \Configuration::updateValue('PS_OS_PAYMENT', self::PAID);
        \Configuration::updateValue('BUCKAROO_ORDER_STATE_FAILED', 11);
        Stubs::object('OrderState', self::PAID);

        $customer = new \Customer();
        $customer->id = 7;
        $this->context()->customer = $customer;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function controller(): \Buckaroo3UserreturnModuleFrontController
    {
        $controller = new \Buckaroo3UserreturnModuleFrontController();
        $this->logger = $this->recordLogs($controller);

        return $controller;
    }

    /** Order 42 on cart 5, owned by customer 7. */
    private function givenOrder(array $orderProps = []): void
    {
        Stubs::object('Cart', 5, ['id_customer' => 7]);
        Stubs::object('Customer', 7, ['secure_key' => 'customer-key']);
        Stubs::returns('Order::getIdByCartId', function ($cartId) {
            return (int) $cartId === 5 ? 42 : 0;
        });
        Stubs::object('Order', 42, $orderProps + [
            'reference' => 'REF123',
            'id_cart' => 5,
            'current_state' => self::PENDING,
            'total_paid' => 10.0,
            'total_paid_real' => 0.0,
        ]);
    }

    private function returnRequest(array $fields, bool $tamper = false): void
    {
        $this->signedPush($fields + [
            'brq_invoicenumber' => 'REF123_5',
            'brq_payment_method' => 'ideal',
            'brq_amount' => '10.00',
            'brq_test' => 'false',
            'brq_transactions' => 'TXKEY',
        ], $tamper);
    }

    private function handleReturn(\Buckaroo3UserreturnModuleFrontController $controller): string
    {
        return $this->expectRedirect(function () use ($controller) {
            self::invoke($controller, 'handleReturn', [\ResponseFactory::getResponse()]);
        });
    }

    private function errorUrl(string $message): string
    {
        return 'https://shop.test/order?' . http_build_query([
            'step' => 3,
            'buckaroo_error_msg' => urlencode($message),
            'buckaroo_error' => 1,
        ]);
    }

    private function confirmationUrl(): string
    {
        return 'https://shop.test/order-confirmation?' . http_build_query([
            'id_cart' => 5,
            'id_module' => 1,
            'id_order' => 42,
            'key' => 'customer-key',
            'success' => 'true',
        ]);
    }

    private function signer(): ReturnStateSigner
    {
        return new ReturnStateSigner(_COOKIE_KEY_);
    }

    /** @return array<string, mixed> decoded state from a redirect to the userreturn controller */
    private function stateFrom(string $url): array
    {
        self::assertStringStartsWith('https://shop.test/module/buckaroo3/userreturn?bk_state=', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $state = $this->signer()->verify((string) $query['bk_state']);
        self::assertNotNull($state, 'the state is signed with the cookie key');

        return $state;
    }

    // -------------------------------------------------------------------------
    // init(): session detection
    // -------------------------------------------------------------------------

    public function testReturnWithoutSessionCookieDisablesCookieWriting(): void
    {
        $cookie = new SessionlessCookie();
        $this->context()->cookie = $cookie;
        $controller = $this->controller();

        $controller->init();

        self::assertTrue(self::getProperty($controller, 'withoutSession'));
        self::assertSame(1, Stubs::callCount(SessionlessCookie::class . '->disallowWriting'));
    }

    /**
     * @dataProvider sessionRequests
     */
    public function testReturnWithSessionOrSignedStateKeepsTheCookie(bool $cookieExists, array $get): void
    {
        $this->context()->cookie = $cookieExists ? new \Cookie() : new SessionlessCookie();
        $this->request($get);
        $controller = $this->controller();

        $controller->init();

        self::assertFalse(self::getProperty($controller, 'withoutSession'));
        self::assertSame(0, Stubs::callCount(SessionlessCookie::class . '->disallowWriting'));
    }

    public function sessionRequests(): array
    {
        return [
            'session cookie present' => [true, []],
            'signed state, no cookie yet' => [false, ['bk_state' => 'x.y']],
        ];
    }

    // -------------------------------------------------------------------------
    // Successful returns
    // -------------------------------------------------------------------------

    public function testPaidReturnMarksTheOrderPaidAndShowsTheConfirmation(): void
    {
        $this->givenOrder();
        $cart = new \Cart();
        $cart->id = 5;
        $this->context()->cart = $cart;
        $this->context()->cookie->buckaroo_giftcard_applied = '1';
        $this->context()->cookie->buckaroo_giftcard_tx_key = 'GC';
        $this->returnRequest(['brq_statuscode' => '190']);
        $controller = $this->controller();

        $url = $this->handleReturn($controller);

        self::assertSame($this->confirmationUrl(), $url);
        self::assertSame([[self::PAID, 42, true]], Stubs::callsTo('OrderHistory->changeIdOrderState'));
        $saved = Stubs::persisted('OrderPayment', 'save');
        self::assertCount(1, $saved);
        self::assertSame('TXKEY', $saved[0]->transaction_id);
        self::assertSame(10.0, $saved[0]->amount);
        self::assertSame([$cart], Stubs::persisted('Cart', 'delete'), 'the paid cart is removed from the session');
        self::assertArrayNotHasKey('buckaroo_giftcard_applied', $this->context()->cookie->values);
        self::assertArrayNotHasKey('buckaroo_giftcard_tx_key', $this->context()->cookie->values);
    }

    public function testPendingReturnShowsTheConfirmationButLeavesTheOrderState(): void
    {
        $this->givenOrder();
        $this->returnRequest(['brq_statuscode' => '791']);
        $controller = $this->controller();

        $url = $this->handleReturn($controller);

        self::assertSame($this->confirmationUrl(), $url);
        self::assertSame([], Stubs::callsTo('OrderHistory->changeIdOrderState'));
        self::assertSame([], Stubs::persisted('OrderPayment'));
        self::assertContains('Pending return; order state left for the push', $this->logger->messages('info'));
    }

    public function testCartDeleteFailureDoesNotBlockTheConfirmation(): void
    {
        $this->givenOrder(['current_state' => self::PAID]);
        $cart = new \Cart();
        $cart->id = 5;
        $cart->stub('delete', function () {
            throw new \RuntimeException('locked');
        });
        $this->context()->cart = $cart;
        $this->returnRequest(['brq_statuscode' => '190']);
        $controller = $this->controller();

        self::assertSame($this->confirmationUrl(), $this->handleReturn($controller));
        self::assertContains('Cart delete failed: locked', $this->logger->messages('error'));
    }

    public function testSuccessfulReturnWithoutOrderShowsAnError(): void
    {
        Stubs::returns('Order::getIdByCartId', 0);
        $this->returnRequest(['brq_statuscode' => '190']);
        $controller = $this->controller();

        $url = $this->handleReturn($controller);

        self::assertSame(
            $this->errorUrl('Your payment was received, but the order could not be loaded. Please contact the shop.'),
            $url
        );
        self::assertContains('Missing cart/order on successful return', $this->logger->messages('error'));
        self::assertSame(
            'Your payment was received, but the order could not be loaded. Please contact the shop.',
            $this->context()->cookie->buckaroo_error_msg
        );
    }

    public function testSuccessfulReturnWithoutCartInTheInvoiceShowsAnError(): void
    {
        $this->returnRequest(['brq_statuscode' => '190', 'brq_invoicenumber' => 'REF123']);
        $controller = $this->controller();

        $this->handleReturn($controller);

        self::assertSame(0, Stubs::callCount('Order::getIdByCartId'));
        self::assertContains('Missing cart/order on successful return', $this->logger->messages('error'));
    }

    public function testSuccessfulReturnForAnUnknownCustomerGoesBackToCheckout(): void
    {
        $this->givenOrder();
        Stubs::$objects['Customer'] = [];
        $this->returnRequest(['brq_statuscode' => '190']);
        $controller = $this->controller();

        self::assertSame('index.php?controller=order&step=1', $this->handleReturn($controller));
        self::assertSame([], Stubs::callsTo('OrderHistory->changeIdOrderState'));
    }

    public function testFinishSuccessWithoutOrderGoesBackToCheckout(): void
    {
        Stubs::object('Cart', 6, ['id_customer' => 7]);
        Stubs::object('Customer', 7);
        Stubs::returns('Order::getIdByCartId', 0);
        $controller = $this->controller();

        $url = $this->expectRedirect(function () use ($controller) {
            self::invoke($controller, 'finishSuccess', [6]);
        });

        self::assertSame('index.php?controller=order&step=1', $url);
        self::assertContains('Missing cart/order/customer on successful return', $this->logger->messages('error'));
    }

    // -------------------------------------------------------------------------
    // Failed returns
    // -------------------------------------------------------------------------

    public function testFailedReturnRestoresADuplicateOfTheOrderedCart(): void
    {
        $this->givenOrder();
        $copy = new \Cart();
        $copy->id = 77;
        Stubs::returns('Cart->duplicate', ['cart' => $copy, 'success' => true]);
        $this->returnRequest(['brq_statuscode' => '490', 'brq_statusmessage' => 'Card declined']);
        $controller = $this->controller();

        $url = $this->handleReturn($controller);

        self::assertSame($this->errorUrl('Card declined'), $url);
        self::assertSame(77, $this->context()->cookie->id_cart);
        self::assertSame('Card declined', $this->context()->cookie->buckaroo_error_msg);
        self::assertSame(['Payment failed'], $this->logger->messages('error'));
    }

    public function testFailedDuplicationIsLogged(): void
    {
        $this->givenOrder();
        Stubs::returns('Cart->duplicate', ['cart' => new \Cart(), 'success' => false]);
        $this->returnRequest(['brq_statuscode' => '490']);
        $controller = $this->controller();

        $url = $this->handleReturn($controller);

        self::assertSame($this->errorUrl(self::DEFAULT_ERROR), $url, 'no gateway message: default message');
        self::assertFalse($this->context()->cookie->id_cart);
        self::assertContains('Cart duplication failed', $this->logger->messages('error'));
    }

    public function testFailedReturnWithoutOrderRestoresTheSameCart(): void
    {
        Stubs::object('Cart', 5, ['id_customer' => 7]);
        Stubs::returns('Order::getIdByCartId', 0);
        $this->returnRequest(['brq_statuscode' => '890']);
        $controller = $this->controller();

        $this->handleReturn($controller);

        self::assertSame(5, $this->context()->cookie->id_cart);
        self::assertSame(0, Stubs::callCount('Cart->duplicate'));
    }

    public function testCartOfAnotherCustomerIsNotRestored(): void
    {
        $this->givenOrder();
        Stubs::object('Cart', 5, ['id_customer' => 99]);
        $this->returnRequest(['brq_statuscode' => '490', 'brq_statusmessage' => 'Declined']);
        $controller = $this->controller();

        $url = $this->handleReturn($controller);

        self::assertSame($this->errorUrl('Declined'), $url);
        self::assertFalse($this->context()->cookie->id_cart);
        self::assertSame(0, Stubs::callCount('Cart->duplicate'));
        self::assertContains('Cart does not belong to the current customer; cart not restored', $this->logger->messages('error'));
    }

    public function testGuestWithoutCustomerCannotRestoreACart(): void
    {
        Stubs::object('Cart', 5, ['id_customer' => 0]);
        $this->context()->customer->id = 0;
        Stubs::returns('Order::getIdByCartId', 0);
        $this->returnRequest(['brq_statuscode' => '490']);
        $controller = $this->controller();

        $this->handleReturn($controller);

        self::assertFalse($this->context()->cookie->id_cart);
    }

    public function testPendingPaypalReturnIsTreatedAsCancelled(): void
    {
        $this->givenOrder();
        $this->returnRequest(['brq_statuscode' => '791', 'brq_payment_method' => 'paypal', 'brq_statusmessage' => '']);
        $controller = $this->controller();

        $url = $this->handleReturn($controller);

        self::assertSame($this->errorUrl(self::DEFAULT_ERROR), $url);
        self::assertSame([], Stubs::callsTo('OrderHistory->changeIdOrderState'));
    }

    public function testTamperedReturnIsTreatedAsFailed(): void
    {
        $this->givenOrder();
        $this->returnRequest(['brq_statuscode' => '190', 'brq_statusmessage' => ''], true);
        $controller = $this->controller();

        $url = $this->handleReturn($controller);

        self::assertSame($this->errorUrl(self::DEFAULT_ERROR), $url);
        self::assertSame([], Stubs::callsTo('OrderHistory->changeIdOrderState'));
        self::assertSame([], Stubs::persisted('OrderPayment'));
        self::assertContains('Payment failed or invalid response', $this->logger->messages('error'));
    }

    /** Regression: the unsigned brq_statusmessage of an invalid return reached the shopper. */
    public function testInvalidReturnDoesNotShowTheUnsignedStatusMessage(): void
    {
        $this->givenOrder();
        $this->returnRequest(['brq_statuscode' => '490', 'brq_statusmessage' => '<img src=x onerror=alert(1)>'], true);
        $controller = $this->controller();

        $url = $this->handleReturn($controller);

        self::assertSame($this->errorUrl(self::DEFAULT_ERROR), $url);
        self::assertSame(self::DEFAULT_ERROR, $this->context()->cookie->buckaroo_error_msg);
    }

    /** Without a session the message travels in the signed state: an unsigned one must not be signed into it. */
    public function testCookielessInvalidReturnDoesNotSignTheUnsignedStatusMessage(): void
    {
        $this->givenOrder();
        $this->context()->cookie = new SessionlessCookie();
        $this->returnRequest(['brq_statuscode' => '490', 'brq_statusmessage' => 'Call +31 900 FRAUD'], true);
        $controller = $this->controller();
        $controller->init();

        $url = $this->handleReturn($controller);

        self::assertSame(['cartId' => 5, 'outcome' => 'failure', 'message' => self::DEFAULT_ERROR], $this->stateFrom($url));
    }

    public function testInvalidReturnWithoutMessageUsesTheDefaultMessage(): void
    {
        $this->request([], ['brq_invoicenumber' => 'REF123_0']);
        $controller = $this->controller();

        self::assertSame($this->errorUrl(self::DEFAULT_ERROR), $this->handleReturn($controller));
    }

    public function testCheckoutErrorStillRedirectsWhenTheCookieCannotBeWritten(): void
    {
        $this->context()->cookie = new class extends \Cookie {
            public function write()
            {
                throw new \RuntimeException('headers sent');
            }
        };
        $controller = $this->controller();

        $url = $this->expectRedirect(function () use ($controller) {
            self::invoke($controller, 'redirectToCheckoutError', ['Oops']);
        });

        self::assertSame($this->errorUrl('Oops'), $url);
    }

    // -------------------------------------------------------------------------
    // Return without session: signed state hand-over
    // -------------------------------------------------------------------------

    public function testCookielessSuccessHandsTheOutcomeToASignedSameSiteRequest(): void
    {
        $this->givenOrder();
        $this->context()->cookie = new SessionlessCookie();
        $this->returnRequest(['brq_statuscode' => '190']);
        $controller = $this->controller();
        $controller->init();

        $url = $this->handleReturn($controller);

        self::assertSame(['cartId' => 5, 'outcome' => 'success', 'message' => ''], $this->stateFrom($url));
        self::assertSame([[self::PAID, 42, true]], Stubs::callsTo('OrderHistory->changeIdOrderState'), 'the order is updated before the hand-over');
        self::assertSame([], Stubs::persisted('Cart', 'delete'), 'no session work without the session');
    }

    public function testCookielessFailureCarriesTheMessageInTheSignedState(): void
    {
        $this->givenOrder();
        $this->context()->cookie = new SessionlessCookie();
        $this->returnRequest(['brq_statuscode' => '490', 'brq_statusmessage' => 'Declined']);
        $controller = $this->controller();
        $controller->init();

        $url = $this->handleReturn($controller);

        self::assertSame(['cartId' => 5, 'outcome' => 'failure', 'message' => 'Declined'], $this->stateFrom($url));
        self::assertFalse($this->context()->cookie->id_cart);
    }

    public function testSignedSuccessStateShowsTheConfirmation(): void
    {
        $this->givenOrder();
        $controller = $this->controller();

        $url = $this->expectRedirect(function () use ($controller) {
            self::invoke($controller, 'handleSignedState', [$this->signer()->sign(5, ReturnStateSigner::OUTCOME_SUCCESS)]);
        });

        self::assertSame($this->confirmationUrl(), $url);
    }

    /**
     * @dataProvider failureStates
     */
    public function testSignedFailureStateRestoresTheCartAndShowsTheMessage(string $message, string $expected): void
    {
        Stubs::object('Cart', 5, ['id_customer' => 7]);
        Stubs::returns('Order::getIdByCartId', 0);
        $controller = $this->controller();

        $url = $this->expectRedirect(function () use ($controller, $message) {
            self::invoke($controller, 'handleSignedState', [$this->signer()->sign(5, ReturnStateSigner::OUTCOME_FAILURE, $message)]);
        });

        self::assertSame($this->errorUrl($expected), $url);
        self::assertSame(5, $this->context()->cookie->id_cart);
    }

    public function failureStates(): array
    {
        return [
            'gateway message' => ['Declined', 'Declined'],
            'no message' => ['', self::DEFAULT_ERROR],
        ];
    }

    /**
     * @dataProvider forgedStates
     */
    public function testForgedOrExpiredStateIsRejected(string $state): void
    {
        $this->givenOrder();
        $controller = $this->controller();

        $url = $this->expectRedirect(function () use ($controller, $state) {
            self::invoke($controller, 'handleSignedState', [$state]);
        });

        self::assertSame($this->errorUrl(self::DEFAULT_ERROR), $url);
        self::assertSame(['Invalid or expired return state'], $this->logger->messages('error'));
        self::assertFalse($this->context()->cookie->id_cart);
    }

    public function forgedStates(): array
    {
        $other = new ReturnStateSigner('another-shop-key');
        $expired = new ReturnStateSigner(_COOKIE_KEY_, -10);

        return [
            'malformed' => ['not-a-state'],
            'signed with another key' => [$other->sign(5, ReturnStateSigner::OUTCOME_SUCCESS)],
            'expired' => [$expired->sign(5, ReturnStateSigner::OUTCOME_SUCCESS)],
        ];
    }

    // -------------------------------------------------------------------------
    // initContent(): dispatch and fatal errors
    // -------------------------------------------------------------------------

    /** @return string[] every redirect target, in order */
    private function runInitContent(\Buckaroo3UserreturnModuleFrontController $controller): array
    {
        try {
            $controller->initContent();
        } catch (RedirectException $redirect) {
            return array_map(function ($args) {
                return $args[0];
            }, Stubs::callsTo('Tools::redirect'));
        }
        self::fail('Expected a redirect');
    }

    public function testInitContentUsesTheSignedStateWhenPresent(): void
    {
        $this->givenOrder();
        $this->request(['bk_state' => $this->signer()->sign(5, ReturnStateSigner::OUTCOME_SUCCESS)]);
        $controller = $this->controller();

        $redirects = $this->runInitContent($controller);

        self::assertSame($this->confirmationUrl(), $redirects[0]);
        self::assertSame(1, Stubs::callCount('Order::getIdByCartId'), 'only finishSuccess looks up the order');
        self::assertSame([], Stubs::callsTo('OrderHistory->changeIdOrderState'), 'the state carries no payment result');
    }

    public function testInitContentHandlesTheGatewayReply(): void
    {
        $this->givenOrder();
        $this->returnRequest(['brq_statuscode' => '490', 'brq_statusmessage' => 'Declined']);
        $controller = $this->controller();

        $redirects = $this->runInitContent($controller);

        self::assertSame($this->errorUrl('Declined'), $redirects[0]);
    }

    public function testInitContentTurnsAnUnexpectedErrorIntoTheCheckoutError(): void
    {
        Stubs::throws('Order::getIdByCartId', new \RuntimeException('database gone'));
        $this->returnRequest(['brq_statuscode' => '190']);
        $controller = $this->controller();

        $redirects = $this->runInitContent($controller);

        self::assertSame([$this->errorUrl(self::DEFAULT_ERROR)], $redirects);
        self::assertSame(['User return fatal: database gone'], $this->logger->messages('error'));
    }
}
