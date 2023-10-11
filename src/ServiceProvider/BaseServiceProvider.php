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

require_once dirname(__FILE__) . '/../../library/logger.php';

use Buckaroo\PrestaShop\Src\Service\BuckarooConfigService;
use Buckaroo\PrestaShop\Src\Service\BuckarooCountriesService;
use Buckaroo\PrestaShop\Src\Service\BuckarooFeeService;
use Buckaroo\PrestaShop\Src\Service\BuckarooOrderingService;
use League\Container\Container;

/**
 * Load base services here which are usually required
 */
final class BaseServiceProvider
{
    private $extendedServices;

    public $symContainer;

    public function __construct($extendedServices)
    {
        $this->extendedServices = $extendedServices;
        $this->setContainer();
    }

    private function setContainer()
    {
        global $kernel;

        if (!$kernel) {
            require_once _PS_ROOT_DIR_ . '/app/AppKernel.php';
            $kernel = new \AppKernel('prod', false);
            $kernel->boot();
        }
        $this->symContainer = $kernel->getContainer();
    }

    public function register(Container $container)
    {
        $entityManager = $this->symContainer->get('doctrine.orm.entity_manager');
        $this->addService($container, BuckarooConfigService::class, BuckarooConfigService::class)
            ->withArgument($entityManager)
            ->withArgument(new \Logger(\Logger::INFO, $fileName = ''));

        $this->addService($container, BuckarooFeeService::class, BuckarooFeeService::class)
            ->withArgument($entityManager)
            ->withArgument(new \Logger(\Logger::INFO, $fileName = ''));

        $this->addService($container, BuckarooOrderingService::class, BuckarooOrderingService::class)
            ->withArgument($entityManager);

        $this->addService($container, BuckarooCountriesService::class, BuckarooCountriesService::class)
            ->withArgument($entityManager);
    }

    private function addService(Container $container, $className, $service)
    {
        return $container->add($className, $this->getService($className, $service));
    }

    public function getService($className, $service)
    {
        if (isset($this->extendedServices[$className])) {
            return $this->extendedServices[$className];
        }

        return $service;
    }
}
