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
 * Gives a class an injected PrestaShop context with a lazy, backward compatible
 * fallback. Call setContext() with a Context object, or with a provider such as
 * the prestashop.adapter.legacy.context service; getContext() resolves it on
 * first use so the context is never read before PrestaShop initialised it.
 */
trait ContextAwareTrait
{
    /**
     * @var object|null Resolved PrestaShop Context
     */
    private $buckarooContext;

    /**
     * @var object|null Context object or provider exposing getContext()
     */
    private $buckarooContextSource;

    /**
     * @param object|null $context Context object or provider exposing getContext()
     */
    public function setContext($context): void
    {
        $this->buckarooContextSource = $context;
        $this->buckarooContext = null;
    }

    /**
     * @return object|null PrestaShop Context
     */
    public function getContext()
    {
        if ($this->buckarooContext === null) {
            $this->buckarooContext = ContextResolver::resolve($this->buckarooContextSource);
        }

        return $this->buckarooContext;
    }
}
