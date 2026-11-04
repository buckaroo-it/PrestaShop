<?php

namespace BuckarooTest\Unit\Upgrade;

use BuckarooTest\Stubs;

require_once __DIR__ . '/UpgradeTestCase.php';

/**
 * upgrade-5.2.0.php: group transaction table, cart footer hook and the
 * fee_refunded column.
 */
class Upgrade520Test extends UpgradeTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Loaded inside the test run so the files' top-level guard counts as covered.
        self::requireUpgrade('5.2.0');
    }

    public function testCreatesTheTableRegistersTheHookAndAddsTheColumn(): void
    {
        self::assertTrue(upgrade_module_5_2_0($this->module()));

        $sql = Stubs::sql('execute');
        self::assertCount(2, $sql);
        self::assertStringStartsWith('CREATE TABLE IF NOT EXISTS `ps_bk_group_transaction`', $sql[0]);
        self::assertStringContainsString('`refunded_amount`       DOUBLE PRECISION DEFAULT 0', $sql[0]);
        self::assertStringContainsString('ENGINE = InnoDB', $sql[0]);
        self::assertStringContainsString('ADD COLUMN `fee_refunded` TINYINT(1) NOT NULL DEFAULT 0', $sql[1]);
        self::assertSame(["SHOW COLUMNS FROM `ps_bk_buckaroo_fee` LIKE 'fee_refunded'"], Stubs::sql('executeS'));
        self::assertSame(['displayShoppingCartFooter'], self::registeredHooks());
    }

    public function testDoesNotAddAnExistingColumn(): void
    {
        Stubs::db('/^SHOW COLUMNS/', [['Field' => 'fee_refunded']]);

        self::assertTrue(upgrade_module_5_2_0($this->module()));

        self::assertCount(1, Stubs::sql('execute'));
    }

    public function testFailsWhenTheTableCannotBeCreated(): void
    {
        Stubs::db('/ps_bk_group_transaction/', false);

        self::assertFalse(upgrade_module_5_2_0($this->module()));

        self::assertSame([], self::registeredHooks());
        self::assertSame([], Stubs::sql('executeS'));
    }

    public function testFailsWhenTheColumnCannotBeAdded(): void
    {
        Stubs::db('/^ALTER TABLE `ps_bk_buckaroo_fee`/', false);

        self::assertFalse(upgrade_module_5_2_0($this->module()));

        self::assertSame(['displayShoppingCartFooter'], self::registeredHooks());
    }
}
