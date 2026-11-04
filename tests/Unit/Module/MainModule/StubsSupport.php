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

/*
 * PrestaShop core class missing from tests/Support that Buckaroo3::hookDisplayProductExtraContent() creates.
 */

namespace PrestaShop\PrestaShop\Core\Product {
    if (!class_exists(ProductExtraContent::class)) {
        class ProductExtraContent
        {
            /** @var string */
            private $title = '';

            /** @var string */
            private $content = '';

            public function setTitle($title)
            {
                $this->title = (string) $title;

                return $this;
            }

            public function getTitle()
            {
                return $this->title;
            }

            public function setContent($content)
            {
                $this->content = (string) $content;

                return $this;
            }

            public function getContent()
            {
                return $this->content;
            }
        }
    }
}

namespace BuckarooTest\Unit\Module\MainModule {
    use Symfony\Component\DependencyInjection\ContainerInterface;
    use Symfony\Component\HttpKernel\KernelInterface;

    /**
     * Generic service double: records every call and answers with the configured
     * value (or the result of a configured closure).
     */
    class ServiceDouble
    {
        /** @var array<int, array{0: string, 1: array}> */
        public $calls = [];

        /** @var array<string, mixed> */
        public $returns;

        public function __construct(array $returns = [])
        {
            $this->returns = $returns;
        }

        public function __call($method, $args)
        {
            $this->calls[] = [$method, $args];
            if (!array_key_exists($method, $this->returns)) {
                return null;
            }

            $value = $this->returns[$method];

            return $value instanceof \Closure ? $value(...$args) : $value;
        }

        /** @return array<int, array> argument lists of the calls to $method */
        public function callsTo(string $method): array
        {
            $found = [];
            foreach ($this->calls as $call) {
                if ($call[0] === $method) {
                    $found[] = $call[1];
                }
            }

            return $found;
        }
    }

    /** In-memory Symfony container. */
    class ContainerDouble implements ContainerInterface
    {
        /** @var array<string, mixed> */
        public $services;

        /** @var \Throwable|null thrown by get() */
        public $getError;

        public function __construct(array $services = [], ?\Throwable $getError = null)
        {
            $this->services = $services;
            $this->getError = $getError;
        }

        public function get($id, $invalidBehavior = 1)
        {
            if ($this->getError !== null) {
                throw $this->getError;
            }

            return $this->services[$id] ?? null;
        }

        public function has($id)
        {
            return array_key_exists($id, $this->services);
        }
    }

    class KernelDouble implements KernelInterface
    {
        /** @var mixed container or \Throwable */
        public $container;

        public function __construct($container)
        {
            $this->container = $container;
        }

        public function getContainer()
        {
            if ($this->container instanceof \Throwable) {
                throw $this->container;
            }

            return $this->container;
        }
    }

    /** Configuration store whose writes fail, to make the core installer throw. */
    class FailingConfigurationStore implements \ArrayAccess
    {
        /** @var array<string, mixed> */
        private $values = [];

        #[\ReturnTypeWillChange]
        public function offsetExists($offset)
        {
            return isset($this->values[$offset]);
        }

        #[\ReturnTypeWillChange]
        public function offsetGet($offset)
        {
            return $this->values[$offset] ?? null;
        }

        #[\ReturnTypeWillChange]
        public function offsetSet($offset, $value)
        {
            throw new \RuntimeException('configuration table is read-only');
        }

        #[\ReturnTypeWillChange]
        public function offsetUnset($offset)
        {
            unset($this->values[$offset]);
        }
    }
}
