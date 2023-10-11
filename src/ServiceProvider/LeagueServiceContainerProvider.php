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
use League\Container\ReflectionContainer;

class LeagueServiceContainerProvider implements ServiceContainerProviderInterface
{
    private $extendedServices = [];

    /** {@inheritDoc} */
    public function getService(string $serviceName)
    {
        $container = new Container();
        $container->delegate(new ReflectionContainer());
        $container->delegate(new PrestashopContainer());

        (new BaseServiceProvider($this->extendedServices))->register($container);

        return $container->get($serviceName);
    }

    public function extend(string $id, string $concrete = null)
    {
        $this->extendedServices[$id] = $concrete;

        return $this;
    }
}
