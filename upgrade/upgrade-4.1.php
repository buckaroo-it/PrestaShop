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
function upgrade_module_4_1($object)
{
    $sql = 'DELETE FROM ' . _DB_PREFIX_ . 'bk_ordering WHERE country_id IS NOT NULL';
    Db::getInstance()->execute($sql);

    Db::getInstance()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'bk_countries');

    Db::getInstance()->execute('UPDATE ' . _DB_PREFIX_ . 'bk_payment_methods SET name = "kbcpaymentbutton" WHERE name = "kbc"');
    return true;
}
