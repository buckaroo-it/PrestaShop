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
 * Manages gift card group transactions for partial payments.
 *
 * Mirrors the behaviour of Magento 2's PaymentGroupTransaction helper:
 * each gift card deduction is stored as a row in bk_group_transaction so
 * that the checkout summary can display "Paid with X Giftcard" and
 * "Remaining Amount" lines that survive page reloads and are confirmed
 * by server-side Buckaroo push notifications.
 *
 * Status codes follow the Buckaroo convention: 190 = Success.
 */
class BuckarooGroupTransactionService
{
    private const TABLE = 'bk_group_transaction';
    private const STATUS_SUCCESS = 190;

    /**
     * Persist a new gift card partial payment row.
     *
     * Expected keys in $data:
     *   transaction_key      (string) Buckaroo transaction key
     *   group_transaction_id (string) Buckaroo group transaction reference (may be empty)
     *   amount               (float)  Amount deducted by this gift card
     *   currency             (string) ISO currency code
     *   card_code            (string) Gift card service code / name
     *   status               (int)    Buckaroo status code (190 = success)
     */
    public function saveGroupTransaction(int $cartId, array $data): void
    {
        \Db::getInstance()->insert(
            self::TABLE,
            [
                'cart_id'               => (int) $cartId,
                'order_id'              => null,
                'transaction_key'       => pSQL((string) ($data['transaction_key'] ?? '')),
                'group_transaction_id'  => pSQL((string) ($data['group_transaction_id'] ?? '')),
                'amount'                => (float) ($data['amount'] ?? 0),
                'refunded_amount'       => 0,
                'currency'              => pSQL((string) ($data['currency'] ?? '')),
                'card_code'             => pSQL((string) ($data['card_code'] ?? '')),
                'status'                => (int) ($data['status'] ?? self::STATUS_SUCCESS),
            ]
        );
    }

    /**
     * Update the status of an existing row identified by its Buckaroo transaction key.
     * Called from the push handler when Buckaroo confirms (or fails) a gift card auth.
     */
    public function updateGroupTransactionStatus(string $transactionKey, int $status): void
    {
        \Db::getInstance()->update(
            self::TABLE,
            ['status' => (int) $status],
            '`transaction_key` = \'' . pSQL($transactionKey) . '\''
        );
    }

    /**
     * Attach a PrestaShop order ID to all group-transaction rows for the given cart.
     * Called after validateOrder() so rows can be associated with the final order.
     */
    public function linkOrderToCart(int $cartId, int $orderId): void
    {
        \Db::getInstance()->update(
            self::TABLE,
            ['order_id' => (int) $orderId],
            '`cart_id` = ' . (int) $cartId
        );
    }

    /**
     * Return all successful (non-fully-refunded) rows for a cart.
     * Each row contains: transaction_key, amount, refunded_amount, currency, card_code.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getGroupTransactionItems(int $cartId): array
    {
        if ($cartId <= 0) {
            return [];
        }

        $rows = \Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . pSQL(self::TABLE) . '`
             WHERE `cart_id` = ' . (int) $cartId . '
               AND `status` = ' . self::STATUS_SUCCESS . '
             ORDER BY `created_at` ASC'
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Sum the net applied amounts (amount minus refunded_amount) across all successful rows.
     */
    public function getAlreadyPaid(int $cartId): float
    {
        $total = 0.0;
        foreach ($this->getGroupTransactionItems($cartId) as $row) {
            $total += (float) $row['amount'] - (float) $row['refunded_amount'];
        }
        return round($total, 2);
    }

    /**
     * Calculate the amount still owed after gift card deductions.
     * Returns 0 if the gift card(s) covered the full cart.
     */
    public function getRemainingAmount(int $cartId, float $cartTotal): float
    {
        return (float) max(0, round($cartTotal - $this->getAlreadyPaid($cartId), 2));
    }

    /**
     * Build the display-ready rows used by already-paid.tpl and the Ajax endpoint.
     * Each entry has: label, amount (float, positive), card_code, transaction_key.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDisplayItems(int $cartId): array
    {
        $items = [];
        foreach ($this->getGroupTransactionItems($cartId) as $row) {
            $net = (float) $row['amount'] - (float) $row['refunded_amount'];
            if ($net <= 0) {
                continue;
            }
            $cardCode = (string) $row['card_code'];
            $label = $this->buildLabel($cardCode);
            $items[] = [
                'label'           => $label,
                'amount'          => round($net, 2),
                'card_code'       => $cardCode,
                'transaction_key' => (string) $row['transaction_key'],
            ];
        }
        return $items;
    }

    /**
     * Build a human-readable label for a gift card service code.
     */
    private function buildLabel(string $cardCode): string
    {
        if (empty($cardCode)) {
            return 'Giftcard';
        }

        $row = \Db::getInstance()->getRow(
            'SELECT `name` FROM `' . _DB_PREFIX_ . 'bk_giftcards`
             WHERE `code` = \'' . pSQL($cardCode) . '\''
        );

        if ($row && !empty($row['name'])) {
            return 'Paid with ' . $row['name'];
        }

        return 'Paid with ' . ucfirst($cardCode) . ' Giftcard';
    }
}
