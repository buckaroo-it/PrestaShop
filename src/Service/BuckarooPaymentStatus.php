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
 * Keeps "accepted / still pending" apart from "paid".
 *
 * Only 190 means the money was collected. 790-793 mean the gateway accepted
 * the request but the payment is not final yet.
 */
class BuckarooPaymentStatus
{
    public const SUCCESS = 190;
    public const PENDING = [790, 791, 792, 793];

    public const GIFTCARD_PAID = 'paid';
    public const GIFTCARD_PARTIAL = 'partial';
    public const GIFTCARD_PENDING = 'pending';
    public const GIFTCARD_FAILED = 'failed';

    private const GIFTCARD_INVOICE_PREFIX = 'gc';

    public static function isPaid($statusCode): bool
    {
        return self::normalize($statusCode) === self::SUCCESS;
    }

    public static function isPending($statusCode): bool
    {
        return in_array(self::normalize($statusCode), self::PENDING, true);
    }

    /**
     * Classify a direct giftcard Pay response.
     *
     * A partial card is reported as pending with a PayRemainder: the card was
     * debited and the gateway waits for the remainder. Pending without a usable
     * remainder is not paid and must not complete the order.
     */
    public static function giftcardOutcome($statusCode, float $remainder, float $charged, string $groupTransaction): string
    {
        if (self::isPaid($statusCode)) {
            return $remainder > 0 ? self::GIFTCARD_PARTIAL : self::GIFTCARD_PAID;
        }

        if (!self::isPending($statusCode)) {
            return self::GIFTCARD_FAILED;
        }

        if ($remainder > 0 && $remainder < $charged && $groupTransaction !== '') {
            return self::GIFTCARD_PARTIAL;
        }

        return self::GIFTCARD_PENDING;
    }

    /**
     * The push parser reads the cart id from the last invoice segment.
     */
    public static function giftcardInvoice(int $cartId, int $time): string
    {
        return self::GIFTCARD_INVOICE_PREFIX . '_' . $time . '_' . $cartId;
    }

    public static function isGiftcardReference(string $reference): bool
    {
        return strpos($reference, self::GIFTCARD_INVOICE_PREFIX . '_') === 0;
    }

    /**
     * @return array{0:int,1:string} cart id and order reference
     */
    public static function parseInvoice(string $invoice): array
    {
        $parts = $invoice !== '' ? explode('_', $invoice) : [];

        if (count($parts) < 2) {
            return [0, $invoice];
        }

        return [(int) end($parts), implode('_', array_slice($parts, 0, -1))];
    }

    private static function normalize($statusCode): int
    {
        return is_numeric($statusCode) ? (int) $statusCode : 0;
    }
}
