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

class KlarnaTransactionKey
{
    /**
     * Klarna Pay (capture) transaction type. Refunds must use this key, not the Reserve data request key.
     */
    public static function isCapturePush($response): bool
    {
        $method = \Tools::strtolower((string) ($response->payment_method ?? ''));
        if (strpos($method, 'klarna') === false) {
            return false;
        }

        if (!empty($response->brq_relatedtransaction_refund)) {
            return false;
        }

        return strtoupper((string) ($response->brq_transaction_type ?? '')) === 'C339'
            && $response->hasSucceeded()
            && trim((string) ($response->transactions ?? '')) !== '';
    }

    public static function storeCaptureKey(\Order $order, string $transactionKey): void
    {
        $transactionKey = trim($transactionKey);
        if ($transactionKey === '' || !\Validate::isLoadedObject($order)) {
            return;
        }

        $payments = \OrderPayment::getByOrderReference($order->reference);
        if (!is_array($payments)) {
            $payments = [];
        }

        foreach ($payments as $payment) {
            if ((float) $payment->amount <= 0) {
                continue;
            }

            if ((string) $payment->transaction_id !== $transactionKey) {
                $payment->transaction_id = $transactionKey;
                $payment->update();
            }
            break;
        }
    }
}
