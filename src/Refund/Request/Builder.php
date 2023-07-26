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

namespace Buckaroo\Prestashop\Refund\Request;

use Tax;
use Order;
use OrderDetail;
use OrderPayment;
use Buckaroo\Prestashop\Refund\Request\AbstractBuilder;
use PrestaShop\PrestaShop\Adapter\Order\Refund\OrderRefundSummary;

class Builder extends AbstractBuilder
{
    public function create(Order $order, OrderPayment $payment, OrderRefundSummary $refundSummary)
    {
        return array_merge(
            $this->buildCommon($order, $payment, $this->round($refundSummary->getRefundedAmount())),
            $this->buildIssuers($payment),
            $this->buildArticles($refundSummary)
        );
    }

    private function buildArticles(OrderRefundSummary $refundSummary)
    {
        $articles = [];
        $total = 0;

        // if voucher do a amount refund
        if ($refundSummary->isVoucherChosen() && $refundSummary->getVoucherAmount() > 0) {
            $amount = $this->round($refundSummary->getVoucherAmount());
            $total += $amount;
            $articles[] = [
                'identifier'        => 'amount_refund',
                'description'       => 'Refund amount of ' . $amount,
                'quantity'          => 1,
                'price'             => $amount,
                'vatPercentage'     => 0,
            ];
        } else {

            // create body for each product
            foreach ($refundSummary->getProductRefunds() as  $orderDetailId => $productRefund) {

                if (!isset($productRefund['amount']) || !isset($productRefund['quantity'])) {
                    continue;
                }

                $amount = $this->round((float)$productRefund['amount']);
                if($amount <= 0) {
                    continue;
                }

                $orderDetail = $refundSummary->getOrderDetailById($orderDetailId);
                $total += $amount;

                $articles[] = [
                    'identifier'        => $orderDetail->product_id,
                    'description'       => $orderDetail->product_name,
                    'quantity'          => $productRefund['quantity'],
                    'price'             => $amount,
                    'vatPercentage'     => $this->getVatPercentage($orderDetail),
                ];
            }

            //if we have other type of voucher we deduct
            if ($refundSummary->getVoucherAmount() > 0) {
                $amount = $this->round($refundSummary->getVoucherAmount());
                $total -= $amount;
                $articles[] = [
                    'identifier'        => 'amount_discount',
                    'description'       => 'Discount amount of ' . $amount,
                    'quantity'          => 1,
                    'price'             => (-1) * $amount,
                    'vatPercentage'     => 0,
                ];
            }
        }
        //create body for shipping if exists
        if ($refundSummary->getRefundedShipping() > 0) {
            $amount = $this->round($refundSummary->getRefundedShipping());
            $total += $amount;

            $articles[] = [
                'identifier'        => 'shipping',
                'description'       => 'Shipping',
                'quantity'          => 1,
                'price'             => $amount,
                'vatPercentage'     => 0,
            ];
        }

        // Checking for rounding errors
        $errors = $this->round($refundSummary->getRefundedAmount()) - $total;
        if (abs($errors) >= 0.01) {
            $articles[] = [
                'identifier'        => 'rounding_errors',
                'description'       => 'Rounding errors',
                'quantity'          => 1,
                'price'             => $errors,
                'vatPercentage'     => 0,
            ];
        }

        return ["articles" => $articles];
    }

    /**
     * Get first tax percentage
     *
     * @param OrderDetail $orderDetail
     *
     * @return float
     */
    private function getVatPercentage(OrderDetail $orderDetail): float
    {
        $taxList = $orderDetail->getTaxList();
        if (!is_array($taxList)) {
            return 0;
        }

        foreach ($orderDetail->getTaxList() as $tax) {
            if (!isset($tax['id_tax'])) {
                continue;
            }
            return (new Tax($tax['id_tax']))->rate;
        }

        return 0;
    }
}
