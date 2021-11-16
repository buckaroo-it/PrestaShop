<?php
/**
*
*
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

include_once(_PS_MODULE_DIR_ . 'buckaroo3/library/checkout/checkout.php');
include_once(_PS_MODULE_DIR_ . 'buckaroo3/library/logger.php');
include_once(_PS_MODULE_DIR_ . 'buckaroo3/controllers/front/common.php');

class AdminRefundController extends AdminControllerCore
{
    public function __construct()
    {
        parent::__construct();
        if (Tools::getValue('action') && Tools::getValue('action') == 'refund') {
            $this->refundAction();
        }
    }

    public function display()
    {
        return false;
    }

    public function refundAction()
    {
        $cookie = new Cookie('ps');
        $order = new Order(Tools::getValue("id_order"));
        $transactions = $order->getOrderPayments();
        foreach ($transactions as $transaction) {
            /* @var $transaction OrderPaymentCore */
            if ($transaction->transaction_id == Tools::getValue("transaction_id")) {
                //refund this transaction
                autoload('refunds');
                $transaction_amount =
                    Tools::getValue('refund_amount') ? Tools::getValue('refund_amount') : $transaction->amount;
                $Refunds = new Refunds($transaction->payment_method);
                $currency = new Currency((int)$transaction->id_currency);
                $Refunds->amountDedit = 0;
                $Refunds->amountCredit = $transaction_amount;
                $Refunds->currency = $currency->iso_code;
                $Refunds->description = '';
                $Refunds->invoiceId = $transaction->order_reference . '_' . $order->id_cart;
                $Refunds->orderId = $transaction->order_reference . '_' . $order->id_cart;
                $Refunds->OriginalTransactionKey = $transaction->transaction_id;
                $Refunds->returnUrl = '';
                $response = $Refunds->refund();
                if ($response && $response->isValid() && $response->hasSucceeded()) {
                    $cookie->refundStatus = 1;
                    $cookie->refundMessage = sprintf(
                        'Refunded %s - Refund transaction ID: %s',
                        $transaction_amount . ' ' . $currency->iso_code,
                        $response->transactions
                    );
                } else {
                    if (!empty($response->ChannelError)) {
                        $cookie->refundStatus = 0;
                        $cookie->refundMessage = sprintf(
                            'Refund failed for transaction ID: %s ' . $response->ChannelError,
                            $transaction->transaction_id
                        );
                    } else {
                        $cookie->refundStatus = 0;
                        $cookie->refundMessage = sprintf(
                            'Refund failed for transaction ID: %s. See error in Buckaroo Payment Plaza',
                            $transaction->transaction_id
                        );
                    }
                }
            }
        }
        Tools::redirectAdmin(
            $this->context->link->getAdminLink('AdminOrders').'&vieworder&id_order='.(int)$order->id."&token=" . Tools::getValue(//phpcs:ignore
                "admtoken"
            )
        );
        exit();
    }
}
