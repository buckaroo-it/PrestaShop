<?php

namespace Buckaroo\Src\Install;

interface UninstallerInterface
{
    /**
     * @return bool
     */
    public function uninstall();
}
