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
require_once _PS_MODULE_DIR_ . 'buckaroo3/config.php';
require_once _PS_MODULE_DIR_ . 'buckaroo3/vendor/autoload.php';

class CapayableIn3
{
    const VERSION_V2 = 'V2';
    const LOGO_IN3_IDEAL = 'in3_ideal';
    const LOGO_IN3_IDEAL_FILENAME = 'buckaroo_in3_ideal.svg?v1';
    const LOGO_DEFAULT = 'buckaroo_in3.png?v';
    public function isV3(): bool
    {
        return Configuration::get('BUCKAROO_IN3_API_VERSION') !== self::VERSION_V2;
    }
    public function getLogo(): string
    {
        if ($this->isV3()) {
            return self::LOGO_DEFAULT;
        }

        if (Configuration::get('BUCKAROO_IN3_PAYMENT_LOGO') === self::LOGO_IN3_IDEAL) {
            return self::LOGO_IN3_IDEAL_FILENAME;
        }

        return self::LOGO_DEFAULT;
    }
    public function getMethod(): string
    {
        return $this->isV3() ? 'in3' : 'in3Old';
    }
}
