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

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Src\ServiceProvider;

use League\Container\Container;

interface ServiceContainerProviderInterface
{
    /**
     * Gets service that is defined by module container.
     *
     * @param string $serviceName
     */
    public function getService(string $serviceName);

    /**
     * Extending the service. Useful for tests to dynamically change the implementations
     *
     * @param string $id
     * @param ?string $concrete - a class name
     *
     * @return mixed
     */
    public function extend(string $id, string $concrete = null);
}
