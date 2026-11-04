<?php

namespace BuckarooTest\Unit\RefundAdmin;

use Buckaroo\PrestaShop\Src\Refund\OrderService;
use Buckaroo\PrestaShop\Src\Refund\Settings;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\IssuePartialRefundCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\InvalidCancelProductException;
use PrestaShop\PrestaShop\Core\Domain\Order\VoucherRefundType;

require_once __DIR__ . '/StubsSupport.php';

/**
 * OrderService turns a Buckaroo refund amount into a PrestaShop partial refund
 * (credit slip): products first, then shipping, and flags the session so the
 * refund decorator does not send the same refund to Buckaroo a second time.
 */
class RefundOrderServiceTest extends TestCase
{
    /** @var RecordingCommandBus */
    private $bus;

    /** @var ArraySession */
    private $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = new ArraySession();
        $this->bus = new RecordingCommandBus();
        $this->bus->session = $this->session;

        Stubs::returns('OrderDetail::getList', function ($orderId) {
            return $orderId !== 70 ? [] : [
                ['id_order_detail' => 1, 'product_quantity' => 3, 'product_quantity_return' => 1, 'product_quantity_refunded' => 0, 'unit_price_tax_incl' => '10.00', 'unit_price_tax_excl' => '8.00'],
                ['id_order_detail' => 2, 'product_quantity' => 1, 'product_quantity_return' => 0, 'product_quantity_refunded' => 1, 'unit_price_tax_incl' => '99.00', 'unit_price_tax_excl' => '80.00'],
                ['id_order_detail' => 3, 'product_quantity' => 1, 'product_quantity_return' => 0, 'product_quantity_refunded' => 0, 'unit_price_tax_incl' => '5.00', 'unit_price_tax_excl' => '4.00'],
            ];
        });
        Stubs::returns('OrderSlip::getShippingSlipResume', ['total_shipping_tax_incl' => '2.00']);
        Stubs::object('Customer', 8, ['id_default_group' => 3]);
    }

    private static function order(): \Order
    {
        $order = new \Order();
        $order->id = 70;
        $order->id_customer = 8;
        $order->total_shipping_tax_incl = 8.0;
        $order->total_shipping_tax_excl = 6.0;

        return $order;
    }

    private function refund(float $amount): IssuePartialRefundCommand
    {
        (new OrderService($this->bus, $this->session))->refund(self::order(), $amount);
        self::assertCount(1, $this->bus->commands);

        return $this->bus->commands[0];
    }

    public function splitProvider(): array
    {
        // Tax incl: product 1 has 2 units of 10.00 left, product 2 nothing, product 3 one unit of 5.00;
        // shipping 8.00 of which 2.00 was already refunded.
        return [
            'part of the first product' => [PS_TAX_INC, 15.0, [1 => ['amount' => 15.0, 'quantity' => 2.0]], 0],
            'whole first product, part of the next' => [PS_TAX_INC, 23.0, [1 => ['amount' => 20.0, 'quantity' => 2], 3 => ['amount' => 3.0, 'quantity' => 1.0]], 0],
            'products and part of the shipping' => [PS_TAX_INC, 30.0, [1 => ['amount' => 20.0, 'quantity' => 2], 3 => ['amount' => 5.0, 'quantity' => 1]], 5.0],
            'shipping is capped at what is left' => [PS_TAX_INC, 40.0, [1 => ['amount' => 20.0, 'quantity' => 2], 3 => ['amount' => 5.0, 'quantity' => 1]], 6.0],
            'tax excluded prices' => [PS_TAX_EXC, 18.0, [1 => ['amount' => 16.0, 'quantity' => 2], 3 => ['amount' => 2.0, 'quantity' => 1.0]], 0],
            'tax excluded shipping' => [PS_TAX_EXC, 22.0, [1 => ['amount' => 16.0, 'quantity' => 2], 3 => ['amount' => 4.0, 'quantity' => 1]], 2.0],
        ];
    }

    /**
     * @dataProvider splitProvider
     */
    public function testRefundAmountIsSpreadOverProductsThenShipping(int $taxMethod, float $amount, array $products, $shipping): void
    {
        Stubs::returns('Group::getPriceDisplayMethod', function ($groupId) use ($taxMethod) {
            return $groupId === 3 ? $taxMethod : -1;
        });

        $command = $this->refund($amount);

        self::assertSame(70, $command->getOrderId());
        self::assertEquals($products, $command->getOrderDetailRefunds());
        self::assertEqualsWithDelta($shipping, $command->getShippingCostRefundAmount(), 0.0001);
        self::assertSame(VoucherRefundType::PRODUCT_PRICES_EXCLUDING_VOUCHER_REFUND, $command->getVoucherRefundType());
    }

    public function testShopRefundIsFlaggedSoBuckarooIsNotRefundedTwice(): void
    {
        $this->refund(5.0);

        self::assertSame([true], $this->bus->skipFlagDuringHandle);
        self::assertFalse($this->session->has('buckaroo_skip_refund'));
    }

    public function settingsProvider(): array
    {
        return [
            'defaults: credit slip on' => [[], false, true, false],
            'all enabled' => [[Settings::LABEL_REFUND_RESTOCK => '1', Settings::LABEL_REFUND_CREDIT_SLIP => '1', Settings::LABEL_REFUND_VOUCHER => '1'], true, true, true],
            'credit slip disabled' => [[Settings::LABEL_REFUND_CREDIT_SLIP => '0'], false, false, false],
            'unreadable credit slip setting' => [[Settings::LABEL_REFUND_CREDIT_SLIP => ['x']], false, true, false],
        ];
    }

    /**
     * @dataProvider settingsProvider
     */
    public function testRefundOptionsComeFromTheSettings(array $config, bool $restock, bool $creditSlip, bool $voucher): void
    {
        foreach ($config as $key => $value) {
            \Configuration::$values[$key] = $value;
        }

        $command = $this->refund(5.0);

        self::assertSame($restock, $command->restockRefundedProducts());
        self::assertSame($creditSlip, $command->generateCreditSlip());
        self::assertSame($voucher, $command->generateVoucher());
    }

    public function testShippingWithoutSlipResumeIsFullyAvailable(): void
    {
        Stubs::returns('OrderSlip::getShippingSlipResume', []);
        Stubs::returns('Group::getPriceDisplayMethod', PS_TAX_INC);

        self::assertEqualsWithDelta(8.0, $this->refund(100.0)->getShippingCostRefundAmount(), 0.0001);
    }

    /**
     * BUG (src/Refund/OrderService.php::refund): the skip flag is not cleared
     * when the command bus throws (e.g. InvalidCancelProductException::NO_REFUNDS,
     * which AdminRefundController expects and swallows). The flag stays in the
     * employee session and IssuePartialRefundHandler then skips the Buckaroo
     * refund of every later PrestaShop partial refund in that session.
     * This test pins the current behaviour; update it when the source is fixed.
     */
    public function testSkipFlagIsClearedWhenTheShopRefundFails(): void
    {
        $this->bus->throw = new InvalidCancelProductException('nothing', InvalidCancelProductException::NO_REFUNDS);

        try {
            $this->refund(5.0);
            self::fail('The command bus exception must surface');
        } catch (InvalidCancelProductException $e) {
            self::assertSame(InvalidCancelProductException::NO_REFUNDS, $e->getCode());
        }

        self::assertFalse($this->session->has('buckaroo_skip_refund'));
    }
}
