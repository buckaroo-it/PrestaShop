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

namespace Buckaroo\PrestaShop\Src\Repository;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Gateway environment (live/test) each Buckaroo invoice was started on,
 * so pushes and returns from the other environment can be rejected.
 */
class RawTransactionModeRepository
{
    public const TABLE = 'bk_transaction_mode';

    public function record(string $invoice, string $mode): bool
    {
        if ($invoice === '') {
            return false;
        }

        try {
            return \Db::getInstance()->insert(
                self::TABLE,
                [
                    'invoice' => pSQL($invoice),
                    'mode' => pSQL($mode),
                    'created_at' => date('Y-m-d H:i:s'),
                ],
                false,
                true,
                \Db::INSERT_IGNORE
            );
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog('Failed to store Buckaroo gateway mode: ' . $e->getMessage(), 3);

            return false;
        }
    }

    /**
     * @return string[]
     */
    public function getModes(string $invoice): array
    {
        if ($invoice === '') {
            return [];
        }

        try {
            $rows = \Db::getInstance()->executeS(
                'SELECT `mode` FROM `' . _DB_PREFIX_ . self::TABLE . '` WHERE `invoice` = \'' . pSQL($invoice) . '\''
            );
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog('Failed to read Buckaroo gateway mode: ' . $e->getMessage(), 3);

            return [];
        }

        return is_array($rows) ? array_column($rows, 'mode') : [];
    }
}
