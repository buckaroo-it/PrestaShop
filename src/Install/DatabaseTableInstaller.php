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
     * @author    Buckaroo.nl <plugins@buckaroo.nl>
     * @copyright Copyright (c) Buckaroo B.V.
     * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
     */

    namespace Buckaroo\PrestaShop\Src\Install;

    if (!defined('_PS_VERSION_')) {
        exit;
    }

    final class DatabaseTableInstaller implements InstallerInterface
    {
        public function install()
        {
            $commands = $this->getCommands();

            foreach ($commands as $query) {
                if (!\Db::getInstance()->execute($query)) {
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
                `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
				INDEX order_id_index (order_id),
                INDEX key_index (refund_key)
			) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = ' . _MYSQL_ENGINE_;

            $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'bk_payment_methods` (
				`id`                INT(11) AUTO_INCREMENT PRIMARY KEY,
				`name`              VARCHAR(255) NOT NULL,
				`label`             VARCHAR(255) NOT NULL,
				`icon`              VARCHAR(255) NOT NULL,
				`template`          VARCHAR(255) NOT NULL,
				`is_payment_method` INT(11) NOT NULL,
                `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
                INDEX(`name`)
			) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = ' . _MYSQL_ENGINE_;

            $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'buckaroo_fee` (
				`id`              INT(11) AUTO_INCREMENT PRIMARY KEY,
				`reference`       TEXT NOT NULL,
				`id_cart`         TEXT NOT NULL,
				`buckaroo_fee`    FLOAT,
				`currency`        TEXT NOT NULL,
                `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
			) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = ' . _MYSQL_ENGINE_;

            $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'bk_configuration` (
				`id`              INT(11) AUTO_INCREMENT PRIMARY KEY,
				`configurable_id` INT(11) NOT NULL,
				`value`           TEXT NOT NULL,
                `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
			) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = ' . _MYSQL_ENGINE_;

            $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'bk_ordering` (
				`id`              INT(11) AUTO_INCREMENT PRIMARY KEY,
				`country_id`      INT(11),
				`value`           TEXT NOT NULL,
                `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
			) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = ' . _MYSQL_ENGINE_;

            $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'bk_creditcards` (
				`id`              INT(11) AUTO_INCREMENT PRIMARY KEY,
				`icon`            VARCHAR(255) NOT NULL,
				`name`            VARCHAR(255) NOT NULL,
				`service_code`    VARCHAR(255) NOT NULL,
                `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
			) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = ' . _MYSQL_ENGINE_;

            $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'bk_giftcards` (
				`id`              INT(11) AUTO_INCREMENT PRIMARY KEY,
				`code`            VARCHAR(255) NOT NULL,
				`name`            VARCHAR(255) NOT NULL,
				`logo`            VARCHAR(255) NOT NULL,
				`is_custom`       INT(11) DEFAULT 0 NOT NULL,
                `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
			) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = ' . _MYSQL_ENGINE_;

            $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'bk_customer_idin` (
				`id`                                INT(11) AUTO_INCREMENT PRIMARY KEY,
				`customer_id`                       INT(11) NOT NULL,
				`buckaroo_idin_consumerbin`         VARCHAR(255) NULL,
				`buckaroo_idin_iseighteenorolder`   VARCHAR(255) NULL,
                `created_at`                        TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
			) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = ' . _MYSQL_ENGINE_;

            $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'bk_product_idin` (
				`id`               INT(11) AUTO_INCREMENT PRIMARY KEY,
				`product_id`       INT(11) NOT NULL,
				`buckaroo_idin`    TINYINT(1) UNSIGNED DEFAULT 0,
                `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
			) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = ' . _MYSQL_ENGINE_;

            return $sql;
        }
    }
