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
namespace Buckaroo\Src\Refund\Request;

use Buckaroo\Resources\Constants\IPProtocolVersion;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractBuilder
{
    protected function buildCommon(\Order $order, \OrderPayment $payment, float $refundAmount): array
    {
        return [
            'order' => $payment->order_reference . '_' . $order->id_cart,
            'invoice' => $payment->order_reference . '_' . $order->id_cart,
            'amountCredit' => $refundAmount,
            'currency' => \Currency::getIsoCodeById($order->id_currency),
            'pushURL' => $this->getPushUrl(),
            'pushURLFailure' => $this->getPushUrl(),
            'clientIP' => $this->getIp(),
            'originalTransactionKey' => (string) $payment->transaction_id,
            'additionalParameters' => [
                'orderId' => $order->id,
            ],
        ];
    }

    /**
     * Get ip
     *
     * @return array
     */
    private function getIp(): array
    {
        $remoteIp = Request::createFromGlobals()->getClientIp();

        return [
            'address' => $remoteIp,
            'type' => IPProtocolVersion::getVersion($remoteIp),
        ];
    }

    /**
     * Get push url
     *
     * @return string
     */
    private function getPushUrl(): string
    {
        return 'http' . ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 's' : '') . '://' . $_SERVER['SERVER_NAME'] . __PS_BASE_URI__ . 'index.php?fc=module&module=buckaroo3&controller=userreturn';
    }

    /**
     * Build body for credit & debit cards
     *
     * @param \OrderPayment $payment
     *
     * @return array
     */
    protected function buildIssuers(\OrderPayment $payment): array
    {
        if (in_array($payment->payment_method, [
            'creditcard', 'mastercard', 'visa',
            'amex', 'vpay', 'maestro',
            'visaelectron', 'cartebleuevisa',
            'cartebancaire', 'dankort', 'nexi',
            'postepay',
        ])) {
            return [
                'name' => $payment->payment_method,
                'version' => 2,
            ];
        }

        return [];
    }

    /**
     * Round amount to 2 decimals for payment engine
     *
     * @param float $amount
     *
     * @return float
     */
    protected function round(float $amount): float
    {
        return round($amount, 2);
    }
}
