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

namespace Buckaroo\PrestaShop\Src\Refund;

if (!defined('_PS_VERSION_')) {
    exit;
}

class RefundSplit
{
    /**
     * Split a refund across the captures stored on the order.
     * Each capture is refunded only for what has not already been refunded.
     *
     * @param \OrderPayment[] $payments
     *
     * @return array<int, array{payment: \OrderPayment, amount: float}>
     */
    public static function chunks(array $payments, float $amount): array
    {
        $chunks = [];
        $remaining = round($amount, 2);

        foreach ($payments as $payment) {
            if ($remaining < 0.01) {
                break;
            }
            if ((float) $payment->amount <= 0 || (string) $payment->transaction_id === '') {
                continue;
            }

            $available = round((float) $payment->amount - self::alreadyRefunded((string) $payment->transaction_id), 2);
            if ($available < 0.01) {
                continue;
            }

            $chunk = round(min($remaining, $available), 2);
            if ($chunk < 0.01) {
                continue;
            }

            $chunks[] = [
                'payment' => $payment,
                'amount' => $chunk,
            ];
            $remaining = round($remaining - $chunk, 2);
        }

        return $chunks;
    }

    private static function alreadyRefunded(string $transactionId): float
    {
        $transactionId = trim($transactionId);
        if ($transactionId === '') {
            return 0.0;
        }

        $sum = \Db::getInstance()->getValue(
            'SELECT COALESCE(SUM(`amount`), 0)
             FROM `' . _DB_PREFIX_ . 'bk_refund_request`
             WHERE `payment_key` = \'' . pSQL($transactionId) . '\'
               AND `status` = \'success\''
        );

        return round((float) $sum, 2);
    }
}
