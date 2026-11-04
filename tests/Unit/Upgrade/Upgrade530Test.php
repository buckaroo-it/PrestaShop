<?php

namespace BuckarooTest\Unit\Upgrade;

use BuckarooTest\Stubs;

require_once __DIR__ . '/UpgradeTestCase.php';

/**
 * upgrade-5.3.0.php: removes GoSettle (knaken) and Payconiq, registers
 * displayPaymentTop, adds Click to Pay and drops the global test setting.
 */
class Upgrade530Test extends UpgradeTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Loaded inside the test run so the files' top-level guard counts as covered.
        self::requireUpgrade('5.3.0');
    }

    private static function lookup(string $name): string
    {
        return '/^SELECT id FROM `ps_bk_payment_methods` WHERE \(name = "' . $name . '"\)$/';
    }

    public function testRemovesOldMethodsAndAppendsClickToPayToEveryOrdering(): void
    {
        \Configuration::updateValue('BUCKAROO_TEST', '1');
        Stubs::db(self::lookup('knaken'), '12');
        Stubs::db(self::lookup('payconiq'), '0');
        Stubs::db(self::lookup('clicktopay'), false);
        Stubs::db('/^SELECT id, value FROM `ps_bk_ordering`$/', [
            ['id' => '1', 'value' => '["3","12","5"]'],
            ['id' => '2', 'value' => 'broken'],
            ['id' => '3', 'value' => '[1000,4]'],
        ]);
        Stubs::$nextId = 1000;

        self::assertTrue(upgrade_module_5_3_0($this->module()));

        self::assertFalse(\Configuration::get('BUCKAROO_TEST'));
        self::assertSame(['displayPaymentTop'], self::registeredHooks());

        self::assertSame([
            'DELETE FROM `ps_bk_configuration` WHERE configurable_id = 12',
            'DELETE FROM `ps_bk_payment_methods` WHERE id = 12',
            'DELETE FROM `ps_bk_payment_methods` WHERE name = "knaken"',
            'DELETE FROM `ps_bk_payment_methods` WHERE name = "payconiq"',
        ], Stubs::sql('execute'));

        $orderingUpdates = array_map(function (array $args) {
            return [$args[2], stripslashes($args[1]['value'])];
        }, self::updates('bk_ordering'));
        self::assertSame([
            // knaken (12) removed from every decodable ordering row
            ['id = 1', '["3","5"]'],
            ['id = 3', '[1000,4]'],
            // click to pay (1000) appended where it is missing
            ['id = 1', '["3","12","5",1000]'],
        ], $orderingUpdates);

        $methodInsert = self::inserts('bk_payment_methods');
        self::assertCount(1, $methodInsert);
        $row = $methodInsert[0][1];
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $row['created_at']);
        unset($row['created_at']);
        self::assertSame([
            'name' => 'clicktopay',
            'label' => 'Click to Pay',
            'icon' => 'ClickToPay.svg',
            'template' => 'payment_clicktopay.tpl',
            'is_payment_method' => 1,
        ], $row);

        $configInsert = self::inserts('bk_configuration');
        self::assertCount(1, $configInsert);
        self::assertSame(1000, $configInsert[0][1]['configurable_id']);
        self::assertSame(
            ['mode' => 'off', 'client_id' => '', 'client_secret' => '', 'merchant_identifier' => ''],
            json_decode(stripslashes($configInsert[0][1]['value']), true)
        );
    }

    public function testDoesNotAddClickToPayTwice(): void
    {
        Stubs::db(self::lookup('clicktopay'), '33');

        self::assertTrue(upgrade_module_5_3_0($this->module()));

        self::assertSame([], self::inserts('bk_payment_methods'));
        self::assertSame([], self::inserts('bk_configuration'));
        self::assertSame([], self::updates('bk_ordering'));
        self::assertSame([], Stubs::sql('executeS'), 'nothing to remove or append');
    }

    public function testSkipsTheConfigurationAndOrderingWhenTheInsertFails(): void
    {
        Stubs::db('/^INSERT INTO `ps_bk_payment_methods`/', false);

        self::assertTrue(upgrade_module_5_3_0($this->module()));

        self::assertSame([], self::inserts('bk_configuration'));
        self::assertSame([], Stubs::sql('executeS'));
        self::assertSame(['displayPaymentTop'], self::registeredHooks());
    }

    public function testCopesWithAnUnreadableOrderingTable(): void
    {
        Stubs::db(self::lookup('payconiq'), '8');
        Stubs::db('/^SELECT id, value FROM `ps_bk_ordering`$/', false);

        self::assertTrue(upgrade_module_5_3_0($this->module()));

        self::assertSame([], self::updates('bk_ordering'));
        self::assertCount(2, Stubs::sql('executeS'), 'read once for the removal, once for the append');
        self::assertCount(1, self::executed('/^DELETE FROM `ps_bk_payment_methods` WHERE id = 8$/'));
        self::assertCount(1, self::inserts('bk_configuration'));
    }
}
