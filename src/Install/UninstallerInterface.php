<?php

namespace Buckaroo\Prestashop\Install;

interface UninstallerInterface
{
    /**
     * @return bool
     */
    public function uninstall();
}
