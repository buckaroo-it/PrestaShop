<?php

namespace BuckarooTest\Unit\Upgrade;

use BuckarooTest\Stubs;

require_once __DIR__ . '/UpgradeTestCase.php';

/**
 * upgrade-4.3.0.php: moves the payment fee table to bk_buckaroo_fee, adds the
 * custom giftcard flag, removes Tinka and sets the payment fee defaults.
 */
class Upgrade430Test extends UpgradeTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Loaded inside the test run so the files' top-level guard counts as covered.
        self::requireUpgrade('4.3.0');
    }

    public function testMovesTheOldFeeTableAndAddsTheGiftcardColumn(): void
    {
        Stubs::db('/^SHOW TABLES LIKE "ps_buckaroo_fee"$/', [['Tables_in_shop' => 'ps_buckaroo_fee']]);
        Stubs::db('/^SHOW COLUMNS FROM `ps_bk_giftcards` LIKE "is_custom"$/', []);
        // The table created by upgrade 3.3.2 has no created_at column.
        Stubs::db('/^SHOW COLUMNS FROM `ps_buckaroo_fee` LIKE "created_at"$/', []);

        self::assertTrue(upgrade_module_4_3_0($this->module()));

        $sql = Stubs::sql('execute');
        self::assertCount(5, $sql);
        self::assertStringStartsWith('CREATE TABLE IF NOT EXISTS `ps_bk_buckaroo_fee`', $sql[0]);
        self::assertStringContainsString('ENGINE = InnoDB', $sql[0]);
        self::assertStringStartsWith('INSERT INTO `ps_bk_buckaroo_fee` (reference, id_cart, id_order, buckaroo_fee_tax_incl, buckaroo_fee_tax_excl, currency, created_at)', $sql[1]);
        // Only columns the 3.3.2 table has; id_order resolved from the cart like the checkout does.
        self::assertStringContainsString(
            'SELECT f.reference, CAST(f.id_cart AS UNSIGNED), COALESCE((SELECT MIN(o.id_order) FROM `ps_orders` o WHERE o.id_cart = f.id_cart), 0), f.buckaroo_fee, f.buckaroo_fee, f.currency, NOW()',
            $sql[1]
        );
        self::assertStringContainsString('FROM `ps_buckaroo_fee` f', $sql[1]);
        self::assertSame('DROP TABLE IF EXISTS `ps_buckaroo_fee`', $sql[2]);
        self::assertStringContainsString('ADD is_custom INT(11) DEFAULT 0 NOT NULL', $sql[3]);
        self::assertSame('DELETE FROM ps_bk_payment_methods WHERE name = "tinka"', $sql[4]);

        self::assertSame('subtotal', \Configuration::get('BUCKAROO_PAYMENT_FEE_MODE'));
        self::assertSame('Payment Fee', \Configuration::get('BUCKAROO_PAYMENT_FEE_FRONTEND_LABEL'));
    }

    public function testSkipsTheMoveAndTheColumnWhenAlreadyDone(): void
    {
        Stubs::db('/^SHOW TABLES/', []);
        Stubs::db('/^SHOW COLUMNS/', [['Field' => 'is_custom']]);

        self::assertTrue(upgrade_module_4_3_0($this->module()));

        $sql = Stubs::sql('execute');
        self::assertCount(2, $sql);
        self::assertStringStartsWith('CREATE TABLE IF NOT EXISTS `ps_bk_buckaroo_fee`', $sql[0]);
        self::assertSame('DELETE FROM ps_bk_payment_methods WHERE name = "tinka"', $sql[1]);
    }

    public function testKeepsTheCreationDateWhenTheOldTableHasOne(): void
    {
        Stubs::db('/^SHOW TABLES/', [['Tables_in_shop' => 'ps_buckaroo_fee']]);
        Stubs::db('/^SHOW COLUMNS FROM `ps_buckaroo_fee` LIKE "created_at"$/', [['Field' => 'created_at']]);

        self::assertTrue(upgrade_module_4_3_0($this->module()));

        $copy = self::executed('/^INSERT INTO `ps_bk_buckaroo_fee`/');
        self::assertCount(1, $copy);
        self::assertStringContainsString('f.currency, f.created_at', $copy[0]);
        self::assertStringNotContainsString('NOW()', $copy[0]);
    }

    /**
     * Regression: the old table used to be dropped even when copying its rows
     * failed, losing every recorded payment fee.
     */
    public function testKeepsTheOldTableWhenTheCopyFails(): void
    {
        Stubs::db('/^SHOW TABLES/', [['Tables_in_shop' => 'ps_buckaroo_fee']]);
        Stubs::db('/^INSERT INTO `ps_bk_buckaroo_fee`/', false);

        self::assertFalse(upgrade_module_4_3_0($this->module()));

        self::assertSame([], self::executed('/^DROP TABLE/'));
        self::assertSame([], self::executed('/tinka/'), 'the upgrade stops so it can be retried');
        $logs = Stubs::callsTo('PrestaShopLogger::addLog');
        self::assertCount(1, $logs);
        self::assertStringContainsString('old buckaroo_fee table was kept', $logs[0][0]);
        self::assertSame(3, $logs[0][1]);
    }
}
