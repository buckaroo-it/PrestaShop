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

use Buckaroo\PrestaShop\Src\ApplePay\DomainAssociation;
use Buckaroo\PrestaShop\Src\Install\CookieSameSiteRestorer;
use Buckaroo\PrestaShop\Src\Refund\Settings as RefundSettings;
use BuckarooTest\Stubs;

require_once __DIR__ . '/MainModuleTestCase.php';

/**
 * install()/uninstall(), the order state set up and the Apple Pay domain association file.
 */
class InstallUninstallTest extends MainModuleTestCase
{
    /** @var string */
    private $wellKnown;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wellKnown = _PS_ROOT_DIR_ . '/.well-known';
        $this->removeWellKnown();
        Stubs::returns('Language::getLanguages', [['id_lang' => 1], ['id_lang' => 2]]);
        \Configuration::updateValue('PS_LANG_DEFAULT', 1);
        \Configuration::updateValue('PS_OS_PAYMENT', 2);
        \Configuration::updateValue('PS_OS_CANCELED', 6);
    }

    protected function tearDown(): void
    {
        $this->removeWellKnown();
        parent::tearDown();
    }

    public function testInstallRegistersHooksAndConfiguresTheShop(): void
    {
        Stubs::returns('Shop::isFeatureActive', true);
        Stubs::returns('OrderState::getOrderStates', [
            ['id_order_state' => 2, 'name' => 'Payment accepted'],
            ['id_order_state' => 14, 'name' => 'Awaiting for Remote payment'],
        ]);
        $module = $this->createModule();

        self::assertTrue($module->install());

        self::assertSame([[\Shop::CONTEXT_ALL, null]], Stubs::callsTo('Shop::setContext'));
        $hooks = array_column(Stubs::callsTo('Module->registerHook'), 0);
        foreach (['paymentOptions', 'displayHeader', 'displayExpressCheckout', 'displayProductAdditionalInfo', 'actionEmailSendBefore'] as $hook) {
            self::assertContains($hook, $hooks);
        }
        self::assertSame(14, \Configuration::get('BUCKAROO_ORDER_STATE_DEFAULT'));
        self::assertSame(2, \Configuration::get('BUCKAROO_ORDER_STATE_SUCCESS'));
        self::assertSame(6, \Configuration::get('BUCKAROO_ORDER_STATE_FAILED'));
        self::assertSame(1, \Configuration::get(CookieSameSiteRestorer::MARKER));
        // The core installer enables the refund confirmation; the refund settings install turns it off again.
        self::assertFalse(\Configuration::get(RefundSettings::LABEL_REFUND_CONF));
        self::assertTrue(\Configuration::get(RefundSettings::LABEL_REFUND_CREDIT_SLIP));
        self::assertCount(0, Stubs::persisted('OrderState', 'add'), 'the existing state is reused');
        self::assertCount(3, Stubs::persisted('Tab'));
        self::assertFileExists($this->wellKnown . '/' . DomainAssociation::FILE_NAME);
        self::assertNotEmpty(Stubs::sql('execute'), 'the module tables are created');
    }

    public function testInstallCreatesTheAwaitingPaymentStateWhenMissing(): void
    {
        Stubs::returns('OrderState::getOrderStates', []);
        $module = $this->createModule();

        // The state logo goes to the shop img/os folder, which does not exist in tests:
        // the copy is skipped without a PHP warning and the skip is logged.
        $warnings = [];
        set_error_handler(function ($severity, $message) use (&$warnings) {
            $warnings[] = $message;

            return true;
        });
        try {
            $installed = $module->install();
        } finally {
            restore_error_handler();
        }

        self::assertTrue($installed);
        self::assertSame([], Stubs::callsTo('Shop::setContext'), 'single shop installs keep the context');
        $states = Stubs::persisted('OrderState', 'add');
        self::assertCount(1, $states);
        $state = $states[0];
        self::assertSame([1 => 'Awaiting for Remote payment'], $state->name);
        self::assertSame('buckaroo3', $state->module_name);
        self::assertSame('#FFF000', $state->color);
        self::assertSame(0, $state->send_email);
        self::assertFalse($state->unremovable);
        self::assertSame($state->id, \Configuration::get('BUCKAROO_ORDER_STATE_DEFAULT'));
        self::assertSame([], $warnings, 'a missing img/os folder does not warn');
        $logged = $module->logger->callsTo('logWarn');
        self::assertCount(1, $logged);
        self::assertStringStartsWith('Buckaroo3::copyLogo - order state logo not copied', $logged[0][0]);
    }

    public function testInstallKeepsGoingWhenTheOrderStateCannotBeSaved(): void
    {
        Stubs::returns('OrderState::getOrderStates', []);
        Stubs::returns('OrderState->add', false);
        $module = $this->createModule();

        self::assertTrue($module->install());
        // The unsaved state has no id, so the default state setting stays empty.
        self::assertTrue(\Configuration::hasKey('BUCKAROO_ORDER_STATE_DEFAULT'));
        self::assertNull(\Configuration::$values['BUCKAROO_ORDER_STATE_DEFAULT']);
        self::assertSame([], Stubs::persisted('OrderState'));
    }

    public function testInstallStopsWhenTheCoreInstallFails(): void
    {
        Stubs::returns('Module->install', false);
        $module = $this->createModule();

        self::assertFalse($module->install());
        self::assertSame(['Unable to install module'], $module->_errors);
        self::assertSame([], Stubs::callsTo('Module->registerHook'));
    }

    public function testInstallStopsWhenTheModuleInstallerFails(): void
    {
        $module = $this->createModule();
        $module->_errors = [];
        \Configuration::$values = new FailingConfigurationStore();

        self::assertFalse($module->install());
        self::assertSame(['Unable to install config'], $module->_errors);
        self::assertSame([], Stubs::persisted('Tab'));
    }

    public function testUninstallRemovesHooksConfigurationAndRefundSettings(): void
    {
        $refundSettings = new ServiceDouble();
        $module = $this->createModule(['buckaroo.refund.settings' => $refundSettings]);
        \Configuration::updateValue('BUCKAROO_MERCHANT_KEY', 'key');

        self::assertTrue($module->uninstall());

        self::assertCount(1, $refundSettings->callsTo('uninstall'));
        self::assertContains('paymentOptions', array_column(Stubs::callsTo('Module->unregisterHook'), 0));
        self::assertFalse(\Configuration::hasKey('BUCKAROO_MERCHANT_KEY'));
        self::assertSame(1, Stubs::callCount('Module->uninstall'));
    }

    public function testFailedUninstallMergesTheUninstallerErrors(): void
    {
        require_once __DIR__ . '/Fixtures/FailingUninstallBuckaroo3.php';
        /** @var FailingUninstallBuckaroo3 $module */
        $module = $this->createModule([], FailingUninstallBuckaroo3::class);
        $module->_errors = ['earlier'];
        $module->uninstallErrors = ['tables not dropped', 'hooks left'];

        self::assertFalse($module->uninstall());

        self::assertSame(['earlier', 'tables not dropped', 'hooks left'], $module->_errors);
        self::assertSame(0, Stubs::callCount('Module->uninstall'));
    }

    public function testUninstallReportsRefundSettingsFailures(): void
    {
        $module = $this->createModule(['buckaroo.refund.settings' => new \RuntimeException('boom')]);

        self::assertTrue($module->uninstall());
        self::assertSame(['Failed to uninstall buckaroo.refund.settings: boom'], $module->_errors);
    }

    public function testUninstallReturnsTheCoreResultWithoutRefundService(): void
    {
        Stubs::returns('Module->uninstall', false);
        $module = $this->createModule(['buckaroo.refund.settings' => null]);

        self::assertFalse($module->uninstall());
    }

    public function testDomainAssociationIsInstalledThenReportedPresent(): void
    {
        $module = $this->createModule();

        self::assertSame(DomainAssociation::STATUS_INSTALLED, $module->installApplePayDomainAssociation());
        self::assertSame(DomainAssociation::STATUS_PRESENT, $module->installApplePayDomainAssociation());
        self::assertSame([], $this->loggedMessages());
    }

    public function testDomainAssociationLogsAForeignFile(): void
    {
        mkdir($this->wellKnown, 0777, true);
        file_put_contents($this->wellKnown . '/' . DomainAssociation::FILE_NAME, 'someone else');
        $module = $this->createModule();

        self::assertSame(DomainAssociation::STATUS_OTHER_FILE, $module->installApplePayDomainAssociation());
        self::assertSame(
            ['Buckaroo: Apple Pay domain association file not placed in /.well-known/ (other_file)'],
            $this->loggedMessages()
        );
        self::assertSame('someone else', file_get_contents($this->wellKnown . '/' . DomainAssociation::FILE_NAME));
    }

    public function testDomainAssociationLogsAFailure(): void
    {
        // A file where the directory should be makes mkdir() fail.
        file_put_contents($this->wellKnown, 'not a directory');
        $module = $this->createModule();

        self::assertSame(DomainAssociation::STATUS_FAILED, $module->installApplePayDomainAssociation());
        self::assertSame(
            ['Buckaroo: Apple Pay domain association file not placed in /.well-known/ (failed)'],
            $this->loggedMessages()
        );
    }

    private function removeWellKnown(): void
    {
        if (is_file($this->wellKnown)) {
            unlink($this->wellKnown);
        } elseif (is_dir($this->wellKnown)) {
            foreach ((array) glob($this->wellKnown . '/*') as $file) {
                unlink($file);
            }
            rmdir($this->wellKnown);
        }
    }
}
