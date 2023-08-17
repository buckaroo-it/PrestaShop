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
function upgrade_module_4_0_0($object)
{
    Db::getInstance()->execute(
        "CREATE TABLE IF NOT EXISTS ". _DB_PREFIX_ ."bk_refund_request (id INT AUTO_INCREMENT NOT NULL, order_id INT NOT NULL, amount DOUBLE PRECISION NOT NULL, status VARCHAR(255) NOT NULL, refund_key VARCHAR(255) DEFAULT NULL, payment_key VARCHAR(255) DEFAULT NULL, payload LONGTEXT NOT NULL COMMENT '(DC2Type:json_array)', data LONGTEXT NOT NULL COMMENT '(DC2Type:json_array)', created_at DATETIME NOT NULL, INDEX order_id_index (order_id), INDEX key_index (refund_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = "._MYSQL_ENGINE_
    );

    return true;
}
