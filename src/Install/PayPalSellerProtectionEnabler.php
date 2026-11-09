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
 * Turn PayPal seller protection on for an existing installation. Seller
 * protection sends the customer name and address to PayPal and is the
 * default from 5.4.0 on, for checkout and express payments alike.
 */
class PayPalSellerProtectionEnabler
{
    /**
     * @return bool whether the PayPal configuration was changed
     */
    public function enable(): bool
    {
        $db = \Db::getInstance();
        $row = $db->getRow(
            'SELECT c.`id`, c.`value` FROM `' . _DB_PREFIX_ . 'bk_configuration` c'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'bk_payment_methods` m ON m.`id` = c.`configurable_id`'
            . ' WHERE m.`name` = \'paypal\''
        );

        if (!is_array($row) || empty($row['id'])) {
            return false;
        }

        $config = json_decode((string) $row['value'], true);
        if (!is_array($config)) {
            return false;
        }

        if (($config['seller_protection'] ?? null) === '1') {
            return false;
        }

        $config['seller_protection'] = '1';

        return $db->update(
            'bk_configuration',
            ['value' => pSQL((string) json_encode($config))],
            '`id` = ' . (int) $row['id']
        );
    }
}
