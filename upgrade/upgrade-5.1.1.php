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
 * Adds Google Pay payment method to existing installations.
 *
 * @param object $object Module instance
 *
 * @return bool
 */
function upgrade_module_5_1_1($object)
{
    $db = Db::getInstance();

    $paymentMethodExists = function ($name) use ($db) {
        $sql = new DbQuery();
        $sql->select('id');
        $sql->from('bk_payment_methods');
        $sql->where('name = "' . pSQL($name) . '"');

        return (bool) $db->getValue($sql);
    };

    $configExistsForMethod = function ($paymentMethodId) use ($db) {
        $sql = new DbQuery();
        $sql->select('COUNT(1)');
        $sql->from('bk_configuration');
        $sql->where('configurable_id = ' . (int) $paymentMethodId);

        return (bool) $db->getValue($sql);
    };

    if (!$paymentMethodExists('googlepay')) {
        $googlePayData = [
            'name' => 'googlepay',
            'label' => 'Google Pay',
            'icon' => 'GooglePay.svg',
            'template' => '',
            'is_payment_method' => '1',
        ];

        $keys = array_keys($googlePayData);
        $values = array_map(function ($value) {
            return pSQL($value);
        }, array_values($googlePayData));

        $insertQuery = 'INSERT INTO ' . _DB_PREFIX_ . 'bk_payment_methods (' . implode(', ', $keys) . ') VALUES ("' . implode('", "', $values) . '")';
        $db->execute($insertQuery);

        $paymentMethodId = (int) $db->Insert_ID();

        if ($paymentMethodId && !$configExistsForMethod($paymentMethodId)) {
            $googlePayConfig = [
                'mode' => 'off',
            ];

            $configInsertQuery = 'INSERT INTO ' . _DB_PREFIX_ . 'bk_configuration (configurable_id, value) VALUES (' . $paymentMethodId . ', \'' . pSQL(json_encode($googlePayConfig)) . '\')';
            $db->execute($configInsertQuery);
        }
    }

    $orderingRepository = new RawOrderingRepository();
    $orderingRepository->insertCountryOrdering();

    return true;
}
