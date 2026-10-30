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
 * Hold the order confirmation until the Buckaroo order is paid.
 * Shops that do not enable the option keep the current email behaviour.
 *
 * @param object $object Module instance
 *
 * @return bool
 */
function upgrade_module_5_4_0($object)
{
    if (Configuration::get('BUCKAROO_DISABLE_UNPAID_ORDER_EMAIL') === false) {
        Configuration::updateValue('BUCKAROO_DISABLE_UNPAID_ORDER_EMAIL', 0);
    }

    $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'bk_deferred_order_mail` (
        `id_order`         INT(11) UNSIGNED NOT NULL,
        `id_lang`          INT(11) UNSIGNED NOT NULL,
        `template`         VARCHAR(64) NOT NULL,
        `subject`          VARCHAR(255) NOT NULL,
        `template_vars`    MEDIUMTEXT NOT NULL,
        `recipient`        VARCHAR(255) NOT NULL,
        `recipient_name`   VARCHAR(255) NOT NULL,
        `template_path`    VARCHAR(255) NOT NULL,
        `id_shop`          INT(11) UNSIGNED NOT NULL,
        `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (`id_order`)
    ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = ' . _MYSQL_ENGINE_;

    if (!Db::getInstance()->execute($sql)) {
        return false;
    }

    // Gateway environment each invoice was started on; replies from the other one are rejected.
    $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'bk_transaction_mode` (
        `invoice`          VARCHAR(191) NOT NULL,
        `mode`             VARCHAR(10) NOT NULL,
        `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (`invoice`, `mode`)
    ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = ' . _MYSQL_ENGINE_;

    if (!Db::getInstance()->execute($sql)) {
        return false;
    }

    return $object->registerHook('actionOrderStatusPostUpdate');
}
