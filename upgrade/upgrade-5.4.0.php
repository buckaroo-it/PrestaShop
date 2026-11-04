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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * 5.4.0 upgrade:
 * - Introduce opt-in error reporting to Buckaroo. Existing installations start
 *   as "not asked yet" so the merchant is prompted on the configuration page.
 *   An existing decision is never overwritten.
 *
 * @param object $object Module instance
 *
 * @return bool
 */
function upgrade_module_5_4_0($object)
{
    $value = Configuration::get('BUCKAROO_ERROR_REPORTING');

    if ($value !== '1' && $value !== '0') {
        Configuration::updateValue('BUCKAROO_ERROR_REPORTING', '');
    }

    return true;
}
