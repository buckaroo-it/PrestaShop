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
 * 5.3.0 upgrade:
 * - Remove the GoSettle (knaken) payment method from existing installations
 * - Remove the Payconiq payment method from existing installations
 * - Register displayPaymentTop so partial giftcard amounts are visible on checkout
 * - Add the Click to Pay payment method to existing installations
 * - Remove the global test/live setting; per-method mode is the only environment control
 *
 * @param object $object Module instance
 *
 * @return bool
 */
function upgrade_module_5_3_0($object)
{
    Configuration::deleteByName('BUCKAROO_TEST');

    $db = Db::getInstance();

    foreach (['knaken', 'payconiq'] as $methodName) {
        upgrade_module_5_3_0_remove_payment_method($db, $methodName);
    }

    $object->registerHook('displayPaymentTop');

    $paymentMethodId = upgrade_module_5_3_0_add_payment_method($db, [
        'name' => 'clicktopay',
        'label' => 'Click to Pay',
        'icon' => 'ClickToPay.svg',
        'template' => 'payment_clicktopay.tpl',
        'is_payment_method' => 1,
    ], [
        'mode' => 'off',
        'client_id' => '',
        'client_secret' => '',
        'merchant_identifier' => '',
    ]);

    if ($paymentMethodId > 0) {
        upgrade_module_5_3_0_append_to_ordering($db, $paymentMethodId);
    }

    return true;
}

/**
 * Remove a payment method and its configuration/ordering entries.
 *
 * @param Db $db
 * @param string $methodName
 */
function upgrade_module_5_3_0_remove_payment_method($db, $methodName)
{
    $sql = new DbQuery();
    $sql->select('id');
    $sql->from('bk_payment_methods');
    $sql->where('name = "' . pSQL($methodName) . '"');

    $paymentMethodId = (int) $db->getValue($sql);

    if ($paymentMethodId > 0) {
        $db->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'bk_configuration` WHERE configurable_id = ' . $paymentMethodId
        );

        $orderings = $db->executeS('SELECT id, value FROM `' . _DB_PREFIX_ . 'bk_ordering`');
        if (is_array($orderings)) {
            foreach ($orderings as $row) {
                $ids = json_decode($row['value'], true);
                if (!is_array($ids)) {
                    continue;
                }

                $filtered = array_values(array_filter($ids, function ($id) use ($paymentMethodId) {
                    return (int) $id !== $paymentMethodId;
                }));

                $db->update(
                    'bk_ordering',
                    [
                        'value' => pSQL(json_encode($filtered)),
                    ],
                    'id = ' . (int) $row['id']
                );
            }
        }

        $db->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'bk_payment_methods` WHERE id = ' . $paymentMethodId
        );
    }

    $db->execute(
        'DELETE FROM `' . _DB_PREFIX_ . 'bk_payment_methods` WHERE name = "' . pSQL($methodName) . '"'
    );
}

/**
 * Insert a payment method with its default configuration, unless it already exists.
 *
 * @param Db $db
 * @param array $methodData
 * @param array $defaultConfig
 *
 * @return int Id of the newly inserted method, or 0 when nothing was inserted
 */
function upgrade_module_5_3_0_add_payment_method($db, array $methodData, array $defaultConfig)
{
    $sql = new DbQuery();
    $sql->select('id');
    $sql->from('bk_payment_methods');
    $sql->where('name = "' . pSQL($methodData['name']) . '"');

    if ((int) $db->getValue($sql) > 0) {
        return 0;
    }

    if (!$db->insert('bk_payment_methods', [
        'name' => pSQL($methodData['name']),
        'label' => pSQL($methodData['label']),
        'icon' => pSQL($methodData['icon']),
        'template' => pSQL($methodData['template']),
        'is_payment_method' => (int) $methodData['is_payment_method'],
        'created_at' => date('Y-m-d H:i:s'),
    ])) {
        return 0;
    }

    $paymentMethodId = (int) $db->Insert_ID();

    $db->insert('bk_configuration', [
        'configurable_id' => $paymentMethodId,
        'value' => pSQL(json_encode($defaultConfig)),
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    return $paymentMethodId;
}

/**
 * Append the method to every ordering row. Methods that are absent from the
 * ordering are never rendered in checkout, and rebuilding the table would wipe
 * the merchant's per-country ordering.
 *
 * @param Db $db
 * @param int $paymentMethodId
 */
function upgrade_module_5_3_0_append_to_ordering($db, $paymentMethodId)
{
    $orderings = $db->executeS('SELECT id, value FROM `' . _DB_PREFIX_ . 'bk_ordering`');

    if (!is_array($orderings)) {
        return;
    }

    foreach ($orderings as $row) {
        $ids = json_decode($row['value'], true);
        if (!is_array($ids) || in_array($paymentMethodId, array_map('intval', $ids), true)) {
            continue;
        }

        $ids[] = $paymentMethodId;

        $db->update(
            'bk_ordering',
            ['value' => pSQL(json_encode(array_values($ids)))],
            'id = ' . (int) $row['id']
        );
    }
}

