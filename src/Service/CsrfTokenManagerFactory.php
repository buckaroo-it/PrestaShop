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
        $container = self::getCoreContainer();
        
        if ($container && $container->has('security.csrf.token_manager')) {
            try {
                return $container->get('security.csrf.token_manager');
            } catch (\Exception $e) {
                // Service might not be public, try alternative approaches
            }
        }

        // Alternative: Try to get it via the request stack and session
        global $kernel;
        if ($kernel) {
            try {
                $container = $kernel->getContainer();
                if ($container instanceof ContainerInterface) {
                    // Try getting the service, even if not public
                    if ($container->has('security.csrf.token_manager')) {
                        try {
                            return $container->get('security.csrf.token_manager');
                        } catch (\Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException $e) {
                            // Service is not accessible, continue to next method
                        }
                    }
                }
            } catch (\Exception $e) {
                // Continue to next method
            }
        }

        // Last resort: Try to create it manually if we can get the session
        if (class_exists('\PrestaShop\PrestaShop\Adapter\SymfonyContainer')) {
            try {
                $container = \PrestaShop\PrestaShop\Adapter\SymfonyContainer::getInstance();
                if ($container && $container->has('security.csrf.token_manager')) {
                    return $container->get('security.csrf.token_manager');
                }
            } catch (\Exception $e) {
                // Continue
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
        // Try via kernel
        global $kernel;
        if ($kernel && method_exists($kernel, 'getContainer')) {
            try {
                $container = $kernel->getContainer();
                if ($container instanceof ContainerInterface) {
                    return $container;
                }
            } catch (\Exception $e) {
                // Continue to next method
            }
        }

        // Try via PrestaShop's SymfonyContainer
        if (class_exists('\PrestaShop\PrestaShop\Adapter\SymfonyContainer')) {
            try {
                return \PrestaShop\PrestaShop\Adapter\SymfonyContainer::getInstance();
            } catch (\Exception $e) {
                // Continue
            }
        }

        return null;
    }
}

