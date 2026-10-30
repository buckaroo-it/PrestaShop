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

namespace Buckaroo\PrestaShop\Src\Install;

use Buckaroo\PrestaShop\Src\Config\Config;

if (!defined('_PS_VERSION_')) {
    exit;
}
class Uninstaller
{
    /**
     * @var array
     */
    private $errors = [];

    /**
     * @var UninstallerInterface
     */
    private $databaseUninstaller;

    /**
     * @var UninstallerInterface
     */
    private $idinColumnsRemover;

    /**
     * @var \Buckaroo3
     */
    private $module;

    /**
     * @var CookieSameSiteRestorer
     */
    private $cookieSameSiteRestorer;

    public function __construct(
        $module,
        UninstallerInterface $databaseUninstaller,
        UninstallerInterface $idinColumnsRemover,
        ?CookieSameSiteRestorer $cookieSameSiteRestorer = null
    ) {
        $this->module = $module;
        $this->databaseUninstaller = $databaseUninstaller;
        $this->idinColumnsRemover = $idinColumnsRemover;
        $this->cookieSameSiteRestorer = $cookieSameSiteRestorer ?: new CookieSameSiteRestorer();
    }

    public function uninstall()
    {
        foreach ($this->getHooks() as $hook) {
            $this->module->unregisterHook($hook);
        }

        // Shops that never ran the 5.4.0 upgrade still have the None set by older versions.
        $this->cookieSameSiteRestorer->restore();
        $this->cookieSameSiteRestorer->forget();

        $this->deleteConfig();

        $this->uninstallTabs();

        $this->databaseUninstaller->uninstall();

        $this->idinColumnsRemover->uninstall();

        return true;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    private function deleteConfig()
    {
        $configurations = [
            Config::BUCKAROO_TEST,
            Config::BUCKAROO_MERCHANT_KEY,
            Config::BUCKAROO_SECRET_KEY,
            Config::BUCKAROO_TRANSACTION_LABEL,
            Config::BUCKAROO_TRANSACTION_FEE,
            Config::LABEL_REFUND_CONF,
            Config::LABEL_REFUND_RESTOCK,
            Config::LABEL_REFUND_CREDIT_SLIP,
            Config::LABEL_REFUND_VOUCHER,
            Config::LABEL_REFUND_CREATE_NEGATIVE_PAYMENT,
            Config::BUCKAROO_DISABLE_UNPAID_ORDER_EMAIL,
            Config::PAYMENT_FEE_MODE,
            Config::PAYMENT_FEE_FRONTEND_LABEL,
            Config::LEGACY_PAYMENT_FEE_MODE,
            Config::LEGACY_PAYMENT_FEE_FRONTEND_LABEL,
        ];

        $this->deleteConfigurations($configurations);
    }

    private function deleteConfigurations(array $configurations)
    {
        foreach ($configurations as $configuration) {
            \Configuration::deleteByName($configuration);
        }
    }

    private function uninstallTabs()
    {
        $moduleTabs = \Tab::getCollectionFromModule('buckaroo3');
        if (!empty($moduleTabs)) {
            foreach ($moduleTabs as $moduleTab) {
                $moduleTab->delete();
            }
        }
    }

    public function getHooks()
    {
        return [
            'displayHeader',
            'paymentReturn',
            'paymentOptions',
            'displayAdminOrderMainBottom',
            'displayOrderConfirmation',
            'actionEmailSendBefore',
            'actionOrderStatusPostUpdate',
            'displayPDFInvoice',
            'displayBackOfficeHeader',
            'displayProductExtraContent',
            'actionProductFormBuilderModifier',
            'actionAfterUpdateProductFormHandler',
        ];
    }
}
