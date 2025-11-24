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

namespace Buckaroo\PrestaShop\Src\Service;

use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

class CsrfTokenManagerFactory
{
    /**
     * @return CsrfTokenManagerInterface
     */
    public static function create(): CsrfTokenManagerInterface
    {
        $containers = self::getContainerCandidates();

        foreach ($containers as $container) {
            if (!$container->has('security.csrf.token_manager')) {
                continue;
            }

            try {
                return $container->get('security.csrf.token_manager');
            } catch (\Throwable $exception) {
                self::logContainerIssue('Unable to resolve security.csrf.token_manager', $exception);
            }
        }

        throw new \RuntimeException(
            'CSRF Token Manager service is not available. ' .
            'This service is required for admin functionality. ' .
            'Please ensure you are running PrestaShop with the security component enabled.'
        );
    }

    /**
     * Get the core PrestaShop service container
     *
     * @return ContainerInterface|null
     */
    private static function getCoreContainer(): ?ContainerInterface
    {
        return self::getKernelContainer()
            ?? self::getContextControllerContainer()
            ?? self::getLegacySymfonyContainer();
    }

    /**
     * @return ContainerInterface[]
     */
    private static function getContainerCandidates(): array
    {
        $candidates = [];
        $hashMap = [];

        $adder = function ($container) use (&$candidates, &$hashMap) {
            if (!$container instanceof ContainerInterface) {
                return;
            }

            $hash = spl_object_hash($container);
            if (isset($hashMap[$hash])) {
                return;
            }

            $hashMap[$hash] = true;
            $candidates[] = $container;
        };

        $adder(self::getCoreContainer());
        $adder(self::getKernelContainer());
        $adder(self::getContextControllerContainer());
        $adder(self::getLegacySymfonyContainer());

        return $candidates;
    }

    /**
     * @return ContainerInterface|null
     */
    private static function getKernelContainer(): ?ContainerInterface
    {
        global $kernel;

        if ($kernel instanceof KernelInterface) {
            try {
                $container = $kernel->getContainer();
                if ($container instanceof ContainerInterface) {
                    return $container;
                }
            } catch (\Throwable $exception) {
                self::logContainerIssue('Kernel::getContainer failed', $exception);
            }
        }

        return null;
    }

    /**
     * @return ContainerInterface|null
     */
    private static function getContextControllerContainer(): ?ContainerInterface
    {
        if (!class_exists('\Context')) {
            return null;
        }

        $context = \Context::getContext();
        if (!$context || !isset($context->controller) || !is_object($context->controller)) {
            return null;
        }

        if (!method_exists($context->controller, 'getContainer')) {
            return null;
        }

        try {
            $container = $context->controller->getContainer();
            if ($container instanceof ContainerInterface) {
                return $container;
            }
        } catch (\Throwable $exception) {
            self::logContainerIssue('Context controller container unavailable', $exception);
        }

        return null;
    }

    /**
     * @return ContainerInterface|null
     */
    private static function getLegacySymfonyContainer(): ?ContainerInterface
    {
        if (!class_exists('\PrestaShop\PrestaShop\Adapter\SymfonyContainer')) {
            return null;
        }

        try {
            $container = \PrestaShop\PrestaShop\Adapter\SymfonyContainer::getInstance();
            if ($container instanceof ContainerInterface) {
                return $container;
            }
        } catch (\Throwable $exception) {
            self::logContainerIssue('SymfonyContainer::getInstance failed', $exception);
        }

        return null;
    }

    /**
     * @param string $message
     * @param \Throwable $exception
     */
    private static function logContainerIssue($message, \Throwable $exception): void
    {
        if (!defined('_PS_MODE_DEV_') || !_PS_MODE_DEV_) {
            return;
        }

        $logMessage = sprintf('[Buckaroo3] %s: %s', $message, $exception->getMessage());
        if (class_exists('\PrestaShopLogger')) {
            \PrestaShopLogger::addLog($logMessage, 2);
        } else {
            error_log($logMessage);
        }
    }
}

