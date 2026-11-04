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

namespace BuckarooTest\Unit\Data;

use Buckaroo\PrestaShop\Src\Config\Config;
use Buckaroo\PrestaShop\Src\ErrorReporting\ErrorReportingConsent;
use Buckaroo\PrestaShop\Src\Install\CookieSameSiteRestorer;
use Buckaroo\PrestaShop\Src\Install\DatabaseTableInstaller;
use Buckaroo\PrestaShop\Src\Install\DatabaseTableUninstaller;
use Buckaroo\PrestaShop\Src\Install\IdinColumnsRemover;
use Buckaroo\PrestaShop\Src\Install\Installer;
use Buckaroo\PrestaShop\Src\Install\Uninstaller;
use Buckaroo\PrestaShop\Src\Install\UninstallerInterface;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;

/**
 * Module install/uninstall: hooks, configuration, tabs, tables and seed data.
 */
class InstallTest extends TestCase
{
    private const TABLES = [
        'bk_refund_request', 'bk_payment_methods', 'bk_buckaroo_fee', 'bk_configuration', 'bk_ordering',
        'bk_creditcards', 'bk_giftcards', 'bk_customer_idin', 'bk_product_idin', 'bk_group_transaction',
        'bk_deferred_order_mail', 'bk_transaction_mode',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Stubs::returns('Language::getLanguages', [['id_lang' => 1], ['id_lang' => 2]]);
        Stubs::returns('Tab::getIdFromClassName', function ($className) {
            return ['IMPROVE' => 3, 'AdminBuckaroo_B' => 77][$className] ?? 0;
        });
    }

    private static function hooksCalled(string $signature): array
    {
        return array_map(function ($args) {
            return $args[0];
        }, Stubs::callsTo($signature));
    }

    // ------------------------------------------------------------------
    // Installer
    // ------------------------------------------------------------------

    public function testInstallRegistersHooksConfigTabsTablesAndSeedData(): void
    {
        Stubs::db('/FROM `ps_bk_payment_methods`/', [['id' => 1], ['id' => 2]]);
        $module = new \Module();
        $installer = new Installer($module, new DatabaseTableInstaller());

        self::assertTrue($installer->install());
        self::assertSame([], $installer->getErrors());

        self::assertSame($installer->getHooks(), self::hooksCalled('Module->registerHook'));

        // Configuration defaults
        self::assertSame('', \Configuration::get(Config::BUCKAROO_MERCHANT_KEY));
        self::assertSame('', \Configuration::get(Config::BUCKAROO_SECRET_KEY));
        self::assertTrue(\Configuration::get(Config::LABEL_REFUND_CONF));
        self::assertFalse(\Configuration::get(Config::LABEL_REFUND_RESTOCK));
        self::assertTrue(\Configuration::get(Config::LABEL_REFUND_CREDIT_SLIP));
        self::assertSame('subtotal', \Configuration::get(Config::PAYMENT_FEE_MODE));
        self::assertSame('Payment Fee', \Configuration::get(Config::PAYMENT_FEE_FRONTEND_LABEL));
        self::assertSame(ErrorReportingConsent::NOT_ASKED, \Configuration::get(ErrorReportingConsent::CONFIG_KEY));
        self::assertSame(0, \Configuration::get(Config::BUCKAROO_DISABLE_UNPAID_ORDER_EMAIL));

        // Tabs
        $tabs = Stubs::persisted('Tab', 'save');
        self::assertSame(['AdminBuckaroo_B', 'AdminBuckaroo', 'AdminBuckaroolog'], array_map(function ($tab) {
            return $tab->class_name;
        }, $tabs));
        self::assertSame(3, $tabs[0]->id_parent);
        self::assertSame('buckaroo', $tabs[0]->icon);
        self::assertSame(77, $tabs[1]->id_parent);
        self::assertSame([1 => 'Logs', 2 => 'Logs'], $tabs[2]->name);
        self::assertSame('buckaroo3', $tabs[2]->module);

        // Tables are created before the seed data is written.
        $executed = Stubs::sql('execute');
        foreach (self::TABLES as $i => $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `ps_' . $table . '`', $executed[$i]);
        }
        self::assertSame(
            ['DELETE FROM ps_bk_payment_methods', 'DELETE FROM ps_bk_configuration', 'DELETE FROM ps_bk_ordering', 'DELETE FROM ps_bk_creditcards', 'DELETE FROM ps_bk_giftcards'],
            array_slice($executed, count(self::TABLES))
        );
        $insertedTables = array_unique(array_map(function ($query) {
            return $query['args'][0];
        }, array_filter(Stubs::$queries, function ($query) {
            return $query['method'] === 'insert';
        })));
        self::assertSame(['bk_payment_methods', 'bk_configuration', 'bk_ordering', 'bk_creditcards', 'bk_giftcards'], array_values($insertedTables));
    }

    public function testInstallStopsWhenTheConfigurationCannotBeWritten(): void
    {
        $installer = new class(new \Module(), new DatabaseTableInstaller()) extends Installer {
            protected function initConfig()
            {
                throw new \RuntimeException('read-only');
            }
        };

        self::assertFalse($installer->install());
        self::assertSame(['Unable to install config'], $installer->getErrors());
        self::assertSame([], Stubs::persisted('Tab'));
        self::assertSame([], Stubs::$queries);
    }

    public function testInstallTabAcceptsANumericParent(): void
    {
        $installer = new Installer(new \Module(), new DatabaseTableInstaller());

        self::assertTrue($installer->installTab('AdminX', 12, 'X', false));

        $tab = Stubs::persisted('Tab', 'save')[0];
        self::assertSame(12, $tab->id_parent);
        self::assertFalse($tab->active);
        self::assertSame('', $tab->icon);
        self::assertSame(0, Stubs::callCount('Tab::getIdFromClassName'));
    }

    public function testInstallTabReturnsFalseWhenTheTabCannotBeSaved(): void
    {
        Stubs::returns('Tab->save', false);
        $installer = new Installer(new \Module(), new DatabaseTableInstaller());

        self::assertFalse($installer->installTab('AdminX', 'IMPROVE', 'X'));
        self::assertSame([], Stubs::persisted('Tab'));
    }

    // ------------------------------------------------------------------
    // DatabaseTableInstaller / DatabaseTableUninstaller / IdinColumnsRemover
    // ------------------------------------------------------------------

    public function testDatabaseTableInstallerCreatesEveryTable(): void
    {
        self::assertTrue((new DatabaseTableInstaller())->install());

        $executed = Stubs::sql('execute');
        self::assertCount(count(self::TABLES), $executed);
        foreach ($executed as $sql) {
            self::assertStringContainsString('utf8mb4_unicode_ci', $sql);
            self::assertStringEndsWith('ENGINE = InnoDB', $sql);
        }
        self::assertStringContainsString('PRIMARY KEY (`invoice`, `mode`)', end($executed));
    }

    public function testDatabaseTableInstallerStopsAtTheFirstFailure(): void
    {
        Stubs::db('/`ps_bk_buckaroo_fee`/', false);

        self::assertFalse((new DatabaseTableInstaller())->install());
        self::assertCount(3, Stubs::sql('execute'));
    }

    public function testDatabaseTableUninstallerDropsTheModuleTables(): void
    {
        self::assertTrue((new DatabaseTableUninstaller())->uninstall());

        self::assertSame([
            'DROP TABLE IF EXISTS ps_bk_configuration',
            'DROP TABLE IF EXISTS ps_bk_payment_methods',
            'DROP TABLE IF EXISTS ps_bk_countries',
            'DROP TABLE IF EXISTS ps_bk_ordering',
            'DROP TABLE IF EXISTS ps_bk_creditcards',
            'DROP TABLE IF EXISTS ps_bk_giftcards',
            'DROP TABLE IF EXISTS ps_bk_customer_idin',
            'DROP TABLE IF EXISTS ps_bk_product_idin',
            'DROP TABLE IF EXISTS ps_bk_deferred_order_mail',
            'DROP TABLE IF EXISTS ps_bk_transaction_mode',
        ], Stubs::sql('execute'));
    }

    public function testDatabaseTableUninstallerStopsAtTheFirstFailure(): void
    {
        Stubs::db('/ps_bk_payment_methods/', false);

        self::assertFalse((new DatabaseTableUninstaller())->uninstall());
        self::assertCount(2, Stubs::sql('execute'));
    }

    /**
     * @return array<string, array{0: string[], 1: string[]}>
     */
    public function idinColumnsProvider(): array
    {
        return [
            'all columns present' => [
                ['product.buckaroo_idin', 'customer.buckaroo_idin_consumerbin', 'customer.buckaroo_idin_iseighteenorolder'],
                [
                    'ALTER TABLE `ps_product` DROP COLUMN `buckaroo_idin`',
                    'ALTER TABLE `ps_customer` DROP COLUMN `buckaroo_idin_consumerbin`',
                    'ALTER TABLE `ps_customer` DROP COLUMN `buckaroo_idin_iseighteenorolder`',
                ],
            ],
            'only the customer bin' => [
                ['customer.buckaroo_idin_consumerbin'],
                ['ALTER TABLE `ps_customer` DROP COLUMN `buckaroo_idin_consumerbin`'],
            ],
            'already removed' => [[], []],
        ];
    }

    /**
     * @dataProvider idinColumnsProvider
     */
    public function testIdinColumnsRemoverDropsOnlyExistingColumns(array $existing, array $expectedDrops): void
    {
        Stubs::db('/^SHOW COLUMNS/', function ($sql) use ($existing) {
            preg_match('/FROM `ps_(\w+)` LIKE "(\w+)"/', $sql, $m);

            return in_array($m[1] . '.' . $m[2], $existing, true) ? [['Field' => $m[2]]] : [];
        });

        self::assertTrue((new IdinColumnsRemover())->uninstall());

        self::assertSame([
            'SHOW COLUMNS FROM `ps_product` LIKE "buckaroo_idin"',
            'SHOW COLUMNS FROM `ps_customer` LIKE "buckaroo_idin_consumerbin"',
            'SHOW COLUMNS FROM `ps_customer` LIKE "buckaroo_idin_iseighteenorolder"',
        ], Stubs::sql('executeS'));
        self::assertSame($expectedDrops, Stubs::sql('execute'));
    }

    public function testIdinColumnsRemoverReportsAFailedDrop(): void
    {
        Stubs::db('/^SHOW COLUMNS/', [['Field' => 'x']]);
        Stubs::db('/^ALTER TABLE/', false);

        self::assertFalse((new IdinColumnsRemover())->uninstall());
        self::assertCount(1, Stubs::sql('execute'));
    }

    // ------------------------------------------------------------------
    // Uninstaller
    // ------------------------------------------------------------------

    public function testUninstallRemovesHooksConfigTabsTablesAndIdinColumns(): void
    {
        foreach ([Config::BUCKAROO_TEST, Config::BUCKAROO_MERCHANT_KEY, Config::PAYMENT_FEE_MODE, Config::LEGACY_PAYMENT_FEE_MODE, ErrorReportingConsent::CONFIG_KEY] as $key) {
            \Configuration::updateValue($key, 'x');
        }
        \Configuration::updateValue('PS_SHOP_NAME', 'Shop');
        $tabA = new \Tab();
        $tabA->id = 10;
        $tabB = new \Tab();
        $tabB->id = 11;
        Stubs::returns('Tab::getCollectionFromModule', [$tabA, $tabB]);
        Stubs::db('/FROM `ps_configuration`/', [['id_configuration' => 55, 'id_shop_group' => 1, 'id_shop' => 2]]);
        Stubs::db('/^SHOW COLUMNS/', []);
        $module = new \Module();

        $uninstaller = new Uninstaller($module, new DatabaseTableUninstaller(), new IdinColumnsRemover());

        self::assertTrue($uninstaller->uninstall());
        self::assertSame([], $uninstaller->getErrors());

        self::assertSame($uninstaller->getHooks(), self::hooksCalled('Module->unregisterHook'));
        self::assertSame(['PS_SHOP_NAME' => 'Shop'], \Configuration::$values, 'module settings and the SameSite marker are removed');
        self::assertSame([['buckaroo3']], Stubs::callsTo('Tab::getCollectionFromModule'));
        self::assertSame([$tabA, $tabB], Stubs::persisted('Tab', 'delete'));

        // The SameSite=None set by older module versions is restored to Lax.
        self::assertSame(
            ["SELECT `id_configuration`, `id_shop_group`, `id_shop` FROM `ps_configuration` WHERE `name` = 'PS_COOKIE_SAMESITE' AND `value` = 'None'"],
            array_slice(Stubs::sql('executeS'), 0, 1)
        );
        $update = array_values(array_filter(Stubs::$queries, function ($query) {
            return $query['method'] === 'update';
        }))[0];
        self::assertSame('configuration', $update['args'][0]);
        self::assertSame('Lax', $update['args'][1]['value']);
        self::assertSame('`id_configuration` = 55', $update['args'][2]);
        self::assertStringContainsString('(shop group 1, shop 2)', Stubs::callsTo('PrestaShopLogger::addLog')[0][0]);

        self::assertContains('DROP TABLE IF EXISTS ps_bk_configuration', Stubs::sql('execute'));
    }

    public function testUninstallerUnregistersEveryHookTheInstallerRegisters(): void
    {
        $installer = new Installer(new \Module(), new DatabaseTableInstaller());
        $uninstaller = new Uninstaller(new \Module(), new DatabaseTableUninstaller(), new IdinColumnsRemover());

        self::assertSame([], array_values(array_diff($installer->getHooks(), $uninstaller->getHooks())));
        foreach (['displayShoppingCartFooter', 'displayPaymentTop', 'displayProductAdditionalInfo', 'displayExpressCheckout'] as $hook) {
            self::assertContains($hook, $uninstaller->getHooks());
        }
    }

    public function testUninstallRunsTheGivenUninstallersAndRestorerInOrder(): void
    {
        $log = [];
        $recorder = function (string $name) use (&$log) {
            return new class($name, $log) implements UninstallerInterface {
                /** @var string */
                private $name;

                /** @var array */
                private $log;

                public function __construct(string $name, array &$log)
                {
                    $this->name = $name;
                    $this->log = &$log;
                }

                public function uninstall()
                {
                    $this->log[] = $this->name;

                    return true;
                }
            };
        };
        Stubs::returns('Tab::getCollectionFromModule', []);
        \Configuration::updateValue(CookieSameSiteRestorer::MARKER, 1);
        $restorer = new CookieSameSiteRestorer(function () use (&$log) {
            $log[] = 'find';

            return [];
        });

        $uninstaller = new Uninstaller(new \Module(), $recorder('tables'), $recorder('idin'), $restorer);

        self::assertTrue($uninstaller->uninstall());
        // Already restored once (marker set): no lookup; the marker is then forgotten.
        self::assertSame(['tables', 'idin'], $log);
        self::assertFalse(\Configuration::hasKey(CookieSameSiteRestorer::MARKER));
        self::assertSame([], Stubs::persisted('Tab'));
    }
}
