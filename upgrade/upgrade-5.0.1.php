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
 * Upgrade to 5.0.1
 *
 * - Rename iDEAL payment method label to "iDEAL | Wero"
 *   for existing installations.
 * - Update frontend_label in configuration JSON for the
 *   iDEAL method when it was explicitly set to "iDEAL".
 *
 * @param Module $module
 *
 * @return bool
 */
function upgrade_module_5_0_1($module)
{
    $db = Db::getInstance();

    // Locate the iDEAL payment method (name = "ideal")
    $sql = new DbQuery();
    $sql->select('id');
    $sql->from('bk_payment_methods');
    $sql->where('name = "ideal"');

    $idealId = (int) $db->getValue($sql);

    if ($idealId > 0) {
        // 1) Update the default label in bk_payment_methods
        $db->update(
            'bk_payment_methods',
            [
                'label' => pSQL('iDEAL | Wero'),
            ],
            'id = ' . (int) $idealId
        );

        // 2) Update frontend_label in bk_configuration JSON
        //    but ONLY when it was explicitly set to "iDEAL"
        $configQuery = new DbQuery();
        $configQuery->select('id, value');
        $configQuery->from('bk_configuration');
        $configQuery->where('configurable_id = ' . (int) $idealId);

        $rows = $db->executeS($configQuery);

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $id = (int) $row['id'];
                $valueJson = $row['value'];

                if ($valueJson === '' || $valueJson === null) {
                    continue;
                }

                $decoded = json_decode($valueJson, true);

                if (!is_array($decoded)) {
                    continue;
                }

                if (isset($decoded['frontend_label']) && $decoded['frontend_label'] === 'iDEAL') {
                    $decoded['frontend_label'] = 'iDEAL | Wero';

                    $db->update(
                        'bk_configuration',
                        [
                            'value' => pSQL(json_encode($decoded)),
                        ],
                        'id = ' . $id
                    );
                }
            }
        }
    }

    return true;
}

