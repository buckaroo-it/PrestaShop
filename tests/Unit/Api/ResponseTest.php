<?php

namespace BuckarooTest\Unit\Api;

use Buckaroo\Transaction\Response\TransactionResponse;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;

require_once __DIR__ . '/ApiTestSupport.php';

/**
 * Parsing of Buckaroo replies by api/paymentmethods/response.php: direct
 * transaction responses and signed push/return requests.
 */
class ResponseTest extends TestCase
{
    private const WEBSITE_KEY = 'TESTWEBSITEKEY';
    private const SECRET_KEY = 'a-test-secret-key-not-a-real-one';

    protected function setUp(): void
    {
        parent::setUp();
        ApiFixtures::load();
        \Configuration::updateValue('BUCKAROO_MERCHANT_KEY', self::WEBSITE_KEY);
        \Configuration::updateValue('BUCKAROO_SECRET_KEY', self::SECRET_KEY);
    }

    // ------------------------------------------------------------------
    // Direct transaction responses
    // ------------------------------------------------------------------

    /**
     * @dataProvider statusCodes
     */
    public function testStatusOfADirectResponse(int $code, string $true, bool $succeeded, bool $paid): void
    {
        $response = new \ResponseDefault(new TransactionResponse(null, ApiFixtures::reply($code)));

        $checks = ['isSuccess', 'isFailed', 'isCanceled', 'isAwaitingConsumer', 'isPendingProcessing', 'isWaitingOnUserInput', 'isRejected'];
        foreach ($checks as $check) {
            self::assertSame($check === $true, $response->$check(), $check . ' for ' . $code);
        }
        self::assertSame($succeeded, $response->hasSucceeded());
        self::assertSame($paid, $response->isPaid());
        self::assertSame($code, $response->getStatusCodeValue());
    }

    public function statusCodes(): array
    {
        return [
            'success' => [190, 'isSuccess', true, true],
            'failed' => [490, 'isFailed', false, false],
            'rejected' => [690, 'isRejected', false, false],
            'waiting on user input' => [790, 'isWaitingOnUserInput', true, false],
            'pending processing' => [791, 'isPendingProcessing', true, false],
            'awaiting consumer' => [792, 'isAwaitingConsumer', true, false],
            'cancelled by user' => [890, 'isCanceled', false, false],
            'cancelled by merchant' => [891, 'isCanceled', false, false],
        ];
    }

    public function testValidationFailureIsNotValid(): void
    {
        $response = new \ResponseDefault(new TransactionResponse(null, ApiFixtures::reply(491)));

        self::assertFalse($response->isValid());
        self::assertFalse($response->hasSucceeded());
    }

    public function testDirectResponseDataIsExposed(): void
    {
        $data = ApiFixtures::reply(791, [
            'RequiredAction' => ['Name' => 'Redirect', 'RedirectURL' => 'https://checkout.buckaroo.nl/pay/abc'],
        ]);
        $transaction = new TransactionResponse(null, $data);
        $response = new \ResponseDefault($transaction);

        self::assertSame($transaction, $response->getResponse());
        self::assertTrue($response->isRedirectRequired());
        self::assertSame('https://checkout.buckaroo.nl/pay/abc', $response->getRedirectUrl());
        self::assertSame(['consumeriban' => 'NL44RABO0123456789', 'consumername' => 'J. de Tester'], $response->getServiceParameters());
        self::assertTrue($response->isTest());
        self::assertFalse($response->isPartialPayment());
        self::assertNull($response->getGroupTransaction());
        self::assertSame(0, $response->getRemainderAmount());
        self::assertSame(0.0, $response->getRemainingAmount());
        // The SDK falls back to the sub code description when there is no error.
        self::assertTrue($response->hasSomeError());
        self::assertSame('The request was successful.', $response->getSomeError());
    }

    public function testErrorsOfADirectResponse(): void
    {
        $response = new \ResponseDefault(new TransactionResponse(null, ApiFixtures::reply(491, [
            'Status' => ['SubCode' => ['Description' => '']],
            'RequestErrors' => [
                'ParameterErrors' => [['Name' => 'issuer', 'ErrorMessage' => 'Parameter "issuer" is missing.']],
            ],
        ])));

        self::assertTrue($response->hasSomeError());
        self::assertSame('Parameter "issuer" is missing.', $response->getSomeError());
    }

    public function testNonScalarRemainderAmountIsZero(): void
    {
        $response = new \ResponseDefault(new TransactionResponse(null, ApiFixtures::reply(792, [
            'RequiredAction' => ['PayRemainderDetails' => ['RemainderAmount' => ['7.25']]],
        ])));

        self::assertSame(0, $response->getRemainderAmount());
    }

    /**
     * @dataProvider gatewayModes
     */
    public function testDirectResponseMustComeFromTheRequestedGateway($isTest, ?string $requested, bool $valid): void
    {
        $response = new \ResponseDefault(new TransactionResponse(null, ApiFixtures::reply(190, ['IsTest' => $isTest])));
        if ($requested !== null) {
            $response->setGatewayMode($requested);
        }

        self::assertSame($valid, $response->isValid());
    }

    public function gatewayModes(): array
    {
        return [
            'test reply, test request' => [true, 'test', true],
            'live reply, live request' => [false, 'live', true],
            'test reply, live request' => [true, 'live', false],
            'live reply, test request' => [false, 'test', false],
            'no mode requested' => [true, null, true],
            'reply without IsTest' => ['yes', 'live', true],
        ];
    }

    public function testRejectedGatewayIsLogged(): void
    {
        $file = $this->responseLog();
        $before = is_file($file) ? filesize($file) : 0;

        $response = new \ResponseDefault(new TransactionResponse(null, ApiFixtures::reply(190, ['IsTest' => true])));
        $response->setGatewayMode('live');
        self::assertFalse($response->isValid());

        $log = (string) file_get_contents($file, false, null, $before);
        self::assertStringContainsString('Response rejected: gateway environment does not match the request', $log);
        self::assertStringContainsString('[requested] => live', $log);
        self::assertStringContainsString('[response] => test', $log);
    }

    public function testExceptionWhileCheckingTheStatusIsLoggedNotThrown(): void
    {
        $response = new \ResponseDefault(new class(null, []) extends TransactionResponse {
            public function getStatusCode(): ?int
            {
                throw new \RuntimeException('broken reply');
            }
        });

        self::assertFalse($response->hasSucceeded());
        self::assertFalse($response->isPaid());
    }

    /**
     * Regression: getStatuscode(), getStatusmessage() and getAmount() read
     * properties TransactionResponse does not have (always null plus an
     * "Undefined property" warning).
     */
    public function testStatusGettersOfADirectResponse(): void
    {
        $response = new \ResponseDefault(new TransactionResponse(null, ApiFixtures::reply(190)));

        [$values, $errors] = ErrorCollector::run(function () use ($response) {
            return [$response->getStatuscode(), $response->getStatusmessage(), $response->getAmount()];
        });

        self::assertSame([190, 'Status 190', 10.5], $values);
        self::assertSame([], $errors);
    }

    public function testStatusGettersFallBackToSubCodeAndCreditAmount(): void
    {
        $response = new \ResponseDefault(new TransactionResponse(null, ApiFixtures::reply(190, [
            'Status' => ['Code' => ['Description' => '']],
            'AmountDebit' => null,
            'AmountCredit' => '4.20',
        ])));

        self::assertSame('The request was successful.', $response->getStatusmessage());
        self::assertSame(4.2, $response->getAmount());
    }

    public function testStatusGettersOfAnEmptyDirectResponse(): void
    {
        $response = new \ResponseDefault(new TransactionResponse(null, []));

        [$values, $errors] = ErrorCollector::run(function () use ($response) {
            return [$response->getStatuscode(), $response->getStatusmessage(), $response->getAmount()];
        });

        self::assertSame([null, null, null], $values);
        self::assertSame([], $errors);
    }

    // ------------------------------------------------------------------
    // Push and return requests
    // ------------------------------------------------------------------

    public function testPushRequestIsParsed(): void
    {
        $_POST = $this->signedPush([
            'brq_payment' => 'PAYKEY',
            'brq_statuscode_detail' => 'S001',
            'brq_relatedtransaction_partialpayment' => 'GROUP-1',
            'brq_transaction_type' => 'C021',
            'brq_relatedtransaction_refund' => 'REFUNDKEY',
            'brq_amount_credit' => '0.00',
            'brq_timestamp' => '2026-11-04 10:00:00',
        ]);

        $response = new \ResponseDefault();

        self::assertSame('PAYKEY', $response->payment);
        self::assertSame('ideal', $response->payment_method);
        self::assertSame('190', $response->statuscode);
        self::assertSame('Transaction successfully processed', $response->statusmessage);
        self::assertSame('S001', $response->statuscode_detail);
        self::assertSame('GROUP-1', $response->getBrqRelatedtransactionPartialpayment());
        self::assertSame('C021', $response->brq_transaction_type);
        self::assertSame('REFUNDKEY', $response->brq_relatedtransaction_refund);
        self::assertSame('XKBKNABJK_42', $response->invoice);
        self::assertSame('10.00', $response->amount);
        self::assertSame('0.00', $response->amount_credit);
        self::assertSame('EUR', $response->currency);
        self::assertSame('F6E5D4C3B2A1F6E5D4C3B2A1F6E5D4C3', $response->transactions);
        self::assertSame('BUCKAROO_SUCCESS', $response->status);
        self::assertSame('Success', $response->message);
        self::assertSame(42, $response->getCartId());
        self::assertSame('XKBKNABJK', $response->getReferenceId());
        self::assertSame(190, $response->getStatusCodeValue());
        self::assertSame(190, $response->getStatuscode());
        self::assertSame('Transaction successfully processed', $response->getStatusmessage());
        self::assertSame(10.0, $response->getAmount());
        self::assertFalse($response->isTest());
        self::assertTrue($response->isValid());
        self::assertTrue($response->isPaid());
        self::assertTrue($response->hasSucceeded());
    }

    /**
     * @dataProvider pushStatuses
     */
    public function testPushStatusIsMapped(string $code, string $status, string $message, bool $succeeded): void
    {
        $_POST = $this->signedPush(['brq_statuscode' => $code]);

        $response = new \ResponseDefault();

        self::assertSame($status, $response->status);
        self::assertSame($message, $response->message);
        self::assertSame($succeeded, $response->hasSucceeded());
        self::assertSame($code === '190', $response->isPaid());
    }

    public function pushStatuses(): array
    {
        return [
            '190' => ['190', 'BUCKAROO_SUCCESS', 'Success', true],
            '490' => ['490', 'BUCKAROO_FAILED', 'Payment failure', false],
            '491' => ['491', 'BUCKAROO_FAILED', 'Validation error', false],
            '492' => ['492', 'BUCKAROO_ERROR', 'Technical error', false],
            '690' => ['690', 'BUCKAROO_FAILED', 'Payment rejected', false],
            '790' => ['790', 'BUCKAROO_PENDING_PAYMENT', 'Waiting for user input', true],
            '791' => ['791', 'BUCKAROO_PENDING_PAYMENT', 'Waiting for processor', true],
            '792' => ['792', 'BUCKAROO_PENDING_PAYMENT', 'Waiting on consumer action', true],
            '793' => ['793', 'BUCKAROO_PENDING_PAYMENT', 'Payment on hold', true],
            '794' => ['794', 'BUCKAROO_PENDING_PAYMENT', 'Pending approval', true],
            '890' => ['890', 'BUCKAROO_CANCELED', 'Cancelled by consumer', false],
            '891' => ['891', 'BUCKAROO_FAILED', 'Cancelled by merchant', false],
        ];
    }

    /**
     * Regression: 794 (pending approval) was missing from $responseCodes.
     * An unknown code still leaves the status empty, without a PHP warning.
     */
    public function testPushWithAnUnknownStatusCodeHasNoStatus(): void
    {
        $_POST = $this->signedPush(['brq_statuscode' => '999']);

        [$response, $errors] = ErrorCollector::run(function () {
            return new \ResponseDefault();
        });

        self::assertSame([], $errors);
        self::assertNull($response->status);
        self::assertNull($response->message);
        self::assertSame(999, $response->getStatuscode());
        self::assertFalse($response->hasSucceeded());
        self::assertStringContainsString('Unknown push status code', (string) @file_get_contents($this->responseLog()));
    }

    public function testPushWithoutMessageOrAmount(): void
    {
        $_POST = $this->signedPush(['brq_statusmessage' => '', 'brq_amount' => '']);

        $response = new \ResponseDefault();

        self::assertNull($response->getStatusmessage());
        self::assertNull($response->getAmount());
    }

    /**
     * Regression: without a merchant/secret key the SDK client throws while it
     * is created; isValid() and isPaid() must answer false, not crash.
     *
     * @dataProvider missingCredentials
     */
    public function testPushWithoutCredentialsIsNotValid(string $key): void
    {
        $_POST = $this->signedPush([]);
        \Configuration::updateValue($key, '');

        $response = new \ResponseDefault();

        self::assertFalse($response->isValid());
        self::assertFalse($response->isPaid());
        self::assertStringContainsString('Push request validation failed', (string) file_get_contents($this->responseLog()));
    }

    public function missingCredentials(): array
    {
        return [
            'merchant key' => ['BUCKAROO_MERCHANT_KEY'],
            'secret key' => ['BUCKAROO_SECRET_KEY'],
        ];
    }

    /**
     * @dataProvider paymentMethods
     */
    public function testPaymentMethodOfThePush(array $fields, ?string $expected): void
    {
        $_POST = $this->signedPush($fields);

        self::assertSame($expected, (new \ResponseDefault())->payment_method);
    }

    public function paymentMethods(): array
    {
        return [
            'payment method' => [['brq_payment_method' => 'paypal'], 'paypal'],
            'capayable is in3' => [['brq_payment_method' => 'Capayable'], 'in3'],
            'transaction method' => [['brq_payment_method' => '', 'brq_transaction_method' => 'Capayable'], 'in3'],
            'none' => [['brq_payment_method' => ''], null],
        ];
    }

    public function testTamperedPushIsNotValid(): void
    {
        $_POST = $this->signedPush([]);
        $_POST['brq_amount'] = '0.01';

        $response = new \ResponseDefault();

        self::assertFalse($response->isValid());
        self::assertFalse($response->isPaid());
        // hasSucceeded() of a push only looks at the parsed status.
        self::assertTrue($response->hasSucceeded());
    }

    /**
     * @dataProvider replyEnvironments
     */
    public function testPushMustComeFromTheGatewayThePaymentStartedOn(string $brqTest, array $started, bool $valid): void
    {
        Stubs::db('/FROM `ps_bk_transaction_mode`/', array_map(function ($mode) {
            return ['mode' => $mode];
        }, $started));
        $_POST = $this->signedPush(['brq_test' => $brqTest]);

        $response = new \ResponseDefault();

        self::assertSame($valid, $response->isValid());
        self::assertSame($brqTest === 'true', $response->isTest());
        self::assertStringContainsString("`invoice` = 'XKBKNABJK_42'", Stubs::sql('executeS')[0]);
    }

    public function replyEnvironments(): array
    {
        return [
            'test reply for a test payment' => ['true', ['test'], true],
            'live reply for a live payment' => ['false', ['live'], true],
            'test reply for a live payment' => ['true', ['live'], false],
            'live reply for a test payment' => ['false', ['test'], false],
            'unknown payment' => ['true', [], true],
        ];
    }

    public function testValidationIsCachedUntilTheGatewayModeChanges(): void
    {
        $_POST = $this->signedPush([]);
        $response = new \ResponseDefault();

        self::assertTrue($response->isValid());
        self::assertTrue($response->isValid());
        self::assertCount(1, Stubs::sql('executeS'));
    }

    public function testMalformedPushIsNotValid(): void
    {
        $_POST = $this->signedPush([]);
        $_POST['brq_extra'] = ['not', 'a', 'string'];

        $response = new \ResponseDefault();

        self::assertFalse($response->isValid());
    }

    public function testReturnWithoutStatusCodeIsNotAPush(): void
    {
        $_GET = ['id_cart' => '42'];

        $response = new \ResponseDefault();

        self::assertNull($response->statuscode);
        self::assertNull($response->status);
        self::assertFalse($response->hasSucceeded());
        self::assertFalse($response->isValid());
        self::assertSame(0, $response->getCartId());
        self::assertSame('', $response->getReferenceId());
        self::assertSame(0, $response->getStatusCodeValue());
        self::assertTrue($response->isTest());
    }

    private function responseLog(): string
    {
        return \CoreLogger::logDirectory() . 'plugin-response-log-' . date('Y-m-d') . '.txt';
    }

    /**
     * @param array<string,mixed> $fields
     *
     * @return array<string,string>
     */
    private function signedPush(array $fields): array
    {
        $data = array_merge([
            'brq_amount' => '10.00',
            'brq_currency' => 'EUR',
            'brq_invoicenumber' => 'XKBKNABJK_42',
            'brq_payment_method' => 'ideal',
            'brq_statuscode' => '190',
            'brq_statusmessage' => 'Transaction successfully processed',
            'brq_test' => 'false',
            'brq_transactions' => 'F6E5D4C3B2A1F6E5D4C3B2A1F6E5D4C3',
        ], $fields);
        $data['brq_signature'] = PushSigner::sign($data);

        return $data;
    }
}
