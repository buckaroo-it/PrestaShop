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
use Buckaroo\PrestaShop\Src\Repository\RawCountryRepository;
use Buckaroo\PrestaShop\Src\Repository\RawCreditCardsRepository;
use Buckaroo\PrestaShop\Src\Repository\RawOrderingRepository;
use Buckaroo\PrestaShop\Src\Repository\RawPaymentMethodRepository;

class Installer implements InstallerInterface
{
    /**
     * @var array
     */
    private $errors = [];

    /**
     * @var \Buckaroo3
     */
    private $module;

    /**
     * @var DatabaseTableInstaller
     */
    private $databaseTableInstaller;

    public function __construct(
        $module,
        DatabaseTableInstaller $databaseTableInstaller
    ) {
        $this->module = $module;

        $this->databaseTableInstaller = $databaseTableInstaller;
    }

    public function install()
    {
        foreach ($this->getHooks() as $hook) {
            $this->module->registerHook($hook);
        }

        try {
            $this->initConfig();
        } catch (\Exception $e) {
            $this->errors[] = $this->module->l('Unable to install config', Config::FILE_NAME);

            return false;
        }

        $this->installSpecificTabs();

        $this->copyEmailTemplates();
        $this->databaseTableInstaller->install();

        $countryRepository = new RawCountryRepository();
        $countryRepository->insertCountries();

        $paymentMethodRepository = new RawPaymentMethodRepository();
        $paymentMethodRepository->insertPaymentMethods();

        $orderingRepository = new RawOrderingRepository();
        $orderingRepository->insertCountryOrdering();

        $creditCardsRepository = new RawCreditCardsRepository();
        $creditCardsRepository->insertCreditCards();

        return true;
    }

    public function installSpecificTabs(): void
    {
        $this->installTab('AdminBuckaroo_B', 'IMPROVE', 'Buckaroo Payments', true, 'buckaroo');
        $this->installTab('AdminBuckaroo', 'AdminBuckaroo_B', 'Configure', true);
        $this->installTab('AdminBuckaroolog', 'AdminBuckaroo_B', 'Logs', true);
//        $this->installTab('AdminRefund', 'AdminBuckaroo_B', 'Buckaroo Refunds');
    }

    public function getErrors()
    {
        return $this->errors;
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

    /**
     * @return void
     */
    protected function initConfig()
    {
        \Configuration::updateValue(Config::BUCKAROO_TEST, 1);
        \Configuration::updateValue(Config::BUCKAROO_MERCHANT_KEY, '');
        \Configuration::updateValue(Config::BUCKAROO_SECRET_KEY, '');
        \Configuration::updateValue(Config::BUCKAROO_TRANSACTION_LABEL, '');
        \Configuration::updateValue(Config::BUCKAROO_TRANSACTION_FEE, '');

        \Configuration::updateValue(Config::LABEL_REFUND_RESTOCK, false);
        \Configuration::updateValue(Config::LABEL_REFUND_CREDIT_SLIP, true);
        \Configuration::updateValue(Config::LABEL_REFUND_VOUCHER, false);
        \Configuration::updateValue(Config::LABEL_REFUND_CREATE_NEGATIVE_PAYMENT, false);
    }

    public function installTab($className, $parent, $name, $active = true, $icon = '')
    {
        $idParent = is_int($parent) ? $parent : \Tab::getIdFromClassName($parent);

        $moduleTab = new \Tab();
        $moduleTab->class_name = $className;
        $moduleTab->id_parent = $idParent;
        $moduleTab->module = $this->module->name;
        $moduleTab->active = $active;
        $moduleTab->icon = $icon; /** @phpstan-ignore-line */
        $languages = \Language::getLanguages(true);
        foreach ($languages as $language) {
            $moduleTab->name[$language['id_lang']] = $name;
        }

        if (!$moduleTab->save()) {
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function copyEmailTemplates()
    {
        $source = _PS_ROOT_DIR_ . '/modules/buckaroo3/classes/Mail.php';
        $destinationDir = _PS_ROOT_DIR_ . '/override/classes/';
        $destinationFile = $destinationDir . 'Mail.php';

        // Check if destination directory exists, create it if necessary
        if (!is_dir($destinationDir)) {
            if (!mkdir($destinationDir, 0755, true)) {
                throw new \Exception("Failed to create destination directory '{$destinationDir}'");
            }
        }

        // Attempt to copy the file
        if (!copy($source, $destinationFile)) {
            throw new \Exception("Failed to copy file from '{$source}' to '{$destinationFile}'");
        }
    }
}
