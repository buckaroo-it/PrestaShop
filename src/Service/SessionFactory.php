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

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

class SessionFactory
{
    /**
     * @param RequestStack $requestStack
     * @return SessionInterface
     */
    public static function create(RequestStack $requestStack): SessionInterface
    {
        $request = $requestStack->getCurrentRequest();
        if ($request === null) {
            throw new \RuntimeException('Request is not available. Session can only be created in a request context.');
        }
        return $request->getSession();
    }
}

