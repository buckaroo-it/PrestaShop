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
 * Removes the GoSettle (knaken) payment method from existing installations.
 *
 * @param object $object Module instance
 *
 * @return bool
 */
function upgrade_module_5_2_1($object)
{
    $db = Db::getInstance();

    $sql = new DbQuery();
    $sql->select('id');
    $sql->from('bk_payment_methods');
    $sql->where('name = "knaken"');

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

    $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'bk_payment_methods` WHERE name = "knaken"');

    return true;
}
