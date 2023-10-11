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

use Interop\Container\ContainerInterface as InteropContainerInterface;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PrestashopContainer implements InteropContainerInterface
{
    /** @var SymfonyContainer|ContainerInterface|null */
    private $container;

    public function __construct()
    {
        $this->container = SymfonyContainer::getInstance();
    }

    public function get($id): object
    {
        return $this->container->get($id);
    }

    public function has($id): bool
    {
        if ($this->container === null) {
            return false;
        }

        return $this->container->has($id);
    }
}
