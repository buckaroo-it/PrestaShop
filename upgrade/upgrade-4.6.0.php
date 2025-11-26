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
function upgrade_module_4_6_0($object)
{
    // Helper: check if a payment method with given name exists
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

    // Insert Twint if it does not yet exist
    if (!$paymentMethodExists('twint')) {
        $twintData = [
            'name' => 'twint',
            'label' => 'Twint',
            'icon' => 'Twint.svg',
            'template' => '',
            'is_payment_method' => '1',
        ];

        $keys = array_keys($twintData);
        $values = array_map(function ($value) {
            return pSQL($value);
        }, array_values($twintData));

        $insertQuery = 'INSERT INTO ' . _DB_PREFIX_ . 'bk_payment_methods (' . implode(', ', $keys) . ') VALUES ("' . implode('", "', $values) . '")';
        $db->execute($insertQuery);

        $paymentMethodId = (int) $db->Insert_ID();

        if ($paymentMethodId && !$configExistsForMethod($paymentMethodId)) {
            $twintConfig = [
                'mode' => 'off',
            ];

            $configInsertQuery = 'INSERT INTO ' . _DB_PREFIX_ . 'bk_configuration (configurable_id, value) VALUES (' . $paymentMethodId . ', \'' . pSQL(json_encode($twintConfig)) . '\')';
            $db->execute($configInsertQuery);
        }
    }

    // Insert Swish if it does not yet exist
    if (!$paymentMethodExists('swish')) {
        $swishData = [
            'name' => 'swish',
            'label' => 'Swish',
            'icon' => 'Swish.svg',
            'template' => '',
            'is_payment_method' => '1',
        ];

        $swishKeys = array_keys($swishData);
        $swishValues = array_map(function ($value) {
            return pSQL($value);
        }, array_values($swishData));

        $swishInsertQuery = 'INSERT INTO ' . _DB_PREFIX_ . 'bk_payment_methods (' . implode(', ', $swishKeys) . ') VALUES ("' . implode('", "', $swishValues) . '")';
        $db->execute($swishInsertQuery);

        $swishPaymentMethodId = (int) $db->Insert_ID();

        if ($swishPaymentMethodId && !$configExistsForMethod($swishPaymentMethodId)) {
            $swishConfig = [
                'mode' => 'off',
            ];

            $swishConfigInsertQuery = 'INSERT INTO ' . _DB_PREFIX_ . 'bk_configuration (configurable_id, value) VALUES (' . $swishPaymentMethodId . ', \'' . pSQL(json_encode($swishConfig)) . '\')';
            $db->execute($swishConfigInsertQuery);
        }
    }

    // Insert Bizum if it does not yet exist
    if (!$paymentMethodExists('bizum')) {
        $bizumData = [
            'name' => 'bizum',
            'label' => 'Bizum',
            'icon' => 'Bizum.svg',
            'template' => '',
            'is_payment_method' => '1',
        ];

        $bizumKeys = array_keys($bizumData);
        $bizumValues = array_map(function ($value) {
            return pSQL($value);
        }, array_values($bizumData));

        $bizumInsertQuery = 'INSERT INTO ' . _DB_PREFIX_ . 'bk_payment_methods (' . implode(', ', $bizumKeys) . ') VALUES ("' . implode('", "', $bizumValues) . '")';
        $db->execute($bizumInsertQuery);

        $bizumPaymentMethodId = (int) $db->Insert_ID();

        if ($bizumPaymentMethodId && !$configExistsForMethod($bizumPaymentMethodId)) {
            $bizumConfig = [
                'mode' => 'off',
            ];

            $bizumConfigInsertQuery = 'INSERT INTO ' . _DB_PREFIX_ . 'bk_configuration (configurable_id, value) VALUES (' . $bizumPaymentMethodId . ', \'' . pSQL(json_encode($bizumConfig)) . '\')';
            $db->execute($bizumConfigInsertQuery);
        }
    }

    // Insert Wero if it does not yet exist
    if (!$paymentMethodExists('wero')) {
        $weroData = [
            'name' => 'wero',
            'label' => 'Wero',
            'icon' => 'Wero.svg',
            'template' => '',
            'is_payment_method' => '1',
        ];

        $weroKeys = array_keys($weroData);
        $weroValues = array_map(function ($value) {
            return pSQL($value);
        }, array_values($weroData));

        $weroInsertQuery = 'INSERT INTO ' . _DB_PREFIX_ . 'bk_payment_methods (' . implode(', ', $weroKeys) . ') VALUES ("' . implode('", "', $weroValues) . '")';
        $db->execute($weroInsertQuery);

        $weroPaymentMethodId = (int) $db->Insert_ID();

        if ($weroPaymentMethodId && !$configExistsForMethod($weroPaymentMethodId)) {
            $weroConfig = [
                'mode' => 'off',
            ];

            $weroConfigInsertQuery = 'INSERT INTO ' . _DB_PREFIX_ . 'bk_configuration (configurable_id, value) VALUES (' . $weroPaymentMethodId . ', \'' . pSQL(json_encode($weroConfig)) . '\')';
            $db->execute($weroConfigInsertQuery);
        }
    }

    $orderingRepository = new RawOrderingRepository();
    $orderingRepository->insertCountryOrdering();

    return true;
}
