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

class Uninstall
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
            Config::BUCKAROO_IDEAL_FEE,
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
}
