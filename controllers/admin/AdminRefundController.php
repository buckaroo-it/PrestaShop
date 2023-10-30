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

namespace Buckaroo\PrestaShop\Controllers\admin;

use Buckaroo\PrestaShop\Src\Refund\OrderService;
use Buckaroo\PrestaShop\Src\Refund\Request\Handler as RefundRequestHandler;
use Buckaroo\PrestaShop\Src\Refund\Request\QuantityBasedBuilder;
use Buckaroo\PrestaShop\Src\Refund\Request\Response\Handler as RefundResponseHandler;
use PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

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

        if (!is_scalar($orderId)) {
            return $this->renderError('Invalid value for `orderId`');
        }

        if (!is_scalar($refundAmount)) {
            return $this->renderError('Invalid value for `refundAmount`');
        }

        $order = new \Order($orderId);

        try {
            $totalRefundAmount = $this->sendRefundRequests($order, (float) $refundAmount);

            $message = 'Successfully refunded amount of ' . $this->formatPrice($order, $totalRefundAmount);
            $this->addFlash('success', $message);

            return new JsonResponse(
                ['error' => false, 'message' => $message]
            );
        } catch (\Throwable $th) {
            return $this->renderError($th->getMessage());
        }
    }

    /**
     * Send refund request to payment engine, return total amount refunded
     *
     * @param \Order $order
     * @param float  $maxRefundAmount
     *
     * @return float
     */
    private function sendRefundRequests(\Order $order, float $maxRefundAmount): float
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
     * @param \Order        $order
     * @param \OrderPayment $payment
     * @param float         $maxRefundAmount
     *
     * @return float
     */
    private function sentRefundRequest(\Order $order, \OrderPayment $payment, float $maxRefundAmount): float
    {
        $refundAmount = $maxRefundAmount;
        if ($maxRefundAmount > $payment->amount) {
            $refundAmount = $payment->amount;
        }
        $maxRefundAmount -= $refundAmount;

        try {
            $this->orderService->refund($order, $refundAmount);
        } catch (\Throwable $th) { // phpcs:ignore
            // throw $th;
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
     * @param \Order $order
     *
     * @return array
     */
    private function getBuckarooPayments(\Order $order): array
    {
        // todo: filter payments for only buckaroo requests
        return $order->getOrderPayments();
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
        $this->addFlash('error', $message);

        return new JsonResponse(['error' => true, 'message' => $message]);
    }

    /**
     * Format price based on order currency
     *
     * @param \Order $order
     * @param float  $price
     *
     * @return string
     *
     * @throws LocalizationException
     * @throws \Exception
     */
    private function formatPrice(\Order $order, float $price): string
    {
        return \Tools::getContextLocale(\Context::getContext())->formatPrice(
            $price, \Currency::getIsoCodeById((int) $order->id_currency)
        );
    }
}
