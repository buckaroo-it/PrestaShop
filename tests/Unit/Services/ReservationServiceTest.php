<?php

declare(strict_types=1);

namespace BuckarooTest\Unit\Services;

use Buckaroo\PrestaShop\Src\Klarna\ReservationKey;
use Buckaroo\PrestaShop\Src\Klarna\ReservationService;
use Buckaroo\PrestaShop\Src\Refund\OrderMessage;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;

final class ReservationServiceTest extends TestCase
{
    private const FAILED_STATE = 8;
    private const CANCELED_STATE = 6;

    /** @var array<string, mixed> */
    private $serverBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        \Configuration::updateValue('BUCKAROO_ORDER_STATE_FAILED', self::FAILED_STATE);
        \Configuration::updateValue('PS_OS_CANCELED', self::CANCELED_STATE);
        $this->serverBackup = $_SERVER;
        // Config::getMode('klarna') reads the method mode from the database.
        Stubs::db('/FROM `ps_bk_payment_methods`/', '7');
        Stubs::db('/FROM `ps_bk_configuration`/', '{"mode":"test"}');
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        parent::tearDown();
    }

    private function order(int $state = 2): \Order
    {
        $order = new \Order();
        $order->id = 42;
        $order->reference = 'REF42';
        $order->current_state = $state;

        return $order;
    }

    /**
     * @param string[] $messages oldest first
     * @param string[] $transactionIds
     */
    private function history(array $messages, array $transactionIds): void
    {
        Stubs::returns('Message::getMessagesByOrderId', function ($orderId, $private) use ($messages) {
            // PrestaShop returns the newest message first.
            return array_map(function ($message) {
                return ['message' => $message];
            }, array_reverse($messages));
        });
        Stubs::returns('OrderPayment::getByOrderReference', function ($reference) use ($transactionIds) {
            return array_map(function ($id) {
                $payment = new \OrderPayment();
                $payment->transaction_id = $id;

                return $payment;
            }, $transactionIds);
        });
    }

    private function service(): ReservationService
    {
        return new ReservationService(new OrderMessage());
    }

    /**
     * Point the Buckaroo SDK (Guzzle) at a local proxy port nobody listens on,
     * so a request fails immediately instead of reaching the gateway.
     */
    private function blockOutgoingRequests(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($server, false);
        fclose($server);

        $_SERVER['HTTPS_PROXY'] = 'http://' . $address;
        $_SERVER['HTTP_PROXY'] = 'http://' . $address;
        $_SERVER['NO_PROXY'] = '';
        \Configuration::updateValue('BUCKAROO_MERCHANT_KEY', 'website-key');
        \Configuration::updateValue('BUCKAROO_SECRET_KEY', 'secret-key');
    }

    public function testAdminDataShowsTheOpenReservation(): void
    {
        $this->history([ReservationKey::message('OLD'), 'note', ReservationKey::message('KEY1')], ['KEY1']);

        self::assertSame(['key' => 'KEY1', 'cancelSent' => false], $this->service()->getAdminData($this->order()));
        self::assertSame([42, true], Stubs::callsTo('Message::getMessagesByOrderId')[0]);
        self::assertSame([['REF42']], Stubs::callsTo('OrderPayment::getByOrderReference'));
    }

    public function testAdminDataRemembersThatACancelWasSent(): void
    {
        $this->history([ReservationKey::message('KEY1'), '  ' . ReservationService::CANCEL_SENT . ' '], ['KEY1']);

        self::assertSame(['key' => 'KEY1', 'cancelSent' => true], $this->service()->getAdminData($this->order()));
    }

    /**
     * @dataProvider closedReservationProvider
     */
    public function testAdminDataIsEmptyWhenTheReservationIsNotOpen(int $state, array $messages, array $transactionIds): void
    {
        $this->history($messages, $transactionIds);

        self::assertNull($this->service()->getAdminData($this->order($state)));
    }

    public function closedReservationProvider(): array
    {
        // Literal message: data providers run before coverage starts, so they must not load source classes.
        $key = ['Data Request Key: KEY1'];

        return [
            'failed order' => [self::FAILED_STATE, $key, ['KEY1']],
            'canceled order' => [self::CANCELED_STATE, $key, ['KEY1']],
            'captured: key replaced' => [2, $key, ['CAPTURE1']],
            'no key message' => [2, ['hello'], ['KEY1']],
            'no payments' => [2, $key, []],
        ];
    }

    public function testNonArrayMessagesAndPaymentsCountAsNone(): void
    {
        Stubs::returns('Message::getMessagesByOrderId', false);
        Stubs::returns('OrderPayment::getByOrderReference', null);

        self::assertNull($this->service()->getAdminData($this->order()));
    }

    public function testRowsWithoutAMessageAreIgnored(): void
    {
        Stubs::returns('Message::getMessagesByOrderId', [['id' => 1], ['message' => ReservationKey::message('KEY1')]]);
        Stubs::returns('OrderPayment::getByOrderReference', [(object) ['transaction_id' => 'KEY1']]);

        self::assertSame('KEY1', $this->service()->getAdminData($this->order())['key']);
    }

    public function testCancelIsRefusedTwice(): void
    {
        $this->history([ReservationKey::message('KEY1'), ReservationService::CANCEL_SENT], ['KEY1']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Klarna cancel reservation was already sent.');

        $this->service()->cancel($this->order());
    }

    public function testCancelRequiresAnOpenReservation(): void
    {
        $this->history([ReservationKey::message('KEY1')], ['CAPTURE']);

        try {
            $this->service()->cancel($this->order());
            self::fail('Expected the cancel to be refused');
        } catch (\RuntimeException $exception) {
            self::assertSame('This Klarna reservation is no longer open.', $exception->getMessage());
        }
        self::assertSame([], Stubs::persisted('Message'));
    }

    public function testExtendRequiresAnOpenReservation(): void
    {
        $this->history([], []);

        $this->expectExceptionMessage('This Klarna reservation is no longer open.');

        $this->service()->extend($this->order(self::CANCELED_STATE));
    }

    public function testMissingCredentialsStopTheRequestBeforeItIsSent(): void
    {
        $this->history([ReservationKey::message('KEY1')], ['KEY1']);

        try {
            $this->service()->extend($this->order());
            self::fail('Expected the SDK to refuse a client without credentials');
        } catch (\Throwable $exception) {
            self::assertStringContainsString('Config is missing', $exception->getMessage());
        }
        self::assertSame([], Stubs::persisted('Message'), 'nothing is noted when the request was not sent');
    }

    /**
     * @dataProvider actionProvider
     */
    public function testTransportFailureIsReportedAndNothingIsNoted(string $action): void
    {
        $this->history([ReservationKey::message('KEY1')], ['KEY1']);
        $this->blockOutgoingRequests();

        try {
            $this->service()->{$action}($this->order());
            self::fail('Expected the request to fail');
        } catch (\Buckaroo\Exceptions\TransferException $exception) {
            self::assertStringContainsString('Transfer failed', $exception->getMessage());
            // Refused by the local proxy: the gateway was never contacted.
            self::assertInstanceOf(\GuzzleHttp\Exception\ConnectException::class, $exception->getPrevious());
        }
        self::assertSame([], Stubs::persisted('Message'));
    }

    public function actionProvider(): array
    {
        return [
            'cancel' => ['cancel'],
            'extend' => ['extend'],
        ];
    }
}
