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

use Buckaroo\PrestaShop\Src\Config\Config;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @return mixed
 * @throws Exception
 */
function upgrade_module_4_2_1($object)
{
    // Create new table if it doesn't exist
    $createTableQuery = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'bk_buckaroo_fee` (
        `id`                        INT(11) AUTO_INCREMENT PRIMARY KEY,
        `reference`                 TEXT NOT NULL,
        `id_cart`                   INT(11) NOT NULL,
        `id_order`                  INT(11) NOT NULL,
        `buckaroo_fee_tax_incl`     FLOAT,
        `buckaroo_fee_tax_excl`     FLOAT,
        `currency`                  TEXT NOT NULL,
        `created_at`                TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
    ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = ' . _MYSQL_ENGINE_ . ';';

    Db::getInstance()->execute($createTableQuery);

    // Check if the old table exists
    $tableExists = Db::getInstance()->executeS('SHOW TABLES LIKE "' . _DB_PREFIX_ . 'buckaroo_fee"');
    if ($tableExists) {
        // Move data from old table to new table
        $moveDataQuery = 'INSERT INTO `' . _DB_PREFIX_ . 'bk_buckaroo_fee` (reference, id_cart, buckaroo_fee_tax_incl, buckaroo_fee_tax_excl, currency, created_at)
        SELECT reference, id_cart, buckaroo_fee, buckaroo_fee, currency, created_at
        FROM `' . _DB_PREFIX_ . 'buckaroo_fee`';

        Db::getInstance()->execute($moveDataQuery);

        // Delete the old table
        $deleteOldTableQuery = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'buckaroo_fee`';
        Db::getInstance()->execute($deleteOldTableQuery);
    }

    // Example of additional existing operations
    Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'bk_giftcards` 
    ADD is_custom INT(11) DEFAULT 0 NOT NULL;');

    Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'bk_payment_methods WHERE name = "tinka"');

    \Configuration::updateValue(Config::PAYMENT_FEE_MODE, 'subtotal');
    \Configuration::updateValue(Config::PAYMENT_FEE_FRONTEND_LABEL, 'Payment Fee');

    return true;
}
