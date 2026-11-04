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

/*
 * Shared by the upgrade scripts that add or remove payment methods.
 *
 * The file name has no "-version" part, so PrestaShop does not run it as an
 * upgrade script itself; the scripts load it with require_once.
 */

if (!function_exists('buckaroo_upgrade_update_orderings')) {
    /**
     * Adds payment methods to, and removes them from, every existing payment
     * method ordering (the global one and the merchant's per-country ones),
     * keeping the merchant's order. Only when there is no ordering at all is
     * the global ordering built from every payment method in the database.
     *
     * @param Db $db
     * @param array $addIds payment method ids appended where they are missing
     * @param array $removeIds payment method ids taken out
     *
     * @return bool false when the ordering could not be built or updated
     */
    function buckaroo_upgrade_update_orderings($db, array $addIds, array $removeIds = [])
    {
        $addIds = array_values(array_filter(array_map('intval', $addIds)));
        $removeIds = array_values(array_filter(array_map('intval', $removeIds)));

        $orderings = $db->executeS('SELECT id, value FROM `' . _DB_PREFIX_ . 'bk_ordering`');

        if (!is_array($orderings)) {
            // Unreadable table: leave it alone, the shop falls back to the default order.
            PrestaShopLogger::addLog('Buckaroo upgrade: could not read the payment method ordering, it was not updated.', 2);

            return true;
        }

        if (empty($orderings)) {
            try {
                (new RawOrderingRepository())->insertCountryOrdering();
            } catch (Exception $e) {
                PrestaShopLogger::addLog('Buckaroo upgrade: could not build the payment method ordering: ' . $e->getMessage(), 3);

                return false;
            }

            return true;
        }

        $success = true;
        foreach ($orderings as $row) {
            $ids = json_decode($row['value'], true);
            if (!is_array($ids)) {
                continue;
            }

            $updated = array_values(array_filter($ids, function ($id) use ($removeIds) {
                return !in_array((int) $id, $removeIds, true);
            }));
            $present = array_map('intval', $updated);
            foreach ($addIds as $id) {
                if (!in_array($id, $present, true)) {
                    $updated[] = $id;
                    $present[] = $id;
                }
            }

            if ($updated === $ids) {
                continue;
            }

            $success = $db->update(
                'bk_ordering',
                ['value' => pSQL(json_encode($updated))],
                'id = ' . (int) $row['id']
            ) && $success;
        }

        if (!$success) {
            PrestaShopLogger::addLog('Buckaroo upgrade: could not update the payment method ordering.', 3);
        }

        return $success;
    }
}
