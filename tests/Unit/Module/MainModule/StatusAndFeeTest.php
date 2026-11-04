<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * It is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this file
 *
 *  @author    Buckaroo.nl <plugins@buckaroo.nl>
 *  @copyright Copyright (c) Buckaroo B.V.
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace BuckarooTest\Unit\Module\MainModule;

use BuckarooTest\Stubs;

require_once __DIR__ . '/MainModuleTestCase.php';

/**
 * Buckaroo status to order state mapping and the payment fee calculation.
 */
class StatusAndFeeTest extends MainModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            'PS_OS_PAYMENT' => 2,
            'PS_OS_CANCELED' => 6,
            'PS_OS_ERROR' => 8,
            'PS_OS_OUTOFSTOCK_PAID' => 9,
            'PS_OS_BANKWIRE' => 10,
            'PS_OS_WAITING_PAYMENT' => 11,
        ] as $key => $value) {
            \Configuration::updateValue($key, $value);
        }
    }

    /**
     * @dataProvider statusProvider
     *
     * @param int[] $existingStates
     */
    public function testResolveStatusCode(string $status, array $config, array $existingStates, int $expected): void
    {
        foreach ($config as $key => $value) {
            \Configuration::updateValue($key, $value);
        }
        foreach ($existingStates as $id) {
            Stubs::object('OrderState', $id);
        }

        self::assertSame($expected, \Buckaroo3::resolveStatusCode(constant('BuckarooAbstract::' . $status)));
    }

    public function statusProvider(): array
    {
        return [
            'success uses the Buckaroo state' => ['BUCKAROO_SUCCESS', ['BUCKAROO_ORDER_STATE_SUCCESS' => 15], [2, 15], 15],
            'success falls back to payment accepted' => ['BUCKAROO_SUCCESS', ['BUCKAROO_ORDER_STATE_SUCCESS' => 99], [2], 2],
            'success without any valid state' => ['BUCKAROO_SUCCESS', [], [], 8],
            'pending uses the module default' => ['BUCKAROO_PENDING_PAYMENT', ['BUCKAROO_ORDER_STATE_DEFAULT' => 14], [14, 10], 14],
            'pending falls back to bank wire' => ['BUCKAROO_PENDING_PAYMENT', ['BUCKAROO_ORDER_STATE_DEFAULT' => 0], [10], 10],
            'pending falls back to awaiting payment' => ['BUCKAROO_PENDING_PAYMENT', [], [11], 11],
            'failed uses the Buckaroo state' => ['BUCKAROO_FAILED', ['BUCKAROO_ORDER_STATE_FAILED' => 16], [16, 6], 16],
            'canceled falls back to canceled' => ['BUCKAROO_CANCELED', [], [6], 6],
            'error falls back to payment error' => ['BUCKAROO_ERROR', [], [8], 8],
            'incorrect payment without states' => ['BUCKAROO_INCORRECT_PAYMENT', ['PS_OS_ERROR' => 0], [], 0],
        ];
    }

    public function testUnknownStatusMapsToPaymentError(): void
    {
        self::assertSame(8, \Buckaroo3::resolveStatusCode('BUCKAROO_SOMETHING_ELSE'));
    }

    /**
     * @dataProvider backorderProvider
     */
    public function testSuccessfulBackorderUsesTheOutOfStockPaidState(
        bool $stockManagement,
        $orderId,
        ?array $details,
        int $expected
    ): void {
        \Configuration::updateValue('PS_STOCK_MANAGEMENT', $stockManagement ? 1 : 0);
        \Configuration::updateValue('BUCKAROO_ORDER_STATE_SUCCESS', 2);
        Stubs::object('OrderState', 2);
        Stubs::object('OrderState', 9);
        if ($details !== null) {
            Stubs::object('Order', 40);
            Stubs::returns('Order->getOrderDetailList', $details);
        }

        self::assertSame($expected, \Buckaroo3::resolveStatusCode(\BuckarooAbstract::BUCKAROO_SUCCESS, $orderId));
    }

    public function backorderProvider(): array
    {
        $short = [['product_quantity' => 1, 'product_quantity_in_stock' => 1], ['product_quantity' => 3, 'product_quantity_in_stock' => 1]];
        $inStock = [['product_quantity' => 2, 'product_quantity_in_stock' => 2]];

        return [
            'backordered line' => [true, 40, $short, 9],
            'everything in stock' => [true, 40, $inStock, 2],
            'no order lines' => [true, 40, [], 2],
            'stock management off' => [false, 40, $short, 2],
            'invalid order id' => [true, -1, $short, 2],
            'unknown order' => [true, 41, null, 2],
        ];
    }

    /**
     * @dataProvider feeProvider
     */
    public function testBuckarooFee($configured, $base, ?float $cartTotal, ?array $expected): void
    {
        if ($cartTotal !== null) {
            $cart = $this->contextCart(7, $cartTotal);
            $cart->stub('getAverageProductsTaxRate', 0.2);
        }
        $fees = new ServiceDouble(['getBuckarooFeeValue' => $configured]);
        $module = $this->createModule(['buckaroo.config.api.fee.service' => $fees]);

        $fee = $module->getBuckarooFee('ideal', $base);

        self::assertSame([['ideal']], $fees->callsTo('getBuckarooFeeValue'));
        if ($expected === null) {
            self::assertNull($fee);

            return;
        }
        self::assertEqualsWithDelta($expected[0], $fee['buckaroo_fee_tax_excl'], 0.0001);
        self::assertEqualsWithDelta($expected[1], $fee['buckaroo_fee_tax'], 0.0001);
        self::assertEqualsWithDelta($expected[2], $fee['buckaroo_fee_tax_incl'], 0.0001);
    }

    public function feeProvider(): array
    {
        return [
            'not configured' => [null, null, 100.0, null],
            'empty' => ['', null, 100.0, null],
            'fixed amount with cart tax' => [' 2.50 ', null, 100.0, [2.5, 0.5, 3.0]],
            'fixed amount without cart' => ['2.5', null, null, [2.5, 0.0, 2.5]],
            'fixed amount rounded' => ['1.006', null, 50.0, [1.01, 0.2, 1.21]],
            'zero' => ['0', null, 100.0, null],
            'negative' => ['-1', null, 100.0, null],
            'percentage of the given base' => ['10%', 50, 100.0, [5.0, 1.0, 6.0]],
            'percentage with comma of the cart total' => ['2,5 %', null, 200.0, [5.0, 1.0, 6.0]],
            'zero percent' => ['0%', 50, 100.0, null],
            'percentage without base' => ['10%', null, null, null],
            'percentage of a zero base' => ['10%', 0, 100.0, null],
        ];
    }

    /**
     * @dataProvider inactiveProvider
     */
    public function testNoPaymentOptionsWhenTheModuleCannotTakePayments(bool $active, array $config): void
    {
        foreach ($config as $key => $value) {
            \Configuration::updateValue($key, $value);
        }
        $paymentService = new ServiceDouble();
        $module = $this->createModule(['buckaroo.config.api.payment.service' => $paymentService]);
        $module->active = $active;

        self::assertSame([], $module->hookPaymentOptions(['cookie' => $this->context()->cookie]));
        self::assertSame([], $paymentService->calls);
        self::assertSame([], Stubs::callsTo('Media::addJsDef'));
    }

    public function inactiveProvider(): array
    {
        $keys = ['BUCKAROO_MERCHANT_KEY' => 'website', 'BUCKAROO_SECRET_KEY' => 'secret'];

        return [
            'module disabled' => [false, $keys],
            'no website key' => [true, ['BUCKAROO_SECRET_KEY' => 'secret']],
            'no secret key' => [true, ['BUCKAROO_MERCHANT_KEY' => 'website']],
        ];
    }
}
