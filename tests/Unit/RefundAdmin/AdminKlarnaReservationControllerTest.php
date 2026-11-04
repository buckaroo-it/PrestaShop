<?php

namespace BuckarooTest\Unit\RefundAdmin;

use Buckaroo\PrestaShop\Controllers\admin\AdminKlarnaReservationController;
use Buckaroo\PrestaShop\Src\Klarna\ReservationService;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;
use Symfony\Component\HttpFoundation\Request;

require_once __DIR__ . '/StubsSupport.php';

class AdminKlarnaReservationControllerTest extends TestCase
{
    /** @var ReservationService&\PHPUnit\Framework\MockObject\MockObject */
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->createMock(ReservationService::class);
        Stubs::object('Order', 61, ['module' => 'buckaroo3']);
        Stubs::object('Order', 62, ['module' => 'ps_checkout']);
    }

    private function call(string $action, $orderId)
    {
        $controller = new AdminKlarnaReservationController($this->service);
        $response = $controller->{$action . 'Reservation'}(new Request([], ['orderId' => $orderId]));

        return [$response->data, $controller->flashes];
    }

    public function actionProvider(): array
    {
        return [['cancel', 'extend'], ['extend', 'cancel']];
    }

    /**
     * @dataProvider actionProvider
     */
    public function testActionIsDelegatedToTheReservationService(string $action, string $other): void
    {
        $this->service->expects(self::once())->method($action)->with(self::callback(function ($order) {
            return $order instanceof \Order && $order->id === 61;
        }))->willReturn('Reservation ' . $action . 'ed');
        $this->service->expects(self::never())->method($other);

        list($data, $flashes) = $this->call($action, '61');

        self::assertSame(['error' => false, 'message' => 'Reservation ' . $action . 'ed'], $data);
        self::assertSame([['success', 'Reservation ' . $action . 'ed']], $flashes);
    }

    public function missingOrderProvider(): array
    {
        return [
            'no id' => [null],
            'array id' => [['61']],
            'zero id' => ['0'],
            'unknown order' => ['99'],
            'order of another module' => ['62'],
        ];
    }

    /**
     * @dataProvider missingOrderProvider
     */
    public function testUnknownOrNonBuckarooOrderIsRejected($orderId): void
    {
        $this->service->expects(self::never())->method('cancel');

        list($data, $flashes) = $this->call('cancel', $orderId);

        self::assertSame(['error' => true, 'message' => 'Order not found.'], $data);
        self::assertSame([['error', 'Order not found.']], $flashes);
    }

    public function errorProvider(): array
    {
        return [
            'gateway message' => [new \RuntimeException('Reservation expired'), 'Reservation expired'],
            'empty message' => [new \RuntimeException(''), 'The Klarna reservation request could not be completed.'],
        ];
    }

    /**
     * @dataProvider errorProvider
     */
    public function testServiceErrorsAreReported(\Throwable $error, string $message): void
    {
        $this->service->method('extend')->willThrowException($error);

        list($data, $flashes) = $this->call('extend', '61');

        self::assertSame(['error' => true, 'message' => $message], $data);
        self::assertSame([['error', $message]], $flashes);
    }
}
