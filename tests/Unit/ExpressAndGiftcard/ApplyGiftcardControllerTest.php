<?php

namespace BuckarooTest\Unit\ExpressAndGiftcard;

use Buckaroo\PrestaShop\Src\Service\BuckarooPaymentService;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;

require_once __DIR__ . '/ExpressTestSupport.php';

/**
 * "Apply giftcard": a direct Buckaroo giftcard payment that either pays the
 * whole cart (the order is created) or leaves a remainder for another method.
 *
 * The module's real GiftCard request and BuckarooClient run; only the SDK
 * giftcard method is replaced (GiftcardSdk), so nothing reaches the network.
 */
class ApplyGiftcardControllerTest extends TestCase
{
    private const PENDING_STATE = 11;
    private const PAID_STATE = 12;
    private const ORDER_ID = 42;

    /** @var ExpressTestModule */
    private $module;

    /** @var BuckarooPaymentService&\PHPUnit\Framework\MockObject\MockObject */
    private $paymentService;

    /** @var \Cart */
    private $cart;

    /** @var array<int, array<string, mixed>> rows of the fake bk_group_transaction table */
    private $rows = [];

    /** @var bool */
    private $giftcardAvailable = true;

    /** @var string */
    private $allowedCode = 'boekenbon';

    protected function setUp(): void
    {
        parent::setUp();

        $this->module = new ExpressTestModule();
        $this->paymentService = $this->createMock(BuckarooPaymentService::class);
        $this->paymentService->method('isMethodAvailableForCart')->willReturnCallback(function ($cart, $method) {
            return $method === 'giftcard' && $this->giftcardAvailable;
        });
        $this->paymentService->method('resolveAllowedGiftcardCode')->willReturnCallback(function ($code) {
            return strtolower($code) === $this->allowedCode ? $this->allowedCode : '';
        });
        $this->module->services['buckaroo.config.api.payment.service'] = $this->paymentService;
        \Module::$instances['buckaroo3'] = $this->module;

        $this->cart = new \Cart();
        $this->cart->id = 5;
        $this->cart->id_customer = 4;
        $this->cart->totals = [\Cart::BOTH => 50.0];
        $this->context()->cart = $this->cart;
        $this->context()->currency->iso_code = 'EUR';
        Stubs::object('Customer', 4, ['secure_key' => 'sk4']);

        \Configuration::updateValue('BUCKAROO_MERCHANT_KEY', 'merchant-key');
        \Configuration::updateValue('BUCKAROO_SECRET_KEY', 'secret-key');
        \Configuration::updateValue('BUCKAROO_ORDER_STATE_DEFAULT', self::PENDING_STATE);
        \Configuration::updateValue('BUCKAROO_ORDER_STATE_SUCCESS', self::PAID_STATE);
        Stubs::object('OrderState', self::PAID_STATE, []);

        // Fake bk_group_transaction table, the gateway mode of the giftcard method.
        Stubs::db('/ps_bk_group_transaction/', function ($sql, $method, $args) {
            if ($method === 'insert') {
                $this->rows[] = $args[1] + ['created_at' => count($this->rows)];

                return true;
            }
            if ($method === 'executeS') {
                return array_values(array_filter($this->rows, function ($row) {
                    return (int) $row['status'] === 190;
                }));
            }

            return true;
        });
        Stubs::db('/bk_payment_methods/', '7');
        Stubs::db('/bk_configuration/', json_encode(['mode' => 'test']));

        Stubs::returns('OrderPayment::getByOrderReference', []);
    }

    protected function tearDown(): void
    {
        GiftcardSdk::uninstall();
        parent::tearDown();
    }

    private function row(float $amount, string $group = '', int $status = 190, float $refunded = 0.0): void
    {
        $this->rows[] = [
            'cart_id' => 5,
            'transaction_key' => 'OLDKEY' . count($this->rows),
            'group_transaction_id' => $group,
            'amount' => $amount,
            'refunded_amount' => $refunded,
            'card_code' => 'vvvgiftcard',
            'status' => $status,
            'created_at' => count($this->rows),
        ];
    }

    private function card(string $code = 'boekenbon'): void
    {
        $this->request([], ['giftcard_card_number' => '6064361234567890', 'giftcard_security_code' => '1234', 'cardCode' => $code]);
    }

    /** Run the controller and return the redirect URL. */
    private function apply(): string
    {
        $controller = new \Buckaroo3ApplygiftcardModuleFrontController();

        return $this->expectRedirect(function () use ($controller) {
            $controller->postProcess();
        });
    }

    /** @return array<string, string> query of a checkout redirect, messages decoded */
    private static function query(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        foreach (['buckaroo_error_msg', 'buckaroo_giftcard_msg'] as $key) {
            if (isset($query[$key])) {
                $query[$key] = urldecode($query[$key]);
            }
        }

        return $query;
    }

    private function assertCheckoutError(string $url, string $message): void
    {
        self::assertStringStartsWith('https://shop.test/order?', $url);
        self::assertSame(['step' => '3', 'buckaroo_error_msg' => $message, 'buckaroo_error' => '1'], self::query($url));
    }

    /** Register the order validateOrder() creates. */
    private function orderIsCreated(array $props = []): void
    {
        Stubs::returns('PaymentModule->validateOrder', function () {
            $this->module->currentOrder = self::ORDER_ID;

            return true;
        });
        Stubs::object('Order', self::ORDER_ID, $props + [
            'id_cart' => 5,
            'reference' => 'GIFTREF',
            'total_paid' => 50.0,
            'total_paid_real' => 0.0,
            'current_state' => self::PENDING_STATE,
        ]);
    }

    // ------------------------------------------------------------------
    // Request checks
    // ------------------------------------------------------------------

    public function testAnExpiredSessionIsSentBackToCheckout(): void
    {
        $this->context()->cart = new \Cart();

        $this->assertCheckoutError($this->apply(), 'Your shopping session expired. Please start the checkout again.');
    }

    public function testACartWithoutCustomerIsSentBackToCheckout(): void
    {
        $this->cart->id_customer = 0;

        $this->assertCheckoutError($this->apply(), 'Your shopping session expired. Please start the checkout again.');
    }

    public function testCardNumberAndPinAreRequired(): void
    {
        $this->request([], ['giftcard_card_number' => '6064', 'giftcard_security_code' => '']);

        $this->assertCheckoutError($this->apply(), 'Please fill in all giftcard fields.');
        self::assertSame([], GiftcardSdk::$calls);
    }

    public function testWithoutCardDetailsAnAppliedCardAsksForTheRemainder(): void
    {
        $this->context()->cookie->buckaroo_giftcard_applied = '20';
        $this->context()->cookie->buckaroo_giftcard_remainder = '30.5';
        $this->context()->currency->sign = '€';

        $query = self::query($this->apply());

        self::assertSame('3', $query['step']);
        self::assertSame('1', $query['buckaroo_giftcard_applied']);
        self::assertSame('Giftcard applied (€20.00). Please pay the remaining €30.50 with another payment method.', $query['buckaroo_giftcard_msg']);
    }

    public function testTheRemainderMessageUsesTheShopLocale(): void
    {
        $this->context()->cookie->buckaroo_giftcard_applied = '20';
        $this->context()->cookie->buckaroo_giftcard_remainder = '30';
        $this->context()->currentLocale = \Tools::getContextLocale($this->context());

        $query = self::query($this->apply());

        self::assertSame('Giftcard applied (EUR 20.00). Please pay the remaining EUR 30.00 with another payment method.', $query['buckaroo_giftcard_msg']);
    }

    public function testACartCoveredByAnExistingOrderIsNotChargedAgain(): void
    {
        $this->card();
        $this->row(50.0);
        Stubs::returns('Order::getIdByCartId', 99);

        $this->assertCheckoutError($this->apply(), 'The order is already fully covered by giftcard(s).');
        self::assertSame(0, Stubs::callCount('PaymentModule->validateOrder'));
    }

    public function testAnEmptyCartTotalIsNotCharged(): void
    {
        $this->card();
        $this->cart->totals = [\Cart::BOTH => 0.0];

        $this->assertCheckoutError($this->apply(), 'The order is already fully covered by giftcard(s).');
    }

    public function testGiftcardsMustBeAvailableForTheCart(): void
    {
        $this->card();
        $this->giftcardAvailable = false;

        $this->assertCheckoutError($this->apply(), 'Giftcards are not available for this order.');
    }

    public function testTheCardMustBeOneTheMerchantEnabled(): void
    {
        $this->card('fashioncheque');

        $this->assertCheckoutError($this->apply(), 'The selected giftcard is not accepted. Please choose another giftcard.');
    }

    public function testAGatewayErrorIsReportedWithoutDetails(): void
    {
        $this->card();
        \Configuration::deleteByName('BUCKAROO_SECRET_KEY');

        $this->assertCheckoutError($this->apply(), 'The giftcard could not be applied. Please try again.');
        self::assertSame([], $this->rows);
    }

    /** Regression: without a merchant key the SDK client throws an Error, not an Exception. */
    public function testAMissingMerchantKeyIsReportedInsteadOfAFatalError(): void
    {
        $this->card();
        \Configuration::deleteByName('BUCKAROO_MERCHANT_KEY');

        $this->assertCheckoutError($this->apply(), 'The giftcard could not be applied. Please try again.');
        self::assertSame([], $this->rows);
    }

    public function testAnErrorOfTheSdkCallIsReported(): void
    {
        $this->card();
        GiftcardSdk::install(new \RuntimeException('timeout'));

        $this->assertCheckoutError($this->apply(), 'The giftcard could not be applied. Please try again.');
    }

    // ------------------------------------------------------------------
    // Buckaroo request and response
    // ------------------------------------------------------------------

    public function testTheGiftcardIsChargedForTheRemainingAmount(): void
    {
        $this->card();
        $this->row(12.5, '', 190, 2.5);
        GiftcardSdk::install(GiftcardSdk::reply(490));

        $this->apply();

        self::assertCount(1, GiftcardSdk::$calls);
        $call = GiftcardSdk::$calls[0];
        self::assertSame('pay', $call['action']);
        $payload = $call['payload'];
        self::assertSame('40', $payload['amountDebit'], 'cart total minus the net amount already paid');
        self::assertSame('EUR', $payload['currency']);
        self::assertSame('gc_5', $payload['order']);
        self::assertMatchesRegularExpression('/^gc_\d+_5$/', $payload['invoice']);
        self::assertSame('https://shop.test/module/buckaroo3/userreturn', $payload['returnURL']);
        self::assertSame('https://shop.test/module/buckaroo3/return', $payload['pushURL']);
        self::assertSame('5.4.0', $payload['moduleVersion']);
        self::assertSame('6064361234567890', $payload['intersolveCardnumber']);
        self::assertSame('1234', $payload['intersolvePIN']);
        self::assertSame('boekenbon', $payload['name']);
        self::assertArrayNotHasKey('originalTransactionKey', $payload);
    }

    public function testAnotherCardContinuesTheGroupTransactionOfTheCookie(): void
    {
        $this->card();
        $this->context()->cookie->buckaroo_giftcard_cart_id = '5';
        $this->context()->cookie->buckaroo_giftcard_group_tx = 'GROUP-COOKIE';
        $this->row(10.0, 'GROUP-DB');
        GiftcardSdk::install(GiftcardSdk::reply(490));

        $this->apply();

        self::assertSame('payRemainder', GiftcardSdk::$calls[0]['action']);
        self::assertSame('GROUP-COOKIE', GiftcardSdk::$calls[0]['payload']['originalTransactionKey']);
    }

    public function testAnotherCardContinuesTheGroupTransactionOfTheDatabase(): void
    {
        $this->card();
        $this->context()->cookie->buckaroo_giftcard_cart_id = '6';
        $this->context()->cookie->buckaroo_giftcard_group_tx = 'GROUP-OTHER-CART';
        $this->row(5.0, '');
        $this->row(10.0, 'GROUP-DB');
        GiftcardSdk::install(GiftcardSdk::reply(490));

        $this->apply();

        self::assertSame('GROUP-DB', GiftcardSdk::$calls[0]['payload']['originalTransactionKey']);
    }

    public function testARejectedCardShowsTheGatewayMessage(): void
    {
        $this->card();
        GiftcardSdk::install(GiftcardSdk::reply(490, null, '', 'TXKEY1', [
            'Status' => ['Code' => ['Code' => 490, 'Description' => 'Failed'], 'SubCode' => ['Code' => 'S101', 'Description' => 'Insufficient balance']],
        ]));

        $this->assertCheckoutError($this->apply(), 'Insufficient balance');
        self::assertSame([], $this->rows, 'nothing is recorded');
    }

    public function testARejectedCardWithoutMessageShowsTheDefault(): void
    {
        $this->card();
        GiftcardSdk::install(GiftcardSdk::reply(490, null, '', 'TXKEY1', [
            'Status' => ['Code' => ['Code' => 490, 'Description' => 'Failed']],
        ]));

        $this->assertCheckoutError($this->apply(), 'The giftcard could not be applied. Please check your card details and try again.');
    }

    public function testAPendingCardIsRecordedButNotCounted(): void
    {
        $this->card();
        GiftcardSdk::install(GiftcardSdk::reply(791, null, '', 'PENDINGKEY'));

        $this->assertCheckoutError($this->apply(), 'Your giftcard payment is still being processed. Please try again in a few minutes or choose another payment method.');

        self::assertCount(1, $this->rows);
        self::assertSame('PENDINGKEY', $this->rows[0]['transaction_key']);
        self::assertSame(791, $this->rows[0]['status']);
        self::assertSame(50.0, $this->rows[0]['amount']);
        self::assertSame('boekenbon', $this->rows[0]['card_code']);
        self::assertFalse(isset($this->context()->cookie->buckaroo_giftcard_applied));
        self::assertSame(0, Stubs::callCount('PaymentModule->validateOrder'));
    }

    public function testAPendingCardWithoutTransactionKeyIsNotRecorded(): void
    {
        $this->card();
        GiftcardSdk::install(GiftcardSdk::reply(791, null, '', ''));

        $this->apply();

        self::assertSame([], $this->rows);
    }

    public function testAPartialCardStoresTheGroupTransactionAndAsksForTheRemainder(): void
    {
        $this->card();
        $this->context()->currency->sign = '€';
        GiftcardSdk::install(GiftcardSdk::reply(791, 32.5, 'GROUP-1', 'PARTKEY'));

        $query = self::query($this->apply());

        self::assertSame('Giftcard applied (€17.50). Please pay the remaining €32.50 with another payment method.', $query['buckaroo_giftcard_msg']);
        self::assertCount(1, $this->rows);
        self::assertSame(17.5, $this->rows[0]['amount']);
        self::assertSame(190, $this->rows[0]['status'], 'the debited part counts as paid');
        self::assertSame('GROUP-1', $this->rows[0]['group_transaction_id']);

        $cookie = $this->context()->cookie;
        self::assertSame('GROUP-1', $cookie->buckaroo_giftcard_group_tx);
        self::assertSame('32.5', $cookie->buckaroo_giftcard_remainder);
        self::assertSame('17.5', $cookie->buckaroo_giftcard_applied);
        self::assertSame('PARTKEY', $cookie->buckaroo_giftcard_tx_key);
        self::assertSame('boekenbon', $cookie->buckaroo_giftcard_card_code);
        self::assertSame('5', $cookie->buckaroo_giftcard_cart_id);
        self::assertSame(1, $cookie->writes);
        self::assertSame(0, Stubs::callCount('PaymentModule->validateOrder'));
    }

    public function testASecondPartialCardAddsToTheAppliedTotalAndKeepsTheGroup(): void
    {
        $this->card();
        $this->row(10.0, 'GROUP-1');
        GiftcardSdk::install(GiftcardSdk::reply(190, 25.0, '', 'KEY2'));

        $this->apply();

        $new = $this->rows[1];
        self::assertSame(15.0, $new['amount'], '40 charged, 25 left');
        self::assertSame('GROUP-1', $new['group_transaction_id'], 'the existing group is kept');
        self::assertSame('25', $this->context()->cookie->buckaroo_giftcard_remainder);
        self::assertSame('25', $this->context()->cookie->buckaroo_giftcard_applied, 'all cards together');
    }

    // ------------------------------------------------------------------
    // Order creation
    // ------------------------------------------------------------------

    public function testACardCoveringTheCartCreatesAPaidOrder(): void
    {
        $this->card();
        $this->orderIsCreated();
        $this->context()->cookie->buckaroo_giftcard_cart_id = '5';
        GiftcardSdk::install(GiftcardSdk::reply(190, null, 'GROUP-9', 'FULLKEY'));

        $url = $this->apply();

        self::assertSame([[5, self::PENDING_STATE, 50.0, 'Giftcard', null, [], 1, false, 'sk4']], Stubs::callsTo('PaymentModule->validateOrder'));
        self::assertSame(50.0, $this->rows[0]['amount']);

        $payments = Stubs::persisted('OrderPayment', 'save');
        self::assertCount(1, $payments);
        self::assertSame('GIFTREF', $payments[0]->order_reference);
        self::assertSame(50.0, $payments[0]->amount);
        self::assertSame('boekenbon', $payments[0]->payment_method);
        self::assertSame('FULLKEY', $payments[0]->transaction_id);
        self::assertStringContainsString('UPDATE `ps_bk_group_transaction` {"order_id":42}', implode("\n", Stubs::sql('update')));

        self::assertSame([[self::PAID_STATE, self::ORDER_ID, true]], Stubs::callsTo('OrderHistory->changeIdOrderState'));
        self::assertSame([[true]], Stubs::callsTo('OrderHistory->addWithemail'));

        foreach (['group_tx', 'remainder', 'applied', 'tx_key', 'card_code', 'cart_id'] as $suffix) {
            self::assertFalse(isset($this->context()->cookie->{'buckaroo_giftcard_' . $suffix}), $suffix . ' cookie is cleared');
        }

        self::assertStringStartsWith('https://shop.test/order-confirmation?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        self::assertSame(['id_cart' => '5', 'id_module' => '7', 'id_order' => '42', 'key' => 'sk4', 'success' => 'true'], $query);
    }

    public function testCardsConfirmedByPushCompleteTheOrderWithoutCharging(): void
    {
        $this->card();
        $this->row(30.0, 'G');
        $this->row(20.0, 'G');
        $this->orderIsCreated();

        $url = $this->apply();

        self::assertSame([], GiftcardSdk::$calls);
        self::assertSame(50.0, Stubs::callsTo('PaymentModule->validateOrder')[0][2]);
        self::assertCount(2, Stubs::persisted('OrderPayment', 'save'));
        self::assertSame(self::PAID_STATE, Stubs::callsTo('OrderHistory->changeIdOrderState')[0][0]);
        self::assertStringContainsString('order-confirmation', $url);
    }

    public function testAnOrderIsLeftPendingUntilTheCardsAreConfirmed(): void
    {
        $this->card();
        $this->row(50.0, 'G');
        $this->orderIsCreated(['total_paid' => 60.0]);

        $url = $this->apply();

        self::assertSame([], Stubs::callsTo('OrderHistory->changeIdOrderState'));
        self::assertStringContainsString('order-confirmation', $url);
    }

    public function testAFailedOrderCreationIsShownInCheckout(): void
    {
        $this->card();
        $this->row(50.0, 'G');
        Stubs::throws('PaymentModule->validateOrder', new \Exception('Cart cannot be loaded'));

        $this->assertCheckoutError($this->apply(), 'Cart cannot be loaded');
        self::assertSame([], Stubs::persisted('OrderPayment'));
    }
}
