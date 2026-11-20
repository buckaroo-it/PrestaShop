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
 * 5.5.0 upgrade:
 * - Add the log level setting. Until now every Info entry was written; existing
 *   shops start at Warning, the default for new installations.
 *
 * @param object $object Module instance
 *
 * @return bool
 */
function upgrade_module_5_5_0($object)
{
    $levelKey = Buckaroo\PrestaShop\Src\Config\Config::BUCKAROO_LOG_LEVEL;
    if (Buckaroo\PrestaShop\Src\Logs\LogLevel::normalize(Configuration::get($levelKey)) === null) {
        return Configuration::updateValue($levelKey, Buckaroo\PrestaShop\Src\Logs\LogLevel::DEFAULT_LEVEL);
    }

    return true;
}
