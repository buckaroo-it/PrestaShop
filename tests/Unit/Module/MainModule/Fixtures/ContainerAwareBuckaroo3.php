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

/**
 * Buckaroo3 as PrestaShop 1.7.6+ runs it: Module::getContainer() and
 * Module::has() exist (the core stubs leave them out).
 */
class ContainerAwareBuckaroo3 extends \Buckaroo3
{
    /** @var mixed container returned by getContainer(), or \Throwable to throw */
    public $moduleContainer;

    /** @var array<string, bool> ids has() answers true for */
    public $moduleServices = [];

    /** @var \Throwable|null thrown by has() */
    public $hasError;

    public function getContainer()
    {
        if ($this->moduleContainer instanceof \Throwable) {
            throw $this->moduleContainer;
        }

        return $this->moduleContainer;
    }

    public function has($id)
    {
        if ($this->hasError !== null) {
            throw $this->hasError;
        }

        return !empty($this->moduleServices[$id]);
    }
}
