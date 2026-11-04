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

use Buckaroo\BuckarooClient;
use Buckaroo\PrestaShop\Classes\Config;
use Buckaroo\PrestaShop\Src\Refund\OrderMessage;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ReservationService
{
    public const CANCEL_SENT = 'Klarna cancel reservation sent.';

    /**
     * @var OrderMessage
     */
    private $orderMessage;

    public function __construct(OrderMessage $orderMessage)
    {
        $this->orderMessage = $orderMessage;
    }

    /**
     * @return array{key: string, cancelSent: bool}|null
     */
    public function getAdminData(\Order $order): ?array
    {
        $key = $this->openKey($order);
        if ($key === null) {
            return null;
        }

        return [
            'key' => $key,
            'cancelSent' => $this->messageExists($order, self::CANCEL_SENT),
        ];
    }

    public function cancel(\Order $order): string
    {
        if ($this->messageExists($order, self::CANCEL_SENT)) {
            throw new \RuntimeException('Klarna cancel reservation was already sent.');
        }

        $this->send($order, 'cancelReserve');
        $this->orderMessage->add($order, self::CANCEL_SENT);

        return 'Klarna cancel reservation was sent. The order status updates when Buckaroo confirms it.';
    }

    public function extend(\Order $order): string
    {
        $this->send($order, 'extendReserve');
        $this->orderMessage->add($order, 'Klarna extend reservation sent.');

        return 'Klarna extend reservation was sent.';
    }

    private function send(\Order $order, string $action): void
    {
        $key = $this->openKey($order);
        if ($key === null) {
            throw new \RuntimeException('This Klarna reservation is no longer open.');
        }

        $client = new BuckarooClient(
            (string) \Configuration::get('BUCKAROO_MERCHANT_KEY'),
            (string) \Configuration::get('BUCKAROO_SECRET_KEY'),
            Config::getMode('klarna')
        );

        $klarna = $client->method('klarna');
        $response = $action === 'cancelReserve'
            ? $klarna->cancelReserve(['dataRequestKey' => $key])
            : $klarna->extendReserve(['dataRequestKey' => $key]);

        if (!$response->isSuccess()) {
            $error = trim($response->getSomeError());
            throw new \RuntimeException($error !== '' ? $error : 'Klarna reservation request failed.');
        }
    }

    private function openKey(\Order $order): ?string
    {
        if ($this->isCanceled($order)) {
            return null;
        }

        return ReservationKey::resolve(
            $this->messages($order),
            $this->transactionIds($order)
        );
    }

    private function isCanceled(\Order $order): bool
    {
        $state = (int) $order->getCurrentState();
        $canceledStates = [
            (int) \Configuration::get('BUCKAROO_ORDER_STATE_FAILED'),
            (int) \Configuration::get('PS_OS_CANCELED'),
        ];

        return in_array($state, array_filter($canceledStates), true);
    }

    /**
     * @return string[]
     */
    private function messages(\Order $order): array
    {
        $rows = \Message::getMessagesByOrderId((int) $order->id, true);
        if (!is_array($rows)) {
            return [];
        }

        $messages = [];
        foreach ($rows as $row) {
            if (isset($row['message'])) {
                $messages[] = (string) $row['message'];
            }
        }

        // Newest message last, so a later reserve key wins.
        return array_reverse($messages);
    }

    /**
     * @return string[]
     */
    private function transactionIds(\Order $order): array
    {
        $payments = \OrderPayment::getByOrderReference($order->reference);
        if (!is_array($payments)) {
            return [];
        }

        $ids = [];
        foreach ($payments as $payment) {
            $ids[] = (string) $payment->transaction_id;
        }

        return $ids;
    }

    private function messageExists(\Order $order, string $expected): bool
    {
        foreach ($this->messages($order) as $message) {
            if (trim($message) === $expected) {
                return true;
            }
        }

        return false;
    }
}
