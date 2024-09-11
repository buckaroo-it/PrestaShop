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

use Buckaroo\PrestaShop\Src\Repository\RawOrderingRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @return mixed
 * @throws Exception
 */
function upgrade_module_4_4_0($object)
{
    $blikData = [
        'name' => 'blik',
        'label' => 'Blik',
        'icon' => 'Blik.svg',
        'template' => '',
        'is_payment_method' => '1',
    ];

    $keys = array_keys($blikData);
    $values = array_map(function ($value) {
        return pSQL($value);
    }, array_values($blikData));

    $insertQuery = 'INSERT INTO ' . _DB_PREFIX_ . 'bk_payment_methods (' . implode(', ', $keys) . ') VALUES ("' . implode('", "', $values) . '")';
    Db::getInstance()->execute($insertQuery);

    $paymentMethodId = Db::getInstance()->Insert_ID();

    // Insert default configuration for Knaken Settle
    $blikConfig = [
        'mode' => 'off'
    ];

    $configInsertQuery = 'INSERT INTO ' . _DB_PREFIX_ . 'bk_configuration (configurable_id, value) VALUES (' . (int)$paymentMethodId . ', \'' . pSQL(json_encode($blikConfig)) . '\')';
    Db::getInstance()->execute($configInsertQuery);

    $orderingRepository = new RawOrderingRepository();
    $orderingRepository->insertCountryOrdering();

    Db::getInstance()->execute('UPDATE ' . _DB_PREFIX_ . 'bk_payment_methods SET label = "Riverty" WHERE name = "afterpay"');

    return true;
}
