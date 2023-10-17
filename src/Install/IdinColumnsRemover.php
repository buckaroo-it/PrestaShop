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

final class IdinColumnsRemover implements UninstallerInterface
{
    public function uninstall(): bool
    {
        $commands = $this->getCommands();

        foreach ($commands as $query) {
            if (false == \Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    private function getCommands(): array
    {
        $sql = [];

        // Remove the `buckaroo_idin` field from the `product` table if it exists
        if ($this->columnExists(_DB_PREFIX_ . 'product', 'buckaroo_idin')) {
            $sql[] = 'ALTER TABLE `' . _DB_PREFIX_ . 'product` DROP COLUMN `buckaroo_idin`';
        }

        // Remove the `buckaroo_idin_consumerbin` field from the `customer` table if it exists
        if ($this->columnExists(_DB_PREFIX_ . 'customer', 'buckaroo_idin_consumerbin')) {
            $sql[] = 'ALTER TABLE `' . _DB_PREFIX_ . 'customer` DROP COLUMN `buckaroo_idin_consumerbin`';
        }

        // Remove the `buckaroo_idin_iseighteenorolder` field from the `customer` table if it exists
        if ($this->columnExists(_DB_PREFIX_ . 'customer', 'buckaroo_idin_iseighteenorolder')) {
            $sql[] = 'ALTER TABLE `' . _DB_PREFIX_ . 'customer` DROP COLUMN `buckaroo_idin_iseighteenorolder`';
        }

        return $sql;
    }

    /**
     * Check if a column exists in a table.
     *
     * @param string $table Table name
     * @param string $column Column name
     * @return bool
     */
    private function columnExists(string $table, string $column): bool
    {
        $result = \Db::getInstance()->executeS('SHOW COLUMNS FROM `' . $table . '` LIKE "' . $column . '"');
        return (bool) $result;
    }
}
