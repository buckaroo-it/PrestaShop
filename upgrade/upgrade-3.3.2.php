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
function upgrade_module_3_3_2()
{
    return Db::getInstance()->execute("CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "buckaroo_fee` 
        ( `id` INT NOT NULL AUTO_INCREMENT , `reference` TEXT NOT NULL , `id_cart` TEXT NOT NULL , `buckaroo_fee` FLOAT, `currency` TEXT NOT NULL ,  PRIMARY KEY (id) )");
}
