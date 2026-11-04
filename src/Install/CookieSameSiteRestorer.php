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

namespace Buckaroo\PrestaShop\Src\Install;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Undo the PS_COOKIE_SAMESITE=None change made by module versions before 5.4.0.
 *
 * Those versions set the shop cookie SameSite setting to None on install and
 * on the 3.3.11 upgrade (on every configuration row). The module no longer
 * changes the setting. The first upgrade or uninstall that runs this class sets
 * every row still on None back to the PrestaShop default (Lax) and logs the
 * previous value. Afterwards the marker is set, so a None that the merchant
 * chooses later is left alone. A fresh install sets the marker straight away.
 */
class CookieSameSiteRestorer
{
    public const SETTING = 'PS_COOKIE_SAMESITE';
    public const MARKER = 'BUCKAROO_COOKIE_SAMESITE_RESTORED';
    public const MODULE_VALUE = 'None';
    public const DEFAULT_VALUE = 'Lax';

    /** @var callable(): array<int, array{id_configuration: mixed, id_shop_group: mixed, id_shop: mixed}> */
    private $findModuleRows;

    /** @var callable(int): void */
    private $restoreRow;

    /** @var callable(string): void */
    private $log;

    public function __construct(?callable $findModuleRows = null, ?callable $restoreRow = null, ?callable $log = null)
    {
        $this->findModuleRows = $findModuleRows ?: [$this, 'findModuleRowsInDatabase'];
        $this->restoreRow = $restoreRow ?: [$this, 'restoreRowInDatabase'];
        $this->log = $log ?: [$this, 'logToShop'];
    }

    /**
     * A fresh install never changed the setting, so there is nothing to restore later.
     */
    public function markHandled(): void
    {
        \Configuration::updateValue(self::MARKER, 1);
    }

    /**
     * @return int number of configuration rows set back to Lax
     */
    public function restore(): int
    {
        if (\Configuration::get(self::MARKER)) {
            return 0;
        }

        $restored = 0;
        foreach ((array) call_user_func($this->findModuleRows) as $row) {
            // Update the exact row: Configuration::updateValue() resolves the shop from the current context.
            call_user_func($this->restoreRow, (int) $row['id_configuration']);
            ++$restored;

            call_user_func($this->log, sprintf(
                'Buckaroo: %s changed from %s to %s (shop group %d, shop %d). An earlier Buckaroo module version had set %s.',
                self::SETTING,
                self::MODULE_VALUE,
                self::DEFAULT_VALUE,
                (int) ($row['id_shop_group'] ?? 0),
                (int) ($row['id_shop'] ?? 0),
                self::MODULE_VALUE
            ));
        }

        if ($restored > 0 && method_exists('Configuration', 'loadConfiguration')) {
            \Configuration::loadConfiguration();
        }

        $this->markHandled();

        return $restored;
    }

    public function forget(): void
    {
        \Configuration::deleteByName(self::MARKER);
    }

    /**
     * @return array<int, array{id_configuration: mixed, id_shop_group: mixed, id_shop: mixed}>
     */
    private function findModuleRowsInDatabase(): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT `id_configuration`, `id_shop_group`, `id_shop` FROM `' . _DB_PREFIX_ . 'configuration`'
            . " WHERE `name` = '" . pSQL(self::SETTING) . "'"
            . " AND `value` = '" . pSQL(self::MODULE_VALUE) . "'"
        );

        return is_array($rows) ? $rows : [];
    }

    private function restoreRowInDatabase(int $idConfiguration): void
    {
        \Db::getInstance()->update('configuration', [
            'value' => pSQL(self::DEFAULT_VALUE),
            'date_upd' => date('Y-m-d H:i:s'),
        ], '`id_configuration` = ' . $idConfiguration);
    }

    private function logToShop(string $message): void
    {
        \PrestaShopLogger::addLog($message, 1, null, 'Configuration', 0, true);
    }
}
