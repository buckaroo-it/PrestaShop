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

namespace Buckaroo\PrestaShop\Src\Repository;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Payment attempts of methods that create the order only after a successful payment.
 *
 * Each attempt has its own reference (BK<id>), sent to Buckaroo as "<reference>_<cart id>",
 * so a push or return can be matched to the cart before an order exists.
 */
class RawPaymentAttemptRepository
{
    public const TABLE = 'bk_payment_attempt';

    public const STATUS_STARTED = 'started';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';

    private const REFERENCE_PREFIX = 'BK';

    public static function createTableSql(): string
    {
        return 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE . '` (
            `id_bk_payment_attempt` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `reference`             VARCHAR(32) DEFAULT NULL,
            `id_cart`               INT(11) UNSIGNED NOT NULL,
            `id_shop`               INT(11) UNSIGNED NOT NULL DEFAULT 0,
            `payment_method`        VARCHAR(64) NOT NULL,
            `payment_label`         VARCHAR(255) NOT NULL DEFAULT \'\',
            `amount`                DECIMAL(20,6) NOT NULL DEFAULT 0,
            `currency`              VARCHAR(3) NOT NULL DEFAULT \'\',
            `status`                VARCHAR(16) NOT NULL,
            `transaction_key`       VARCHAR(64) NOT NULL DEFAULT \'\',
            `id_order`              INT(11) UNSIGNED NOT NULL DEFAULT 0,
            `amount_mismatch`       TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            `created_at`            DATETIME NOT NULL,
            `updated_at`            DATETIME NOT NULL,
            PRIMARY KEY (`id_bk_payment_attempt`),
            UNIQUE KEY `reference` (`reference`),
            KEY `id_cart` (`id_cart`)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = ' . _MYSQL_ENGINE_;
    }

    /**
     * Reference of an attempt id: what the checkout puts before "_<cart id>" in the invoice.
     */
    public static function referenceFor(int $attemptId): string
    {
        return self::REFERENCE_PREFIX . $attemptId;
    }

    /**
     * Whether an invoice reference belongs to an attempt. PrestaShop order
     * references are letters only, so "BK" followed by digits never collides.
     */
    public static function isAttemptReference(string $reference): bool
    {
        return (bool) preg_match('/^' . self::REFERENCE_PREFIX . '[1-9][0-9]*$/', $reference);
    }

    /**
     * Start an attempt and return it with its reference, or null when it could not be stored.
     *
     * @return array<string, mixed>|null
     */
    public function start(int $cartId, int $shopId, string $method, string $label, string $currency): ?array
    {
        $now = date('Y-m-d H:i:s');
        $db = \Db::getInstance();

        try {
            $inserted = $db->insert(self::TABLE, [
                'id_cart' => $cartId,
                'id_shop' => $shopId,
                'payment_method' => pSQL($method),
                'payment_label' => pSQL($label),
                'currency' => pSQL($currency),
                'status' => self::STATUS_STARTED,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if (!$inserted) {
                return null;
            }

            $id = (int) $db->Insert_ID();
            if ($id <= 0 || !$db->update(self::TABLE, ['reference' => pSQL(self::referenceFor($id))], 'id_bk_payment_attempt = ' . $id)) {
                return null;
            }
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog('Buckaroo: payment attempt could not be stored: ' . $e->getMessage(), 3);

            return null;
        }

        return $this->findById($id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        return $this->findOne('id_bk_payment_attempt = ' . $id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByReference(string $reference): ?array
    {
        if (!self::isAttemptReference($reference)) {
            return null;
        }

        return $this->findOne('reference = \'' . pSQL($reference) . '\'');
    }

    /**
     * The attempt a Buckaroo invoice ("BK<id>_<cart id>") refers to, when both parts match.
     *
     * @return array<string, mixed>|null
     */
    public function findByInvoice(string $reference, int $cartId): ?array
    {
        $attempt = $this->findByReference($reference);
        if ($attempt === null || $cartId <= 0 || (int) $attempt['id_cart'] !== $cartId) {
            return null;
        }

        return $attempt;
    }

    /**
     * Whether the order of an attempt must stay unpaid because the paid amount did not match.
     *
     * @param array<string, mixed>|null $attempt
     */
    public static function hasAmountMismatch(?array $attempt): bool
    {
        return $attempt !== null && (int) ($attempt['amount_mismatch'] ?? 0) === 1;
    }

    /**
     * The most recent attempt of a cart.
     *
     * @return array<string, mixed>|null
     */
    public function findLatestByCart(int $cartId): ?array
    {
        if ($cartId <= 0) {
            return null;
        }

        return $this->findOne('id_cart = ' . $cartId . ' ORDER BY id_bk_payment_attempt DESC');
    }

    public function setAmount(int $id, float $amount): bool
    {
        return $this->updateFields($id, ['amount' => (float) $amount]);
    }

    public function markPending(int $id, string $transactionKey = ''): bool
    {
        // A paid attempt never goes back; a late pending push must not undo it.
        return $this->updateStatus($id, self::STATUS_PENDING, $transactionKey, [self::STATUS_STARTED, self::STATUS_PENDING]);
    }

    public function markFailed(int $id, string $transactionKey = ''): bool
    {
        return $this->updateStatus($id, self::STATUS_FAILED, $transactionKey, [self::STATUS_STARTED, self::STATUS_PENDING]);
    }

    /**
     * @param bool $amountMismatch the paid amount did not match the order total; the
     *                             order stays on "Payment error" and is never marked paid
     */
    public function markPaid(int $id, int $orderId, string $transactionKey = '', bool $amountMismatch = false): bool
    {
        $fields = ['status' => self::STATUS_PAID, 'id_order' => $orderId];
        if ($amountMismatch) {
            $fields['amount_mismatch'] = 1;
        }
        if ($transactionKey !== '') {
            $fields['transaction_key'] = pSQL($transactionKey);
        }

        return $this->updateFields($id, $fields);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function updateFields(int $id, array $fields): bool
    {
        if ($id <= 0) {
            return false;
        }

        $fields['updated_at'] = date('Y-m-d H:i:s');

        try {
            return (bool) \Db::getInstance()->update(self::TABLE, $fields, 'id_bk_payment_attempt = ' . $id);
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog('Buckaroo: payment attempt ' . $id . ' could not be updated: ' . $e->getMessage(), 3);

            return false;
        }
    }

    /**
     * @param string[] $fromStatuses
     */
    private function updateStatus(int $id, string $status, string $transactionKey, array $fromStatuses): bool
    {
        $attempt = $this->findById($id);
        if ($attempt === null || !in_array((string) $attempt['status'], $fromStatuses, true)) {
            return false;
        }

        $fields = ['status' => $status];
        if ($transactionKey !== '') {
            $fields['transaction_key'] = pSQL($transactionKey);
        }

        return $this->updateFields($id, $fields);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findOne(string $where): ?array
    {
        try {
            $row = \Db::getInstance()->getRow(
                'SELECT * FROM `' . _DB_PREFIX_ . self::TABLE . '` WHERE ' . $where
            );
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog('Buckaroo: payment attempt could not be read: ' . $e->getMessage(), 3);

            return null;
        }

        return is_array($row) ? $row : null;
    }
}
