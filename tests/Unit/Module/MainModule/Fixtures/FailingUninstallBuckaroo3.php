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

use Buckaroo\PrestaShop\Src\Install\DatabaseTableUninstaller;
use Buckaroo\PrestaShop\Src\Install\IdinColumnsRemover;
use Buckaroo\PrestaShop\Src\Install\Uninstaller;

/**
 * Buckaroo3 whose core uninstaller fails with the errors in $uninstallErrors.
 */
class FailingUninstallBuckaroo3 extends \Buckaroo3
{
    /** @var string[] */
    public $uninstallErrors = [];

    protected function createUninstaller()
    {
        $errors = $this->uninstallErrors;

        return new class($this, $errors) extends Uninstaller {
            /** @var string[] */
            private $failures;

            public function __construct($module, array $failures)
            {
                parent::__construct($module, new DatabaseTableUninstaller(), new IdinColumnsRemover());
                $this->failures = $failures;
            }

            public function uninstall()
            {
                return false;
            }

            public function getErrors()
            {
                return $this->failures;
            }
        };
    }
}
