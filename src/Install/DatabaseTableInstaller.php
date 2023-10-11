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

namespace Buckaroo\PrestaShop\Src\Install;

final class DatabaseTableInstaller implements InstallerInterface
{
    public function install()
    {
        $commands = $this->getCommands();

        foreach ($commands as $query) {
            if (false == \Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array
     */
    private function getCommands()
    {
        $sql = [];

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'bk_refund_request` (
				`id`              INT(11) AUTO_INCREMENT PRIMARY KEY,
				`order_id`        INT(11) NOT NULL,
				`amount`          DOUBLE PRECISION NOT NULL,
				`status`          VARCHAR(255) NOT NULL,
				`refund_key`      VARCHAR(255) NOT NULL,
				`payment_key`     VARCHAR(255) NOT NULL,
				`payload`         LONGTEXT NOT NULL,
                `data`            LONGTEXT NOT NULL,
				`created_at`      DATETIME NOT NULL,
				INDEX order_id_index (order_id), 
                INDEX key_index (refund_key) 
			) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = ' . _MYSQL_ENGINE_;

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'bk_payment_methods` (
				`id`              INT(11) AUTO_INCREMENT PRIMARY KEY,
				`name`            VARCHAR(255) NOT NULL,
				`label`           VARCHAR(255) NOT NULL,
				`icon`            VARCHAR(255) NOT NULL,
				`template`        VARCHAR(255) NOT NULL,
                `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                `updated_at`      DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX(`name`)
			) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = ' . _MYSQL_ENGINE_;

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'buckaroo_fee` (
				`id`              INT(11) AUTO_INCREMENT PRIMARY KEY,
				`reference`       TEXT NOT NULL,
				`id_cart`         TEXT NOT NULL,
				`buckaroo_fee`    FLOAT,
				`currency`        TEXT NOT NULL,
				`created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                `updated_at`      DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL ON UPDATE CURRENT_TIMESTAMP
			) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = ' . _MYSQL_ENGINE_;

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'bk_configuration` (
				`id`              INT(11) AUTO_INCREMENT PRIMARY KEY,
				`configurable_id` INT(11) NOT NULL,
				`value`           TEXT NOT NULL,
				`created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                `updated_at`      DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL ON UPDATE CURRENT_TIMESTAMP
			) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = ' . _MYSQL_ENGINE_;

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'bk_countries` (
				`id`              INT(11) AUTO_INCREMENT PRIMARY KEY,
				`country_id`      INT(11),
				`name`            VARCHAR(255) NOT NULL,
                `iso_code_2`      VARCHAR(2) NOT NULL,
                `iso_code_3`      VARCHAR(3) NOT NULL,
				`call_prefix`     INT(11),
				`icon`            VARCHAR(255),
				`created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                `updated_at`      DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL ON UPDATE CURRENT_TIMESTAMP
			) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = ' . _MYSQL_ENGINE_;

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'bk_ordering` (
				`id`              INT(11) AUTO_INCREMENT PRIMARY KEY,
				`country_id`      INT(11),
				`value`           TEXT NOT NULL,
				`created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                `updated_at`      DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL ON UPDATE CURRENT_TIMESTAMP
			) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = ' . _MYSQL_ENGINE_;

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'bk_creditcards` (
				`id`              INT(11) AUTO_INCREMENT PRIMARY KEY,
				`icon`            VARCHAR(255) NOT NULL,
				`name`            VARCHAR(255) NOT NULL,
				`service_code`    VARCHAR(255) NOT NULL,
                `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                `updated_at`      DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL ON UPDATE CURRENT_TIMESTAMP
			) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = ' . _MYSQL_ENGINE_;

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'bk_giftcards` (
				`id`              INT(11) AUTO_INCREMENT PRIMARY KEY,
				`code`            VARCHAR(255) NOT NULL,
				`name`            VARCHAR(255) NOT NULL,
				`logo`            VARCHAR(255) NOT NULL,
				`created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                `updated_at`      DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL ON UPDATE CURRENT_TIMESTAMP
			) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = ' . _MYSQL_ENGINE_;

        return $sql;
    }
}
