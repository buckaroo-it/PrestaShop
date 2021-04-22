<?php
/**
 *
 *
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

require_once dirname(__FILE__) . '/api/config/configcore.php';

class Config extends ConfigCore
{
    const NAME        = 'buckaroo3';
    const PLUGIN_NAME = 'Buckaroo Payments';
    const VERSION     = '3.3.7';
    //ATTENTION: If log is enabled it can be potential vulnerability
    const LOG = false;

    public static function get($key)
    {
        switch ($key) {
            case 'RETURN_URL':
                $val = Config::get('BUCKAROO_TRANSACTION_RETURNURL');
                break;
            case 'CULTURE':
                $val = Config::getApiCulture();
                break;
            case 'BUCKAROO_CERTIFICATE_PATH':
                $val = _PS_MODULE_DIR_ . Config::NAME . '/' . Config::CERTIFICATE_PATH . Config::get(
                    'BUCKAROO_CERTIFICATE_FILE'
                );
                break;
            default:
                $val = Configuration::get($key);
        }

        if (is_null($val) || $val === false) {
            return parent::get($key);
        } else {
            return $val;
        }
    }

    public static function getMode($key)
    {
        $key = Tools::strtoupper($key);
        if (Config::get('BUCKAROO_TEST') == "0" && Config::get('BUCKAROO_' . $key . '_TEST') == "0") {
            return 'live';
        }
        return 'test';
    }

    public static function getSoftware()
    {
        $Software                  = new Software();
        $Software->PlatformName    = 'Prestashop';
        $Software->PlatformVersion = _PS_VERSION_;
        $Software->ModuleSupplier  = 'Buckaroo';
        $Software->ModuleName      = Config::PLUGIN_NAME;
        $Software->ModuleVersion   = Config::VERSION;
        return $Software;
    }

    public static function getApiCulture()
    {
        if (Config::get('BUCKAROO_TRANSACTION_CULTURE') == 'A') {
            $iso_code = Context::getContext()->language->iso_code;
        } else {
            $iso_code = Config::get('BUCKAROO_TRANSACTION_CULTURE');
        }
        switch ($iso_code) {
            case 'nl':
                return 'nl-NL';
            case 'fr':
                return 'fr-FR';
            case 'de':
                return 'de-DE';
            default:
                return 'en-US';
        }
    }
}
