<?php

namespace Buckaroo\Prestashop\Install;

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
