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

namespace Buckaroo\PrestaShop\Classes;

use Buckaroo\PrestaShop\Src\Repository\RawPaymentMethodRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Config
{
    /**
     * @throws \Exception
     */
    public static function getMode($key)
    {
        $paymentMethodRepository = new RawPaymentMethodRepository();
        $getPaymentMethodMode = $paymentMethodRepository->getPaymentMethodMode($key);

        if (\Configuration::get('BUCKAROO_TEST') == 1 && $getPaymentMethodMode == 'live') {
            return 'live';
        }

        return 'test';
    }
}
