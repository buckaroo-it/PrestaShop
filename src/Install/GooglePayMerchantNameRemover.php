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

use Buckaroo\PrestaShop\Src\GooglePay\ExpressSettings;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Remove the Google Pay "Merchant name" value stored by earlier versions (BTI-2009).
 *
 * The setting lived in the Google Pay method configuration (a JSON value in
 * bk_configuration). The Google Pay sheet now shows the shop name instead.
 */
class GooglePayMerchantNameRemover
{
    /** @var \Db */
    private $db;

    /**
     * @param \Db|null $db defaults to the shop database
     */
    public function __construct($db = null)
    {
        $this->db = $db ?? \Db::getInstance();
    }

    /**
     * @return int number of configuration rows the value was removed from
     */
    public function remove(): int
    {
        $rows = $this->db->executeS(
            'SELECT c.`id`, c.`value` FROM `' . _DB_PREFIX_ . 'bk_configuration` c
            INNER JOIN `' . _DB_PREFIX_ . 'bk_payment_methods` m ON m.`id` = c.`configurable_id`
            WHERE m.`name` = \'' . pSQL(ExpressSettings::METHOD_NAME) . '\''
        );
        if (!is_array($rows)) {
            return 0;
        }

        $removed = 0;
        foreach ($rows as $row) {
            $config = json_decode((string) $row['value'], true);
            if (!is_array($config) || !array_key_exists(ExpressSettings::REMOVED_KEY_MERCHANT_NAME, $config)) {
                continue;
            }

            unset($config[ExpressSettings::REMOVED_KEY_MERCHANT_NAME]);
            $updated = $this->db->update(
                'bk_configuration',
                ['value' => pSQL((string) json_encode($config), true)],
                '`id` = ' . (int) $row['id']
            );
            if ($updated) {
                ++$removed;
            }
        }

        return $removed;
    }
}
