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

use Buckaroo\PrestaShop\Src\Config\Config;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Move Configuration values from keys without the module prefix to their
 * prefixed replacements (PrestaShop Marketplace guidelines require every key
 * a module creates to carry the module prefix).
 *
 * A value already stored under the new key wins; the old key is removed in
 * every case so it cannot come back on a later upgrade.
 */
class ConfigurationKeyMigrator
{
    /** @var array<string, string> old key => new key */
    private $renames;

    /**
     * @param array<string, string>|null $renames old key => new key; defaults to the module's renamed keys
     */
    public function __construct(?array $renames = null)
    {
        $this->renames = $renames ?? [
            Config::LEGACY_PAYMENT_FEE_MODE => Config::PAYMENT_FEE_MODE,
            Config::LEGACY_PAYMENT_FEE_FRONTEND_LABEL => Config::PAYMENT_FEE_FRONTEND_LABEL,
        ];
    }

    /**
     * @return int number of values copied to a new key
     */
    public function migrate(): int
    {
        $copied = 0;
        foreach ($this->renames as $old => $new) {
            $value = \Configuration::get($old);
            if ($value === false) {
                continue;
            }
            if (\Configuration::get($new) === false) {
                \Configuration::updateValue($new, $value);
                ++$copied;
            }
            \Configuration::deleteByName($old);
        }

        return $copied;
    }
}
