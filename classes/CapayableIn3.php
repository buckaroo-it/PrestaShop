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
    protected $apiVersion;
    public const VERSION_V2 = 'V2';
    public const LOGO_IN3_IDEAL = 'in3_ideal';
    public const LOGO_IN3_IDEAL_FILENAME = 'In3_ideal.svg?v1';
    public const LOGO_DEFAULT = 'In3.svg?v';

    public function __construct($buckarooConfigService)
    {
        $this->apiVersion = $buckarooConfigService->getConfigValue('in3', 'version');
    }

    public function isV3(): bool
    {
        return $this->apiVersion !== self::VERSION_V2;
    }

    public function getLogo(): string
    {
        if (!$this->isV3()) {
            return self::LOGO_DEFAULT;
        }
        return self::LOGO_IN3_IDEAL_FILENAME;
    }

    public function getMethod(): string
    {
        return $this->isV3() ? 'in3' : 'in3Old';
    }
}
