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

use Buckaroo\PrestaShop\Src\Config\Config;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Keeps the order confirmation PrestaShop builds before payment,
 * and sends that same message once the order is paid.
 */
class DeferredOrderConfirmation
{
    /**
     * Set while the held message is being sent, so this hook does not capture it again.
     * Order history does not exist yet at that moment, so the order still looks unpaid.
     *
     * @var bool
     */
    private static $sending = false;

    /**
     * Cancel this send and keep the message. Returns true when the email may go out.
     *
     * @param array<string, mixed> $params
     * @param \Context|null $context
     */
    public static function hold(array $params, string $moduleName, $context = null): bool
    {
        if (self::$sending || !self::isEnabled() || ($params['template'] ?? '') !== 'order_conf') {
            return true;
        }

        $order = self::findOrder($params, $context);
        if (!$order || $order->module !== $moduleName || $order->hasBeenPaid()) {
            return true;
        }

        // Payment was already recorded before PrestaShop built this message. Send it now.
        $state = new \OrderState((int) $order->current_state);
        if (\Validate::isLoadedObject($state) && (int) $state->paid === 1) {
            return true;
        }

        if (!self::store((int) $order->id, $params)) {
            return true;
        }

        return false;
    }

    /**
     * Send the held confirmation when a Buckaroo order first becomes paid.
     *
     * @param array<string, mixed> $params
     */
    public static function release(array $params, string $moduleName): void
    {
        if (!self::isEnabled()) {
            return;
        }

        $status = $params['newOrderStatus'] ?? null;
        $orderId = (int) ($params['id_order'] ?? 0);
        if ($orderId <= 0 || !is_object($status) || (int) $status->paid !== 1) {
            return;
        }

        $order = new \Order($orderId);
        if (!\Validate::isLoadedObject($order) || $order->module !== $moduleName) {
            return;
        }

        self::send($orderId);
    }

    private static function isEnabled(): bool
    {
        return (int) \Configuration::get(Config::BUCKAROO_DISABLE_UNPAID_ORDER_EMAIL) === 1;
    }

    /**
     * @param array<string, mixed> $params
     * @param \Context|null $context
     */
    private static function findOrder(array $params, $context): ?\Order
    {
        $orderId = (int) ($params['templateVars']['{id_order}'] ?? 0);
        if ($orderId <= 0 && isset($params['cart']->id)) {
            $orderId = (int) \Order::getIdByCartId((int) $params['cart']->id);
        }
        if ($orderId <= 0 && isset($context->cart->id)) {
            $orderId = (int) \Order::getIdByCartId((int) $context->cart->id);
        }
        if ($orderId <= 0) {
            return null;
        }

        $order = new \Order($orderId);

        return \Validate::isLoadedObject($order) ? $order : null;
    }

    /**
     * Db::insert() quotes values without escaping them.
     *
     * @param array<string, mixed> $params
     */
    private static function store(int $orderId, array $params): bool
    {
        $templateVars = $params['templateVars'] ?? null;
        $encoded = is_array($templateVars) ? json_encode($templateVars, JSON_UNESCAPED_UNICODE) : false;
        if ($orderId <= 0 || $encoded === false) {
            return false;
        }

        return (bool) \Db::getInstance()->insert('bk_deferred_order_mail', [
            'id_order' => $orderId,
            'id_lang' => (int) ($params['idLang'] ?? 0),
            'template' => pSQL((string) ($params['template'] ?? 'order_conf')),
            'subject' => pSQL((string) ($params['subject'] ?? ''), true),
            'template_vars' => pSQL($encoded, true),
            'recipient' => pSQL(self::asString($params['to'] ?? '')),
            'recipient_name' => pSQL(self::asString($params['toName'] ?? ''), true),
            'template_path' => pSQL((string) ($params['templatePath'] ?? _PS_MAIL_DIR_)),
            'id_shop' => (int) ($params['idShop'] ?? 0),
        ], false, true, \Db::REPLACE);
    }

    private static function send(int $orderId): bool
    {
        if ($orderId <= 0 || self::$sending) {
            return false;
        }

        $row = \Db::getInstance()->getRow(
            'SELECT `id_lang`, `template`, `subject`, `template_vars`, `recipient`, `recipient_name`, `template_path`, `id_shop`
            FROM `' . _DB_PREFIX_ . 'bk_deferred_order_mail`
            WHERE `id_order` = ' . (int) $orderId
        );
        if (!is_array($row)) {
            return false;
        }

        $templateVars = json_decode((string) $row['template_vars'], true);
        if (!is_array($templateVars)) {
            self::delete($orderId);
            self::logFailure($orderId, 'stored confirmation could not be read');

            return false;
        }

        self::$sending = true;
        try {
            $sent = \Mail::Send(
                (int) $row['id_lang'],
                (string) $row['template'],
                (string) $row['subject'],
                $templateVars,
                (string) $row['recipient'],
                (string) $row['recipient_name'],
                null,
                null,
                null,
                null,
                (string) ($row['template_path'] ?: _PS_MAIL_DIR_),
                false,
                (int) $row['id_shop']
            );
        } finally {
            self::$sending = false;
        }

        if ($sent) {
            self::delete($orderId);
        } else {
            self::logFailure($orderId, 'order confirmation could not be sent');
        }

        return (bool) $sent;
    }

    private static function delete(int $orderId): void
    {
        \Db::getInstance()->delete('bk_deferred_order_mail', 'id_order = ' . (int) $orderId);
    }

    private static function logFailure(int $orderId, string $message): void
    {
        \PrestaShopLogger::addLog(
            'Buckaroo: ' . $message . ' for order ' . (int) $orderId,
            3,
            null,
            'Order',
            (int) $orderId,
            true
        );
    }

    /**
     * Mail recipients may be one address or a list. The order confirmation uses the first one.
     *
     * @param string|array $value
     */
    private static function asString($value): string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        return (string) $value;
    }
}
