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

namespace Buckaroo\PrestaShop\Src\Service;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * MySQL named lock per cart, held while an order is created for that cart.
 *
 * Every code path that creates an order for a cart (push, customer return, direct
 * payment response, giftcard) takes the same lock, so only one order is created
 * per cart. Callers must check for an existing order after acquiring the lock.
 */
class CartOrderLock
{
    public const DEFAULT_TIMEOUT = 15;

    public function acquire(int $cartId, int $timeout = self::DEFAULT_TIMEOUT): bool
    {
        try {
            return (int) \Db::getInstance()->getValue(
                'SELECT GET_LOCK(\'' . self::name($cartId) . '\', ' . max(0, $timeout) . ')',
                false
            ) === 1;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function release(int $cartId): void
    {
        try {
            \Db::getInstance()->getValue('SELECT RELEASE_LOCK(\'' . self::name($cartId) . '\')', false);
        } catch (\Throwable $e) {
            // The lock is released with the connection anyway.
        }
    }

    public static function name(int $cartId): string
    {
        // Lock names are server wide: include the database so two shops on one server do not share them.
        return 'bk_order_' . md5(_DB_NAME_ . _DB_PREFIX_) . '_' . $cartId;
    }
}
