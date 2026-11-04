<?php

declare(strict_types=1);

namespace BuckarooTest\Unit\Services;

use Buckaroo\PrestaShop\Src\Config\Config;
use Buckaroo\PrestaShop\Src\Service\DeferredOrderConfirmation;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;

final class DeferredOrderConfirmationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Configuration::updateValue(Config::BUCKAROO_DISABLE_UNPAID_ORDER_EMAIL, '1');
        \Db::getInstance(); // make sure the stub exists before responders are used
    }

    private function unpaidOrder(int $id = 42, array $props = []): void
    {
        Stubs::object('Order', $id, array_merge(['module' => 'buckaroo3', 'current_state' => 3], $props));
        Stubs::returns('Order->hasBeenPaid', false);
    }

    private function mailParams(array $overrides = []): array
    {
        return array_merge([
            'template' => 'order_conf',
            'templateVars' => ['{id_order}' => 42, '{firstname}' => 'Zoë', '{total}' => '€ 10'],
            'idLang' => 2,
            'subject' => "Order 'confirmation'",
            'to' => ['buyer@example.com', 'cc@example.com'],
            'toName' => ['Jan Jansen'],
            'templatePath' => '/mails/',
            'idShop' => 3,
        ], $overrides);
    }

    /** @return array<int, array{method: string, sql: mixed, args: array}> */
    private static function queries(string $method): array
    {
        return array_values(array_filter(Stubs::$queries, function ($query) use ($method) {
            return $query['method'] === $method;
        }));
    }

    public function testHoldStoresTheConfirmationOfAnUnpaidBuckarooOrder(): void
    {
        $this->unpaidOrder();

        self::assertFalse(DeferredOrderConfirmation::hold($this->mailParams(), 'buckaroo3'));

        $inserts = self::queries('insert');
        self::assertCount(1, $inserts);
        [$table, $data, $type] = $inserts[0]['args'];
        self::assertSame('bk_deferred_order_mail', $table);
        self::assertSame(\Db::REPLACE, $type);
        self::assertSame(42, $data['id_order']);
        self::assertSame(2, $data['id_lang']);
        self::assertSame('order_conf', $data['template']);
        self::assertSame("Order \\'confirmation\\'", $data['subject'], 'values are escaped because Db::insert() does not');
        self::assertSame(
            ['{id_order}' => 42, '{firstname}' => 'Zoë', '{total}' => '€ 10'],
            json_decode(stripslashes($data['template_vars']), true)
        );
        self::assertStringContainsString('Zoë', $data['template_vars'], 'unicode is kept unescaped');
        self::assertSame('buyer@example.com', $data['recipient'], 'the first recipient is used');
        self::assertSame('Jan Jansen', $data['recipient_name']);
        self::assertSame('/mails/', $data['template_path']);
        self::assertSame(3, $data['id_shop']);
    }

    public function testHoldUsesDefaultsForMissingMailParameters(): void
    {
        $this->unpaidOrder();

        $params = ['template' => 'order_conf', 'templateVars' => ['{id_order}' => 42]];
        self::assertFalse(DeferredOrderConfirmation::hold($params, 'buckaroo3'));

        $data = self::queries('insert')[0]['args'][1];
        self::assertSame(0, $data['id_lang']);
        self::assertSame('', $data['subject']);
        self::assertSame('', $data['recipient']);
        self::assertSame('', $data['recipient_name']);
        self::assertSame(_PS_MAIL_DIR_, $data['template_path']);
        self::assertSame(0, $data['id_shop']);
    }

    /**
     * @dataProvider sentImmediatelyProvider
     */
    public function testHoldLetsTheMailThrough(callable $arrange, array $params): void
    {
        $arrange($this);

        self::assertTrue(DeferredOrderConfirmation::hold($params, 'buckaroo3'));
        self::assertSame([], self::queries('insert'));
    }

    public function sentImmediatelyProvider(): array
    {
        $order = function (array $props = [], bool $paid = false) {
            return function () use ($props, $paid) {
                Stubs::object('Order', 42, array_merge(['module' => 'buckaroo3', 'current_state' => 3], $props));
                Stubs::returns('Order->hasBeenPaid', $paid);
            };
        };
        $params = [
            'template' => 'order_conf',
            'templateVars' => ['{id_order}' => 42],
        ];

        return [
            'feature disabled' => [function () use ($order) {
                $order()();
                \Configuration::updateValue(Config::BUCKAROO_DISABLE_UNPAID_ORDER_EMAIL, '0');
            }, $params],
            'other template' => [$order(), ['template' => 'payment'] + $params],
            'no template' => [$order(), ['templateVars' => ['{id_order}' => 42]]],
            'order not found' => [function () {
            }, $params],
            'no order reference at all' => [$order(), ['template' => 'order_conf', 'templateVars' => []]],
            'other payment module' => [$order(['module' => 'ps_wirepayment']), $params],
            'order already paid' => [$order([], true), $params],
            'current state is a paid state' => [function () use ($order) {
                $order(['current_state' => 2])();
                Stubs::object('OrderState', 2, ['paid' => 1]);
            }, $params],
        ];
    }

    private static function cart(int $id): \Cart
    {
        $cart = new \Cart();
        $cart->id = $id;

        return $cart;
    }

    public function testHoldIsNotAppliedWhenTheTemplateVarsCannotBeEncoded(): void
    {
        $this->unpaidOrder();

        $params = $this->mailParams(['templateVars' => ['{id_order}' => 42, '{bad}' => "\xB1\x31"]]);

        self::assertTrue(DeferredOrderConfirmation::hold($params, 'buckaroo3'));
        self::assertSame([], self::queries('insert'));
    }

    public function testHoldLetsTheMailThroughWhenTheInsertFails(): void
    {
        $this->unpaidOrder();
        Stubs::db('/INSERT INTO `ps_bk_deferred_order_mail`/', false);

        self::assertTrue(DeferredOrderConfirmation::hold($this->mailParams(), 'buckaroo3'));
        self::assertCount(1, self::queries('insert'));
    }

    public function testHoldFindsTheOrderThroughTheMailCart(): void
    {
        $this->unpaidOrder(77);
        Stubs::returns('Order::getIdByCartId', function ($cartId) {
            return $cartId === 5 ? 77 : 0;
        });

        $params = $this->mailParams(['templateVars' => ['{firstname}' => 'Jan'], 'cart' => self::cart(5)]);

        self::assertFalse(DeferredOrderConfirmation::hold($params, 'buckaroo3'));
        self::assertSame(77, self::queries('insert')[0]['args'][1]['id_order']);
    }

    public function testHoldFindsTheOrderThroughTheContextCart(): void
    {
        $this->unpaidOrder(88);
        Stubs::returns('Order::getIdByCartId', function ($cartId) {
            return $cartId === 9 ? 88 : 0;
        });
        $context = $this->context();
        $context->cart = self::cart(9);

        $params = $this->mailParams(['templateVars' => ['{firstname}' => 'Jan'], 'cart' => self::cart(5)]);

        self::assertFalse(DeferredOrderConfirmation::hold($params, 'buckaroo3', $context));
        self::assertSame(88, self::queries('insert')[0]['args'][1]['id_order']);
        self::assertSame([[5], [9]], Stubs::callsTo('Order::getIdByCartId'));
    }

    public function testReleaseSendsTheStoredConfirmationAndDeletesIt(): void
    {
        Stubs::object('Order', 42, ['module' => 'buckaroo3']);
        Stubs::db('/FROM `ps_bk_deferred_order_mail`/', [
            'id_lang' => '2',
            'template' => 'order_conf',
            'subject' => 'Order confirmation',
            'template_vars' => json_encode(['{id_order}' => 42]),
            'recipient' => 'buyer@example.com',
            'recipient_name' => 'Jan Jansen',
            'template_path' => '',
            'id_shop' => '3',
        ]);
        $heldDuringSend = null;
        Stubs::returns('Mail::Send', function () use (&$heldDuringSend) {
            // The hook fires again for the message being sent: it must not be captured again.
            $heldDuringSend = DeferredOrderConfirmation::hold(['template' => 'order_conf', 'templateVars' => ['{id_order}' => 42]], 'buckaroo3');
            // A status change triggered while sending must not send the same message twice.
            DeferredOrderConfirmation::release(['newOrderStatus' => (object) ['paid' => 1], 'id_order' => 42], 'buckaroo3');

            return true;
        });
        Stubs::returns('Order->hasBeenPaid', false);

        DeferredOrderConfirmation::release(['newOrderStatus' => (object) ['paid' => 1], 'id_order' => 42], 'buckaroo3');

        self::assertSame([[
            2, 'order_conf', 'Order confirmation', ['{id_order}' => 42], 'buyer@example.com', 'Jan Jansen',
            null, null, null, null, _PS_MAIL_DIR_, false, 3,
        ]], Stubs::callsTo('Mail::Send'));
        self::assertTrue($heldDuringSend);
        self::assertCount(1, Stubs::sql('getRow'), 'the nested release did not read the message again');
        self::assertSame([], self::queries('insert'));
        self::assertStringContainsString('WHERE `id_order` = 42', Stubs::sql('getRow')[0]);
        self::assertSame(['DELETE FROM `ps_bk_deferred_order_mail` WHERE id_order = 42'], Stubs::sql('delete'));
    }

    public function testReleaseKeepsTheConfirmationAndLogsWhenSendingFails(): void
    {
        Stubs::object('Order', 42, ['module' => 'buckaroo3']);
        Stubs::db('/FROM `ps_bk_deferred_order_mail`/', [
            'id_lang' => 1, 'template' => 'order_conf', 'subject' => 's', 'template_vars' => '{}',
            'recipient' => 'a@b.c', 'recipient_name' => 'A', 'template_path' => '/custom/', 'id_shop' => 1,
        ]);
        Stubs::returns('Mail::Send', false);

        DeferredOrderConfirmation::release(['newOrderStatus' => (object) ['paid' => '1'], 'id_order' => '42'], 'buckaroo3');

        self::assertSame('/custom/', Stubs::callsTo('Mail::Send')[0][10]);
        self::assertSame([], Stubs::sql('delete'));
        self::assertSame(
            [['Buckaroo: order confirmation could not be sent for order 42', 3, null, 'Order', 42, true]],
            Stubs::callsTo('PrestaShopLogger::addLog')
        );
    }

    public function testReleaseDropsAnUnreadableStoredConfirmation(): void
    {
        Stubs::object('Order', 42, ['module' => 'buckaroo3']);
        Stubs::db('/FROM `ps_bk_deferred_order_mail`/', ['template_vars' => 'not json']);

        DeferredOrderConfirmation::release(['newOrderStatus' => (object) ['paid' => 1], 'id_order' => 42], 'buckaroo3');

        self::assertSame(0, Stubs::callCount('Mail::Send'));
        self::assertCount(1, Stubs::sql('delete'));
        self::assertSame(
            'Buckaroo: stored confirmation could not be read for order 42',
            Stubs::callsTo('PrestaShopLogger::addLog')[0][0]
        );
    }

    public function testReleaseWithoutAStoredConfirmationDoesNothing(): void
    {
        Stubs::object('Order', 42, ['module' => 'buckaroo3']);

        DeferredOrderConfirmation::release(['newOrderStatus' => (object) ['paid' => 1], 'id_order' => 42], 'buckaroo3');

        self::assertCount(1, Stubs::sql('getRow'));
        self::assertSame(0, Stubs::callCount('Mail::Send'));
        self::assertSame([], Stubs::sql('delete'));
    }

    /**
     * @dataProvider ignoredReleaseProvider
     */
    public function testReleaseIgnoresStatusChangesThatDoNotPayABuckarooOrder(array $params, bool $enabled, string $module): void
    {
        \Configuration::updateValue(Config::BUCKAROO_DISABLE_UNPAID_ORDER_EMAIL, $enabled ? 1 : 0);
        Stubs::object('Order', 42, ['module' => $module]);

        DeferredOrderConfirmation::release($params, 'buckaroo3');

        self::assertSame([], Stubs::$queries);
        self::assertSame(0, Stubs::callCount('Mail::Send'));
    }

    public function ignoredReleaseProvider(): array
    {
        $paid = (object) ['paid' => 1];

        return [
            'feature disabled' => [['newOrderStatus' => $paid, 'id_order' => 42], false, 'buckaroo3'],
            'no order id' => [['newOrderStatus' => $paid], true, 'buckaroo3'],
            'no status' => [['id_order' => 42], true, 'buckaroo3'],
            'status is not an object' => [['newOrderStatus' => 2, 'id_order' => 42], true, 'buckaroo3'],
            'unpaid status' => [['newOrderStatus' => (object) ['paid' => 0], 'id_order' => 42], true, 'buckaroo3'],
            'unknown order' => [['newOrderStatus' => $paid, 'id_order' => 43], true, 'buckaroo3'],
            'other module' => [['newOrderStatus' => $paid, 'id_order' => 42], true, 'ps_checkpayment'],
        ];
    }
}
