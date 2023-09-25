<?php
namespace Buckaroo\Prestashop\Install;

interface InstallerInterface
{
    /**
     * @return bool
     */
    public function install();
}
