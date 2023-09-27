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

final class DatabaseTableUninstaller implements UninstallerInterface
{
    public function uninstall(): bool
    {
        foreach ($this->getCommands() as $query) {
            if (false == \Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    private function getCommands(): array
    {
        $sql = [];

        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'bk_payment_methods`;';
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'bk_configuration`;';
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'bk_countries`;';
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'bk_ordering`;';
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'bk_creditcards`;';
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'bk_giftcards`;';
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'bk_refund_request`;';
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'buckaroo_fee`;';

        return $sql;
    }
}
