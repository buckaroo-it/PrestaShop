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
 * @author    Buckaroo.nl <plugins@buckaroo.nl>
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace Buckaroo\PrestaShop\Classes;

if (!defined('_PS_VERSION_')) {
    exit;
}

class CapayableIn3
{
    public const VERSION_V2 = 'V2';
    public const LOGO_DEFAULT = 'In3.svg?v';

    public function __construct($buckarooConfigService)
    {
        // Intentionally ignore stored API version configuration.
        // All In3 payments now always use the V3 API.
    }

    public function isV3(): bool
    {
        return true;
    }

    public function getLogo(): string
    {
        return self::LOGO_DEFAULT;
    }

    public function getMethod(): string
    {
        return 'in3';
    }
}
