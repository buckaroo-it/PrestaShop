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
 * Creates the bk_group_transaction table and registers the displayShoppingCartFooter
 * hook for existing installations that are upgrading to 5.2.0.
 *
 * @param object $object Module instance
 *
 * @return bool
 */
function upgrade_module_5_2_0($object)
{
    $db = Db::getInstance();

    // Create the group-transaction table if it does not already exist.
    $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'bk_group_transaction` (
        `id`                    INT(11) AUTO_INCREMENT PRIMARY KEY,
        `cart_id`               INT(11) NOT NULL,
        `order_id`              INT(11) DEFAULT NULL,
        `transaction_key`       VARCHAR(255) NOT NULL,
        `group_transaction_id`  VARCHAR(255) DEFAULT NULL,
        `amount`                DOUBLE PRECISION NOT NULL,
        `refunded_amount`       DOUBLE PRECISION DEFAULT 0,
        `currency`              VARCHAR(10) NOT NULL,
        `card_code`             VARCHAR(100) DEFAULT NULL,
        `status`                INT(11) DEFAULT 0,
        `created_at`            TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
        INDEX cart_idx (cart_id),
        INDEX tx_key_idx (transaction_key)
    ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = ' . _MYSQL_ENGINE_;

    if (!$db->execute($sql)) {
        return false;
    }

    // Register the displayShoppingCartFooter hook so the "Already Paid" /
    // "Remaining Amount" lines appear in the checkout order summary.
    $object->registerHook('displayShoppingCartFooter');

    return true;
}
