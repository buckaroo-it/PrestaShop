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

namespace Buckaroo\PrestaShop\Src\Context;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Resolves the PrestaShop context without a static lookup on the core Context class.
 *
 * PrestaShop hands the request context to modules (Module::$context) and to
 * controllers ($this->context), and exposes it to the service container through
 * the prestashop.adapter.legacy.context service. This resolver lets services and
 * helpers accept any of those sources and only falls back to the module instance
 * when nothing was injected, which keeps existing constructors backward compatible.
 *
 * Precedence:
 *  1. a context object passed explicitly;
 *  2. a provider exposing getContext(), e.g. PrestaShop\PrestaShop\Adapter\LegacyContext;
 *  3. the context PrestaShop initialised on the Buckaroo module instance.
 */
final class ContextResolver
{
    public const MODULE_NAME = 'buckaroo3';

    /**
     * @param object|null $candidate Context object or provider exposing getContext()
     *
     * @return object|null PrestaShop Context, or null when none is available yet
     */
    public static function resolve($candidate = null)
    {
        $context = self::fromCandidate($candidate);
        if ($context !== null) {
            return $context;
        }

        return self::fromModule();
    }

    /**
     * @param object|null $candidate
     *
     * @return object|null
     */
    public static function fromCandidate($candidate)
    {
        if (!is_object($candidate)) {
            return null;
        }

        if (self::looksLikeContext($candidate)) {
            return $candidate;
        }

        if (method_exists($candidate, 'getContext')) {
            try {
                $context = $candidate->getContext();
            } catch (\Throwable $exception) {
                return null;
            }

            if (self::looksLikeContext($context)) {
                return $context;
            }
        }

        return null;
    }

    /**
     * The module instance owns the context PrestaShop created for the current
     * request; it is the same object the core passes to hooks and controllers.
     * Module::$context is protected in the core, so the module exposes it
     * through Buckaroo3::getModuleContext().
     *
     * @return object|null
     */
    public static function fromModule()
    {
        if (!class_exists('\Module')) {
            return null;
        }

        try {
            $module = \Module::getInstanceByName(self::MODULE_NAME);
        } catch (\Throwable $exception) {
            return null;
        }

        if (!is_object($module) || !method_exists($module, 'getModuleContext')) {
            return null;
        }

        $context = $module->getModuleContext();

        return self::looksLikeContext($context) ? $context : null;
    }

    /**
     * Duck-typed check so unit tests can use lightweight stand-ins.
     *
     * @param mixed $object
     */
    public static function looksLikeContext($object): bool
    {
        if (!is_object($object)) {
            return false;
        }

        return property_exists($object, 'shop')
            && property_exists($object, 'language')
            && property_exists($object, 'currency');
    }
}
