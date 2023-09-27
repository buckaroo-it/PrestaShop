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

class QuantityBasedBuilder extends AbstractBuilder
{
    public function create(\Order $order, \OrderPayment $payment, float $amount)
    {
        return array_merge(
            $this->buildCommon($order, $payment, $this->round($amount)),
            $this->buildIssuers($payment),
            $this->buildArticles($this->round($amount), $payment->payment_method)
        );
    }

    private function buildArticles(float $amount, string $paymentCode): array
    {
        if (!in_array($paymentCode, ['afterpay', 'billink'])) {
            return [];
        }

        return [
            'articles' => [[
                'refundType' => 'Return',
                'identifier' => 'amount_refund',
                'description' => 'Refund amount of ' . $amount,
                'quantity' => 1,
                'price' => $amount,
                'vatPercentage' => 0,
            ]],
        ];
    }
}
