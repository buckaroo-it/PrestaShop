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
 * @return mixed
 */
function upgrade_module_3_4_1($object)
{
    $object->registerHook('displayAdminOrderTop');

    $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'buckaroo_order_data`
        ( `id` INT NOT NULL AUTO_INCREMENT , `id_order` INT NOT NULL , `key` VARCHAR(255), `value` TEXT,  PRIMARY KEY (id), INDEX (id_order), INDEX (`key`) )';

    Db::getInstance()->execute($sql);

    return true;
}
