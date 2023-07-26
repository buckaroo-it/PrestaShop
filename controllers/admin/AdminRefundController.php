<?php

namespace Buckaroo3\Prestashop\Controller;

use Order;
use Context;
use Currency;
use OrderPayment;
use Tools;
use Buckaroo\Prestashop\Refund\OrderService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Buckaroo\Prestashop\Refund\Request\QuantityBasedBuilder;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Buckaroo\Prestashop\Refund\Request\Handler as RefundRequestHandler;
use Buckaroo\Prestashop\Refund\Request\Response\Handler as RefundResponseHandler;

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

class AdminRefundController extends FrameworkBundleAdminController
{

    /**
     * @var QuantityBasedBuilder
     */
    private $refundBuilder;


    /**
     * @var RefundRequestHandler
     */
    private $refundHandler;

    /**
     * @var RefundResponseHandler
     */
    private $responseHandler;

    /**
     * @var OrderService
     */
    private $orderService;

    public function __construct(
        QuantityBasedBuilder $refundBuilder,
        RefundRequestHandler $refundHandler,
        RefundResponseHandler $responseHandler,
        OrderService $orderService
    ) {
        $this->refundHandler = $refundHandler;
        $this->refundBuilder = $refundBuilder;
        $this->responseHandler = $responseHandler;
        $this->orderService = $orderService;
    }

    public function refund(Request $request)
    {
        $orderId = $request->get('orderId');
        $refundAmount = $request->get('refundAmount');

        if (!is_scalar($orderId) || $orderId === null) {
            return $this->renderError("Invalid value for `orderId`");
        }

        if (!is_scalar($refundAmount) || $refundAmount === null) {
            return $this->renderError("Invalid value for `refundAmount`");
        }

        $order = new Order($orderId);

        try {
            $totalRefundAmount = $this->sendRefundRequests($order, (float)$refundAmount);
            return new JsonResponse(
                ["error" =>false, "message" => "Successfully refunded amount of ". $this->formatPrice($order, $totalRefundAmount)]
            );
        } catch (\Throwable $th) {
            return $this->renderError($th->getMessage());
        }
    }
    
    /**
     * Send refund request to payment engine, return total amount refunded
     *
     * @param Order $order
     * @param float $maxRefundAmount
     *
     * @return float
     */
    private function sendRefundRequests(Order $order, float $maxRefundAmount): float
    {
        $refundAmount = $maxRefundAmount;
        $buckarooPayments = $this->getBuckarooPayments($order);
        if (count($buckarooPayments)) {
            foreach ($buckarooPayments as $payment) {
                if ($payment->amount > 0) {
                    $refundAmount = $this->sentRefundRequest($order, $payment, $refundAmount);
                }
            }
        }
        return $maxRefundAmount - $refundAmount;
    }

    /**
     * Refund individual payment with amount, return remaining amount to be refunded
     *
     * @param Order $order
     * @param OrderPayment $payment
     * @param float $maxRefundAmount
     *
     * @return float
     */
    private function sentRefundRequest(Order $order, OrderPayment $payment, float $maxRefundAmount): float
    {
        $refundAmount = $maxRefundAmount;
        if ($maxRefundAmount > $payment->amount) {
            $refundAmount = $payment->amount;
        }
        $maxRefundAmount = $maxRefundAmount - $refundAmount;
 
        try {
            $this->orderService->refund($order, $refundAmount);
        } catch (\Throwable $th) { // phpcs:ignore
            //throw $th;
        }

        $body = $this->refundBuilder->create($order, $payment, $refundAmount);
        $this->responseHandler->parse(
            $this->refundHandler->refund(
                $body,
                $payment->payment_method
            ),
            $body,
            $order->id
        );

       
    
        return $maxRefundAmount;
    }

    /**
     * Get buckaroo payments
     *
     * @param Order $order
     *
     * @return array
     */
    private function getBuckarooPayments(Order $order): array
    {
        //todo: filter payments for only buckaroo requests 
        return  $order->getOrderPayments();
    }

    /**
     * Render any errors generated
     *
     * @param string $message
     *
     * @return JsonResponse
     */
    private function renderError(string $message): JsonResponse
    {
        return new JsonResponse(['error' => true, "message" => $message]);
    }

    /**
     * Format price based on order currency
     *
     * @param Order $order
     * @param float $price
     *
     * @return string
     */
    private function formatPrice(Order $order, float $price): string
    {
         return Tools::getContextLocale(Context::getContext())->formatPrice(
            $price, Currency::getIsoCodeById((int)$order->id_currency)
        );
    }
}
