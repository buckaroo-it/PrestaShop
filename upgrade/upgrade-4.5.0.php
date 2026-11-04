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

require_once __DIR__ . '/ordering_helper.php';

/**
 * @return mixed
 *
 * @throws Exception
 */
function upgrade_module_4_5_0($object)
{
    $removed = Db::getInstance()->executeS('SELECT id FROM ' . _DB_PREFIX_ . 'bk_payment_methods WHERE name IN ("giropay", "sofortueberweisung")');
    $removedIds = is_array($removed) ? array_column($removed, 'id') : [];

    Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'bk_payment_methods WHERE name = "giropay"');
    Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'bk_payment_methods WHERE name = "sofortueberweisung"');
    Db::getInstance()->execute('UPDATE ' . _DB_PREFIX_ . 'bk_payment_methods SET label = "goSettle" WHERE name = "knaken"');
    Db::getInstance()->execute('UPDATE ' . _DB_PREFIX_ . 'bk_payment_methods SET icon = "GoSettle.svg" WHERE name = "knaken"');
    Db::getInstance()->execute('UPDATE ' . _DB_PREFIX_ . 'bk_payment_methods SET icon = "PayByBank.svg" WHERE name = "paybybank"');

    // Take the removed methods out of the existing (per-country) orderings instead of rebuilding them.
    return buckaroo_upgrade_update_orderings(Db::getInstance(), [], $removedIds);
}
