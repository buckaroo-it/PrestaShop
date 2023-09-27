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

namespace Buckaroo\PrestaShop\Src\Refund\Payment;

class Service
{
    public function create(
        \Order $order,
        string $transactionId,
        string $paymentMethod,
        float $amount
    ) {
        $payment = new OrderPayment();
        $payment->order_reference = $order->reference;
        $payment->id_currency = $order->id_currency;
        $payment->transaction_id = $transactionId;
        $payment->amount = $amount;
        $payment->payment_method = $paymentMethod;
        $payment->save();
    }
}
