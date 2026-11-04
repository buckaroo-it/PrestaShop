<?php

namespace BuckarooTest\Unit\Upgrade;

use BuckarooTest\Stubs;

require_once __DIR__ . '/UpgradeTestCase.php';

/**
 * Upgrade scripts that add, rename or remove payment methods and update the
 * payment method ordering (4.2.0, 4.4.0, 4.5.0, 5.0.0, 5.1.1).
 *
 * Existing orderings (global and per country) keep the merchant's order: new
 * methods are appended, removed ones taken out. The ordering is only built
 * from scratch when the table is empty.
 */
class PaymentMethodUpgradesTest extends UpgradeTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Loaded inside the test run so the files' top-level guard counts as covered.
        foreach (['4.2.0', '4.4.0', '4.5.0', '5.0.0', '5.1.1'] as $version) {
            self::requireUpgrade($version);
        }
    }

    /**
     * With no ordering yet, one global row holding every payment method is built.
     */
    private function assertOrderingRebuilt(array $expectedIds): void
    {
        self::assertCount(1, self::executed('/^DELETE FROM ps_bk_ordering$/'));
        $inserts = self::inserts('bk_ordering');
        self::assertCount(1, $inserts);
        self::assertSame('', $inserts[0][1]['country_id']);
        self::assertSame(json_encode($expectedIds), stripslashes($inserts[0][1]['value']));
    }

    /** The merchant already ordered the methods: a global and a per-country row. */
    private function givenExistingOrderings(): void
    {
        Stubs::db('/^SELECT id, value FROM `ps_bk_ordering`$/', [
            ['id' => '1', 'value' => '["5","3","8"]'],
            ['id' => '2', 'value' => '["8","5"]'],
        ]);
    }

    /**
     * Regression: the scripts used to delete every ordering and insert one
     * global row, wiping the merchant's per-country ordering.
     *
     * @param array<int, array{0: string, 1: string}> $expected [where, value json] per updated row
     */
    private function assertOrderingsKept(array $expected): void
    {
        self::assertSame([], self::executed('/^DELETE FROM ps_bk_ordering/'));
        self::assertSame([], self::inserts('bk_ordering'));
        self::assertSame($expected, array_map(function (array $args) {
            return [$args[2], stripslashes($args[1]['value'])];
        }, self::updates('bk_ordering')));
    }

    private function givenPaymentMethodsInDatabase(array $ids): void
    {
        Stubs::db('/^SELECT id FROM `ps_bk_payment_methods`$/', array_map(function ($id) {
            return ['id' => $id];
        }, $ids));
    }

    public function testUpgrade420AddsKnakenSettleWithDefaultConfiguration(): void
    {
        $this->autoIncrementPaymentMethods(41);
        $this->givenPaymentMethodsInDatabase(['1', '41']);

        self::assertTrue(upgrade_module_4_2_0($this->module()));

        self::assertSame(
            'UPDATE ps_bk_payment_methods SET label = "Bancontact" WHERE name = "bancontactmrcash"',
            Stubs::sql('execute')[0]
        );
        self::assertSame(
            'INSERT INTO ps_bk_payment_methods (name, label, icon, template, is_payment_method) VALUES ("knaken", "Knaken Settle", "Knaken.svg", "", "1")',
            Stubs::sql('execute')[1]
        );
        self::assertSame(['mode' => 'off'], self::configInsertFor(41));
        $this->assertOrderingRebuilt(['1', '41']);
    }

    public function testUpgrade420AppendsKnakenToTheExistingOrderings(): void
    {
        $this->autoIncrementPaymentMethods(41);
        $this->givenExistingOrderings();

        self::assertTrue(upgrade_module_4_2_0($this->module()));

        $this->assertOrderingsKept([
            ['id = 1', '["5","3","8",41]'],
            ['id = 2', '["8","5",41]'],
        ]);
    }

    public function testUpgrade420FailsWhenTheOrderingCannotBeCleared(): void
    {
        Stubs::db('/^DELETE FROM ps_bk_ordering$/', false);

        self::assertFalse(upgrade_module_4_2_0($this->module()));

        $logs = Stubs::callsTo('PrestaShopLogger::addLog');
        self::assertCount(1, $logs);
        // The repository's own message follows; its wording is not pinned here.
        self::assertStringContainsString('could not build the payment method ordering: Database error:', $logs[0][0]);
        self::assertSame(3, $logs[0][1]);
    }

    public function testUpgrade440AddsBlikAndRenamesAfterpayToRiverty(): void
    {
        $this->autoIncrementPaymentMethods(44);
        $this->givenPaymentMethodsInDatabase(['3', '44']);

        self::assertTrue(upgrade_module_4_4_0($this->module()));

        self::assertSame(
            'INSERT INTO ps_bk_payment_methods (name, label, icon, template, is_payment_method) VALUES ("blik", "Blik", "Blik.svg", "", "1")',
            Stubs::sql('execute')[0]
        );
        self::assertSame(['mode' => 'off'], self::configInsertFor(44));
        self::assertCount(1, self::executed('/^UPDATE ps_bk_payment_methods SET label = "Riverty" WHERE name = "afterpay"$/'));
        $this->assertOrderingRebuilt(['3', '44']);
    }

    public function testUpgrade440AppendsBlikToTheExistingOrderings(): void
    {
        $this->autoIncrementPaymentMethods(44);
        $this->givenExistingOrderings();

        self::assertTrue(upgrade_module_4_4_0($this->module()));

        $this->assertOrderingsKept([
            ['id = 1', '["5","3","8",44]'],
            ['id = 2', '["8","5",44]'],
        ]);
    }

    public function testUpgrade440FailsWhenTheOrderingCannotBeInserted(): void
    {
        Stubs::db('/^INSERT INTO `ps_bk_ordering`/', false);

        self::assertFalse(upgrade_module_4_4_0($this->module()));

        $logs = Stubs::callsTo('PrestaShopLogger::addLog');
        self::assertCount(1, $logs);
        self::assertStringContainsString('could not build the payment method ordering: Database error:', $logs[0][0]);
    }

    public function testUpgrade450RemovesGiropayAndSofortAndRebrandsKnaken(): void
    {
        $this->givenPaymentMethodsInDatabase(['1', '2']);

        self::assertTrue(upgrade_module_4_5_0($this->module()));

        self::assertSame([
            'DELETE FROM ps_bk_payment_methods WHERE name = "giropay"',
            'DELETE FROM ps_bk_payment_methods WHERE name = "sofortueberweisung"',
            'UPDATE ps_bk_payment_methods SET label = "goSettle" WHERE name = "knaken"',
            'UPDATE ps_bk_payment_methods SET icon = "GoSettle.svg" WHERE name = "knaken"',
            'UPDATE ps_bk_payment_methods SET icon = "PayByBank.svg" WHERE name = "paybybank"',
            'DELETE FROM ps_bk_ordering',
        ], Stubs::sql('execute'));
        $this->assertOrderingRebuilt(['1', '2']);
    }

    public function testUpgrade450TakesGiropayAndSofortOutOfTheExistingOrderings(): void
    {
        Stubs::db('/^SELECT id FROM ps_bk_payment_methods WHERE name IN \("giropay", "sofortueberweisung"\)$/', [
            ['id' => '3'],
            ['id' => '5'],
        ]);
        $this->givenExistingOrderings();

        self::assertTrue(upgrade_module_4_5_0($this->module()));

        $this->assertOrderingsKept([
            ['id = 1', '["8"]'],
            ['id = 2', '["8"]'],
        ]);
    }

    public function testUpgrade450LeavesOrderingsWithoutTheRemovedMethodsUntouched(): void
    {
        Stubs::db('/WHERE name IN/', false);
        $this->givenExistingOrderings();

        self::assertTrue(upgrade_module_4_5_0($this->module()));

        $this->assertOrderingsKept([]);
    }

    public function testUpgrade500AddsTwintSwishBizumAndWeroWhenMissing(): void
    {
        $this->autoIncrementPaymentMethods(60);

        self::assertTrue(upgrade_module_5_0_0($this->module()));

        $inserted = [];
        foreach (self::executed('/^INSERT INTO ps_bk_payment_methods /') as $sql) {
            preg_match('/VALUES \("([a-z]+)", "([^"]+)", "([^"]+)"/', $sql, $m);
            $inserted[] = [$m[1], $m[2], $m[3]];
        }
        self::assertSame([
            ['twint', 'Twint', 'Twint.svg'],
            ['swish', 'Swish', 'Swish.svg'],
            ['bizum', 'Bizum', 'Bizum.svg'],
            ['wero', 'Wero', 'Wero.svg'],
        ], $inserted);
        foreach ([60, 61, 62, 63] as $id) {
            self::assertSame(['mode' => 'off'], self::configInsertFor($id), 'default config for method ' . $id);
        }
        $this->assertOrderingRebuilt([]);
    }

    public function testUpgrade500AppendsTheNewMethodsToTheExistingOrderings(): void
    {
        $this->autoIncrementPaymentMethods(60);
        $this->givenExistingOrderings();

        self::assertTrue(upgrade_module_5_0_0($this->module()));

        $this->assertOrderingsKept([
            ['id = 1', '["5","3","8",60,61,62,63]'],
            ['id = 2', '["8","5",60,61,62,63]'],
        ]);
    }

    public function testUpgrade500SkipsMethodsThatAlreadyExist(): void
    {
        Stubs::db('/name = "(twint|swish|bizum|wero)"/', '7');

        self::assertTrue(upgrade_module_5_0_0($this->module()));

        self::assertSame([], self::executed('/^INSERT INTO ps_bk_(payment_methods|configuration) /'));
        $this->assertOrderingRebuilt([]);
    }

    public function testUpgrade500KeepsAnExistingConfigurationRow(): void
    {
        $this->autoIncrementPaymentMethods(70);
        Stubs::db('/COUNT\(1\).*configurable_id = 7[0-3]/', '1');

        self::assertTrue(upgrade_module_5_0_0($this->module()));

        self::assertCount(4, self::executed('/^INSERT INTO ps_bk_payment_methods /'));
        self::assertSame([], self::executed('/^INSERT INTO ps_bk_configuration /'));
    }

    public function testUpgrade500SkipsTheConfigurationWithoutAnInsertId(): void
    {
        // Insert_ID() stays 0: the payment method insert did not happen.
        self::assertTrue(upgrade_module_5_0_0($this->module()));

        self::assertCount(4, self::executed('/^INSERT INTO ps_bk_payment_methods /'));
        self::assertSame([], self::executed('/^INSERT INTO ps_bk_configuration /'));
        self::assertSame([], array_values(array_filter(Stubs::sql('getValue'), function ($sql) {
            return strpos($sql, 'COUNT(1)') !== false;
        })), 'no configuration lookup without an id');
    }

    public function testUpgrade511AddsGooglePay(): void
    {
        $this->autoIncrementPaymentMethods(90);
        $this->givenPaymentMethodsInDatabase(['1', '90']);

        self::assertTrue(upgrade_module_5_1_1($this->module()));

        self::assertSame(
            ['INSERT INTO ps_bk_payment_methods (name, label, icon, template, is_payment_method) VALUES ("googlepay", "Google Pay", "GooglePay.svg", "", "1")'],
            self::executed('/^INSERT INTO ps_bk_payment_methods /')
        );
        self::assertSame(['mode' => 'off'], self::configInsertFor(90));
        self::assertSame(
            ['SELECT COUNT(1) FROM `ps_bk_configuration` WHERE (configurable_id = 90)'],
            array_values(array_filter(Stubs::sql('getValue'), function ($sql) {
                return strpos($sql, 'COUNT(1)') !== false;
            }))
        );
        $this->assertOrderingRebuilt(['1', '90']);
    }

    public function testUpgrade511AppendsGooglePayToTheExistingOrderings(): void
    {
        $this->autoIncrementPaymentMethods(90);
        $this->givenExistingOrderings();

        self::assertTrue(upgrade_module_5_1_1($this->module()));

        $this->assertOrderingsKept([
            ['id = 1', '["5","3","8",90]'],
            ['id = 2', '["8","5",90]'],
        ]);
    }

    public function testUpgrade511LeavesAnExistingGooglePayAlone(): void
    {
        Stubs::db('/name = "googlepay"/', '12');

        self::assertTrue(upgrade_module_5_1_1($this->module()));

        self::assertSame([], self::executed('/^INSERT INTO ps_bk_(payment_methods|configuration) /'));
        $this->assertOrderingRebuilt([]);
    }

    public function testUpgrade511KeepsAnExistingConfigurationRow(): void
    {
        $this->autoIncrementPaymentMethods(91);
        Stubs::db('/COUNT\(1\)/', '1');

        self::assertTrue(upgrade_module_5_1_1($this->module()));

        self::assertCount(1, self::executed('/^INSERT INTO ps_bk_payment_methods /'));
        self::assertNull(self::configInsertFor(91));
    }

    public function testOrderingsThatAlreadyHoldTheMethodOrCannotBeDecodedAreLeftAlone(): void
    {
        $this->autoIncrementPaymentMethods(90);
        Stubs::db('/^SELECT id, value FROM `ps_bk_ordering`$/', [
            ['id' => '1', 'value' => '["5","90"]'],
            ['id' => '2', 'value' => 'broken'],
            ['id' => '3', 'value' => '[90]'],
        ]);

        self::assertTrue(upgrade_module_5_1_1($this->module()));

        $this->assertOrderingsKept([]);
    }

    public function testAnUnreadableOrderingTableIsLeftAlone(): void
    {
        $this->autoIncrementPaymentMethods(90);
        Stubs::db('/^SELECT id, value FROM `ps_bk_ordering`$/', false);

        self::assertTrue(upgrade_module_5_1_1($this->module()));

        $this->assertOrderingsKept([]);
        $logs = Stubs::callsTo('PrestaShopLogger::addLog');
        self::assertCount(1, $logs);
        self::assertStringContainsString('could not read the payment method ordering', $logs[0][0]);
    }

    public function testFailsWhenAnExistingOrderingCannotBeUpdated(): void
    {
        $this->autoIncrementPaymentMethods(90);
        $this->givenExistingOrderings();
        Stubs::db('/^UPDATE `ps_bk_ordering` .* WHERE id = 1$/', false);

        self::assertFalse(upgrade_module_5_1_1($this->module()));

        // The other ordering is still updated.
        self::assertCount(2, self::updates('bk_ordering'));
        $logs = Stubs::callsTo('PrestaShopLogger::addLog');
        self::assertCount(1, $logs);
        self::assertStringContainsString('could not update the payment method ordering', $logs[0][0]);
    }
}
