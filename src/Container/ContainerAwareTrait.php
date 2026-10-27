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

namespace Buckaroo\PrestaShop\Src\Container;

use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use Symfony\Component\DependencyInjection\ContainerInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

trait ContainerAwareTrait
{
    /**
     * @var ContainerInterface|null
     */
    private $buckarooContainer;

    protected function hasService(string $serviceId): bool
    {
        try {
            return $this->getBuckarooContainer()->has($serviceId);
        } catch (\Throwable $throwable) {
            return false;
        }
    }

    /**
     * @throws \RuntimeException
     */
    protected function getService(string $serviceId)
    {
        return $this->getBuckarooContainer()->get($serviceId);
    }

    /**
     * @throws \RuntimeException
     */
    private function getBuckarooContainer(): ContainerInterface
    {
        if ($this->buckarooContainer instanceof ContainerInterface) {
            return $this->buckarooContainer;
        }

        $symfonyContainer = SymfonyContainer::getInstance();
        if ($symfonyContainer instanceof ContainerInterface) {
            return $this->buckarooContainer = $symfonyContainer;
        }

        $kernel = $this->bootLegacyKernel();

        return $this->buckarooContainer = $kernel->getContainer();
    }

    /**
     * @throws \RuntimeException
     */
    private function bootLegacyKernel()
    {
        // PrestaShop 1.7/8 ship a concrete AppKernel; PrestaShop 9 made it abstract
        // and boots FrontKernel for the shop front, so pick whichever is instantiable.
        $kernelClass = null;
        foreach (['FrontKernel', 'AppKernel'] as $candidate) {
            if (!class_exists('\\' . $candidate)) {
                $kernelFile = _PS_ROOT_DIR_ . '/app/' . $candidate . '.php';
                if (file_exists($kernelFile)) {
                    require_once $kernelFile;
                }
            }
            if (class_exists('\\' . $candidate)) {
                $reflection = new \ReflectionClass('\\' . $candidate);
                if (!$reflection->isAbstract()) {
                    $kernelClass = $reflection;
                    break;
                }
            }
        }

        if ($kernelClass === null) {
            throw new \RuntimeException('Unable to locate an instantiable PrestaShop kernel class.');
        }

        static $kernel;
        if (null === $kernel) {
            $kernel = $kernelClass->newInstance('prod', false);
            $kernel->boot();
        }

        return $kernel;
    }
}

