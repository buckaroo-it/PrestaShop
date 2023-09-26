<?php

namespace Buckaroo\Src\Install;

use Buckaroo\Src\Config\Config;

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
