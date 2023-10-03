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
require_once dirname(__FILE__) . '/api/config/configcore.php';

use Buckaroo\PrestaShop\Src\Repository\PaymentMethodRepository;

class Config extends ConfigCore
{
    public const NAME = 'buckaroo3';
    public const PLUGIN_NAME = 'Buckaroo Payments';
    public const VERSION = '3.4.0';
    // ATTENTION: If log is enabled it can be potential vulnerability
    public const LOG = true;

    public static function get($key)
    {
        $val = Configuration::get($key);

        if (is_null($val) || $val === false) {
            return parent::get($key);
        } else {
            return $val;
        }
    }

    public static function getMode($key)
    {
        $paymentMethodRepository = new PaymentMethodRepository();
        $getPaymentMethodMode = $paymentMethodRepository->getPaymentMethodMode($key);
        if (Config::get('BUCKAROO_TEST') == 0 && $getPaymentMethodMode == 'live') {
            return 'live';
        }

        return 'test';
    }
}
