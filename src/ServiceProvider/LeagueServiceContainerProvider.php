<?php

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
        try {
            $container = new Container();
            $container->delegate(new ReflectionContainer());
            $container->delegate(new PrestashopContainer());

            (new BaseServiceProvider($this->extendedServices))->register($container);

            return $container->get($serviceName);
        } catch (\Exception $e) {
            echo 'Error when trying to get service: ' . $serviceName . PHP_EOL;
            echo 'Exception Message: ' . $e->getMessage() . PHP_EOL;
            die;
        }
    }

    public function extend(string $id, string $concrete = null)
    {
        $this->extendedServices[$id] = $concrete;

        return $this;
    }
}
