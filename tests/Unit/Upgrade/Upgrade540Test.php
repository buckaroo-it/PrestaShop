<?php

namespace BuckarooTest\Unit\Upgrade;

use BuckarooTest\Stubs;

require_once __DIR__ . '/UpgradeTestCase.php';
require_once __DIR__ . '/StubsSupport.php';

/**
 * upgrade-5.4.0.php: deferred order mails, transaction mode table, cookie
 * SameSite restore, log directory and configuration key migration, Apple Pay
 * domain association, opt-in error reporting and the new hooks.
 */
class Upgrade540Test extends UpgradeTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Loaded inside the test run so the file's top-level guard counts as covered.
        self::requireUpgrade('5.4.0');
        // LogDirectoryMigrator resolves its target through CoreLogger.
        require_once _PS_MODULE_DIR_ . 'buckaroo3/api/corelogger.php';
        // The migrator's default source is _PS_MODULE_DIR_/buckaroo3/api/log/, which is the
        // repository itself in tests. It would move files out of the repo and remove the folder.
        if (is_dir(_PS_MODULE_DIR_ . 'buckaroo3/api/log')) {
            self::markTestSkipped('api/log exists in the repository; upgrade 5.4.0 would move it out.');
        }
    }

    public function testRunsEveryStepOnAShopUpgradingFrom53(): void
    {
        \Configuration::updateValue('PAYMENT_FEE_MODE', 'total');
        \Configuration::updateValue('PAYMENT_FEE_FRONTEND_LABEL', 'Fee');
        Stubs::db('/FROM `ps_configuration` WHERE `name` = \'PS_COOKIE_SAMESITE\' AND `value` = \'None\'/', [
            ['id_configuration' => '15', 'id_shop_group' => '1', 'id_shop' => '2'],
        ]);
        $module = new UpgradeApplePayModule();

        self::assertTrue(upgrade_module_5_4_0($module));

        // Deferred mails stay off by default.
        self::assertSame(0, \Configuration::get('BUCKAROO_DISABLE_UNPAID_ORDER_EMAIL'));

        $sql = Stubs::sql('execute');
        self::assertCount(2, $sql);
        self::assertStringStartsWith('CREATE TABLE IF NOT EXISTS `ps_bk_deferred_order_mail`', $sql[0]);
        self::assertStringContainsString('PRIMARY KEY (`id_order`)', $sql[0]);
        self::assertStringStartsWith('CREATE TABLE IF NOT EXISTS `ps_bk_transaction_mode`', $sql[1]);
        self::assertStringContainsString('PRIMARY KEY (`invoice`, `mode`)', $sql[1]);

        // Cookie SameSite back to Lax on the row the module changed, and marked as handled.
        $cookieUpdates = self::updates('configuration');
        self::assertCount(1, $cookieUpdates);
        self::assertSame('Lax', $cookieUpdates[0][1]['value']);
        self::assertSame('`id_configuration` = 15', $cookieUpdates[0][2]);
        self::assertSame(1, \Configuration::get('BUCKAROO_COOKIE_SAMESITE_RESTORED'));
        self::assertSame(1, Stubs::callCount('PrestaShopLogger::addLog'));

        // Fee keys carry the module prefix now.
        self::assertSame('total', \Configuration::get('BUCKAROO_PAYMENT_FEE_MODE'));
        self::assertSame('Fee', \Configuration::get('BUCKAROO_PAYMENT_FEE_FRONTEND_LABEL'));
        self::assertFalse(\Configuration::get('PAYMENT_FEE_MODE'));
        self::assertFalse(\Configuration::get('PAYMENT_FEE_FRONTEND_LABEL'));

        self::assertSame(1, $module->applePayInstalls);
        self::assertSame('', \Configuration::get('BUCKAROO_ERROR_REPORTING'), 'merchant is asked once');
        self::assertSame(
            ['actionOrderStatusPostUpdate', 'displayProductAdditionalInfo', 'displayExpressCheckout'],
            self::registeredHooks()
        );
    }

    public function testKeepsTheMerchantsExistingDecisions(): void
    {
        \Configuration::updateValue('BUCKAROO_DISABLE_UNPAID_ORDER_EMAIL', '1');
        \Configuration::updateValue('BUCKAROO_ERROR_REPORTING', '0');
        \Configuration::updateValue('BUCKAROO_COOKIE_SAMESITE_RESTORED', 1);
        \Configuration::updateValue('BUCKAROO_PAYMENT_FEE_MODE', 'subtotal');
        \Configuration::updateValue('PAYMENT_FEE_MODE', 'total');

        self::assertTrue(upgrade_module_5_4_0($this->module()));

        self::assertSame('1', \Configuration::get('BUCKAROO_DISABLE_UNPAID_ORDER_EMAIL'));
        self::assertSame('0', \Configuration::get('BUCKAROO_ERROR_REPORTING'));
        self::assertSame('subtotal', \Configuration::get('BUCKAROO_PAYMENT_FEE_MODE'));
        self::assertFalse(\Configuration::get('PAYMENT_FEE_MODE'));
        self::assertSame([], Stubs::sql('executeS'), 'cookie setting already restored');
        self::assertSame([], self::updates('configuration'));
    }

    public function testKeepsAnOptIn(): void
    {
        \Configuration::updateValue('BUCKAROO_ERROR_REPORTING', '1');

        self::assertTrue(upgrade_module_5_4_0($this->module()));

        self::assertSame('1', \Configuration::get('BUCKAROO_ERROR_REPORTING'));
    }

    public function testFailsWhenTheDeferredMailTableCannotBeCreated(): void
    {
        Stubs::db('/ps_bk_deferred_order_mail/', false);
        $module = new UpgradeApplePayModule();

        self::assertFalse(upgrade_module_5_4_0($module));

        self::assertCount(1, Stubs::sql('execute'));
        self::assertSame(0, $module->applePayInstalls);
        self::assertSame([], self::registeredHooks());
        self::assertFalse(\Configuration::get('BUCKAROO_COOKIE_SAMESITE_RESTORED'));
    }

    public function testFailsWhenTheTransactionModeTableCannotBeCreated(): void
    {
        Stubs::db('/ps_bk_transaction_mode/', false);

        self::assertFalse(upgrade_module_5_4_0($this->module()));

        self::assertCount(2, Stubs::sql('execute'));
        self::assertSame([], self::registeredHooks());
        self::assertFalse(\Configuration::get('BUCKAROO_ERROR_REPORTING'));
    }

    /**
     * Regression: a failed hook used to skip registering the hooks after it.
     */
    public function testFailsButRegistersEveryHookWhenOneCannotBeRegistered(): void
    {
        Stubs::returns('Module->registerHook', function ($hook) {
            return $hook !== 'displayProductAdditionalInfo';
        });

        self::assertFalse(upgrade_module_5_4_0($this->module()));

        self::assertSame(
            ['actionOrderStatusPostUpdate', 'displayProductAdditionalInfo', 'displayExpressCheckout'],
            self::registeredHooks()
        );
    }
}
