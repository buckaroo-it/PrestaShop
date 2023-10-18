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

use Buckaroo\PrestaShop\Src\Service\BuckarooConfigService;

class CapayableIn3
{
    /**
     * @var BuckarooConfigService
     */
    protected $buckarooConfigService;
    protected $apiVersion;
    protected $paymentLogo;
    public const VERSION_V2 = 'V2';
    public const LOGO_IN3_IDEAL = 'in3_ideal';
    public const LOGO_IN3_IDEAL_FILENAME = 'In3_ideal.svg?v1';
    public const LOGO_DEFAULT = 'In3.svg?v';

    /** @var \Buckaroo3 */
    public $module;

    public function __construct()
    {
        $this->module = \Module::getInstanceByName('buckaroo3');
        $this->buckarooConfigService = new BuckarooConfigService($this->module->getEntityManager());
        $this->apiVersion = $this->buckarooConfigService->getSpecificValueFromConfig('in3', 'version');
        $this->paymentLogo = $this->buckarooConfigService->getSpecificValueFromConfig('in3', 'payment_logo');
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

        if ($this->paymentLogo === self::LOGO_IN3_IDEAL) {
            return self::LOGO_IN3_IDEAL_FILENAME;
        }

        return self::LOGO_DEFAULT;
    }

    public function getMethod(): string
    {
        return $this->isV3() ? 'in3' : 'in3Old';
    }
}
