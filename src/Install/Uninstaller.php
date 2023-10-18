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

    public function __construct(
        UninstallerInterface $databaseUninstaller
    ) {
        $this->databaseUninstaller = $databaseUninstaller;
    }

    public function uninstall()
    {
        $this->deleteConfig();

        $this->uninstallTabs();

        $this->databaseUninstaller->uninstall();

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
            Config::LABEL_REFUND_RESTOCK,
            Config::LABEL_REFUND_CREDIT_SLIP,
            Config::LABEL_REFUND_VOUCHER,
            Config::LABEL_REFUND_CREATE_NEGATIVE_PAYMENT,
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
            'displayPDFInvoice',
            'displayBackOfficeHeader',
            'displayBeforeCarrier',
            'actionAdminCustomersListingFieldsModifier',
            'displayAdminProductsMainStepLeftColumnMiddle',
            'displayProductExtraContent',
        ];
    }
}
