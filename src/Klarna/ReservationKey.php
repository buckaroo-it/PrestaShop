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

namespace Buckaroo\PrestaShop\Src\Klarna;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ReservationKey
{
    public const PREFIX = 'Data Request Key: ';

    public static function message(string $key): string
    {
        return self::PREFIX . trim($key);
    }

    /**
     * The reserve key stays on the payment until a capture replaces it.
     * After that replacement the reservation is no longer open.
     *
     * @param string[] $messages
     * @param string[] $transactionIds
     */
    public static function resolve(array $messages, array $transactionIds): ?string
    {
        $storedKey = '';
        foreach ($messages as $message) {
            $message = trim((string) $message);
            if (strpos($message, self::PREFIX) !== 0) {
                continue;
            }

            $storedKey = trim(substr($message, strlen(self::PREFIX)));
        }

        if ($storedKey === '') {
            return null;
        }

        foreach ($transactionIds as $transactionId) {
            if (trim((string) $transactionId) === $storedKey) {
                return $storedKey;
            }
        }

        return null;
    }
}
