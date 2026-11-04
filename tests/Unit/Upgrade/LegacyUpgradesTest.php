<?php

namespace BuckarooTest\Unit\Upgrade;

use BuckarooTest\Stubs;

require_once __DIR__ . '/UpgradeTestCase.php';

/**
 * Upgrade scripts 3.3.2 to 4.1.0: hook registration, legacy tables and
 * configuration clean-up.
 */
class LegacyUpgradesTest extends UpgradeTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Loaded inside the test run so the files' top-level guard counts as covered.
        foreach (['3.3.2', '3.3.3', '3.3.5', '3.3.8', '3.3.11', '3.4.1', '4.0.0', '4.0.1', '4.1.0'] as $version) {
            self::requireUpgrade($version);
        }
    }

    public function testUpgrade332CreatesTheLegacyFeeTable(): void
    {
        self::assertTrue(upgrade_module_3_3_2());

        $sql = Stubs::sql('execute');
        self::assertCount(1, $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `ps_buckaroo_fee`', $sql[0]);
        self::assertStringContainsString('`buckaroo_fee` FLOAT', $sql[0]);
    }

    public function testUpgrade332ReturnsTheDatabaseFailure(): void
    {
        Stubs::db('/ps_buckaroo_fee/', false);

        self::assertFalse(upgrade_module_3_3_2());
    }

    public function testUpgrade333RegistersTheConfirmationMailAndInvoiceHooks(): void
    {
        self::assertTrue(upgrade_module_3_3_3($this->module()));

        self::assertSame(
            ['displayOrderConfirmation', 'actionEmailSendBefore', 'displayPDFInvoice'],
            self::registeredHooks()
        );
    }

    /**
     * Regression: a failed hook used to skip registering the hooks after it.
     */
    public function testUpgrade333RegistersEveryHookEvenWhenOneFails(): void
    {
        Stubs::returns('Module->registerHook', function ($hook) {
            return $hook !== 'actionEmailSendBefore';
        });

        self::assertFalse(upgrade_module_3_3_3($this->module()));
        self::assertSame(
            ['displayOrderConfirmation', 'actionEmailSendBefore', 'displayPDFInvoice'],
            self::registeredHooks()
        );
    }

    public function testUpgrade335CopiesTheMailOverrideBelowTheShopRoot(): void
    {
        $moduleDir = _PS_ROOT_DIR_ . '/modules/buckaroo3';
        if (is_link($moduleDir) || is_link(_PS_ROOT_DIR_ . '/modules')) {
            self::markTestSkipped('The test shop root links to a real module directory; not copying files there.');
        }

        $source = $moduleDir . '/classes/Mail.php';
        $target = _PS_ROOT_DIR_ . '/override/classes/Mail.php';
        @mkdir(dirname($source), 0777, true);
        @mkdir(dirname($target), 0777, true);
        file_put_contents($source, '<?php // mail override');
        @unlink($target);

        try {
            self::assertTrue(upgrade_module_3_3_5());
            self::assertFileExists($target);
            self::assertSame('<?php // mail override', file_get_contents($target));
        } finally {
            @unlink($target);
            @unlink($source);
            @rmdir(dirname($source));
            @rmdir(dirname($target));
            @rmdir(_PS_ROOT_DIR_ . '/override');
        }
    }

    public function testUpgrade338RegistersTheIdinHooksWithoutTouchingCoreTables(): void
    {
        self::assertTrue(upgrade_module_3_3_8($this->module()));

        self::assertSame([
            'displayBeforeCarrier',
            'additionalCustomerFormFields',
            'actionSubmitAccountBefore',
            'actionAdminCustomersListingFieldsModifier',
            'displayAdminProductsMainStepLeftColumnMiddle',
        ], self::registeredHooks());
        self::assertSame([], Stubs::sql(), 'iDIN columns are no longer added to core tables');
    }

    public function testUpgrade338SucceedsEvenWhenAHookCannotBeRegistered(): void
    {
        Stubs::returns('Module->registerHook', false);

        self::assertTrue(upgrade_module_3_3_8($this->module()));
        self::assertCount(5, self::registeredHooks());
    }

    public function testUpgrade3311NoLongerChangesTheCookieSetting(): void
    {
        \Configuration::updateValue('PS_COOKIE_SAMESITE', 'Lax');

        self::assertTrue(upgrade_module_3_3_11($this->module()));

        self::assertSame('Lax', \Configuration::get('PS_COOKIE_SAMESITE'));
        self::assertSame([], Stubs::sql());
    }

    public function testUpgrade34RemovesOldMethodsAndRenamesCapayableToIn3(): void
    {
        $removed = [
            'BUCKAROO_EMPAYMENT_ENABLED', 'BUCKAROO_EMPAYMENT_TEST', 'BUCKAROO_EMPAYMENT_LABEL', 'BUCKAROO_EMPAYMENT_FEE',
            'BUCKAROO_DD_ENABLED', 'BUCKAROO_DD_TEST', 'BUCKAROO_DD_LABEL', 'BUCKAROO_DD_FEE',
            'BUCKAROO_DD_USECREDITMANAGMENT', 'BUCKAROO_DD_INVOICEDELAY', 'BUCKAROO_DD_DATEDUE', 'BUCKAROO_DD_MAXREMINDERLEVEL',
            'BUCKAROO_CERTIFICATE', 'BUCKAROO_CERTIFICATE_FILE', 'BUCKAROO_CERTIFICATE_THUMBPRINT',
            'BUCKAROO_TRANSACTION_RETURNURL', 'BUCKAROO_TRANSACTION_CULTURE',
        ];
        foreach ($removed as $key) {
            \Configuration::updateValue($key, 'old');
        }
        \Configuration::updateValue('BUCKAROO_CAPAYABLE_ENABLED', '1');
        \Configuration::updateValue('BUCKAROO_CAPAYABLE_TEST', '0');
        \Configuration::updateValue('BUCKAROO_CAPAYABLE_LABEL', 'Capayable');
        \Configuration::updateValue('BUCKAROO_CAPAYABLE_FEE', '2.50');
        \Configuration::updateValue('BUCKAROO_BUCKAROOPAYPAL_FEE', '0.35');
        \Configuration::updateValue('BUCKAROO_UNRELATED', 'kept');

        // upgrade-3.4.php runs at include time (it defines no function).
        include dirname(__DIR__, 3) . '/upgrade/upgrade-3.4.php';

        self::assertSame([
            'BUCKAROO_UNRELATED' => 'kept',
            'BUCKAROO_IN3_ENABLED' => '1',
            'BUCKAROO_IN3_TEST' => '0',
            'BUCKAROO_IN3_LABEL' => 'Capayable',
            'BUCKAROO_IN3_FEE' => '2.50',
            'BUCKAROO_PAYPAL_FEE' => '0.35',
        ], \Configuration::$values);
    }

    public function testUpgrade34CopiesMissingCapayableKeysAsFalse(): void
    {
        include dirname(__DIR__, 3) . '/upgrade/upgrade-3.4.php';

        self::assertFalse(\Configuration::get('BUCKAROO_IN3_ENABLED', null, null, null, 'unset'));
        self::assertFalse(\Configuration::get('BUCKAROO_PAYPAL_FEE', null, null, null, 'unset'));
    }

    public function testUpgrade341RegistersTheOrderTopHookAndCreatesTheOrderDataTable(): void
    {
        self::assertTrue(upgrade_module_3_4_1($this->module()));

        self::assertSame(['displayAdminOrderTop'], self::registeredHooks());
        $sql = Stubs::sql('execute');
        self::assertCount(1, $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `ps_buckaroo_order_data`', $sql[0]);
        self::assertStringContainsString('INDEX (id_order)', $sql[0]);
    }

    public function testUpgrade341IgnoresATableCreationFailure(): void
    {
        Stubs::db('/ps_buckaroo_order_data/', false);

        self::assertTrue(upgrade_module_3_4_1($this->module()));
    }

    /**
     * @dataProvider reinstallUpgrades
     */
    public function testUpgrade40xReinstallsTheModule(string $function): void
    {
        self::assertTrue($function($this->module()));

        $calls = array_values(array_filter(array_column(Stubs::$calls, 0), function ($signature) {
            return in_array($signature, ['Module->uninstall', 'Module->install'], true);
        }));
        self::assertSame(['Module->uninstall', 'Module->install'], $calls);
    }

    /**
     * @dataProvider reinstallUpgrades
     */
    public function testUpgrade40xReportsSuccessEvenWhenTheReinstallFails(string $function): void
    {
        Stubs::returns('Module->install', false);

        self::assertTrue($function($this->module()));
    }

    public static function reinstallUpgrades(): array
    {
        return [
            '4.0.0' => ['upgrade_module_4_0_0'],
            '4.0.1' => ['upgrade_module_4_0_1'],
        ];
    }

    public function testUpgrade410DropsCountryOrderingAndRenamesKbc(): void
    {
        self::assertTrue(upgrade_module_4_1_0($this->module()));

        self::assertSame([
            'DELETE FROM ps_bk_ordering WHERE country_id IS NOT NULL',
            'DROP TABLE IF EXISTS ps_bk_countries',
            'UPDATE ps_bk_payment_methods SET name = "kbcpaymentbutton" WHERE name = "kbc"',
        ], Stubs::sql('execute'));
    }
}
