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

namespace Buckaroo\Prestashop\Refund;

use Buckaroo\Prestashop\Refund\Request\Builder;
use Buckaroo\Prestashop\Refund\Request\Handler as RefundRequestHandler;
use Buckaroo\Prestashop\Refund\Request\Response\Handler as RefundResponseHandler;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Adapter\Order\Refund\OrderRefundCalculator;
use PrestaShop\PrestaShop\Adapter\Order\Refund\OrderRefundSummary;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\IssuePartialRefundCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\IssueReturnProductCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\IssueStandardRefundCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderException;

class Handler
{
    /**
     * @var OrderRefundCalculator
     */
    private $orderRefundCalculator;

    /**
     * @var RefundRequestHandler
     */
    private $refundHandler;

    /**
     * @var Builder
     */
    private $refundBuilder;

    /**
     * @var RefundResponseHandler
     */
    private $responseHandler;

    public function __construct(
        OrderRefundCalculator $orderRefundCalculator,
        RefundRequestHandler $refundHandler,
        Builder $refundBuilder,
        RefundResponseHandler $responseHandler
    ) {
        $this->orderRefundCalculator = $orderRefundCalculator;
        $this->refundHandler = $refundHandler;
        $this->refundBuilder = $refundBuilder;
        $this->responseHandler = $responseHandler;
    }

    /**
     * Undocumented function
     *
     * @param IssueStandardRefundCommand|IssuePartialRefundCommand $command
     *
     * @return void
     */
    public function execute($command)
    {
        $order = new \Order($command->getOrderId()->getValue());
        $buckarooPayments = $this->getBuckarooPayments($order);
        if (count($buckarooPayments)) {
            $refundSummary = $this->getRefundSummary($order, $command);
            foreach ($buckarooPayments as $payment) {
                $this->refund($order, $payment, $refundSummary);
            }
        }
    }

    private function refund(\Order $order, \OrderPayment $payment, OrderRefundSummary $refundSummary)
    {
        if ($payment->amount < 0) {
            return null;
        }

        if ($refundSummary->getRefundedAmount() - $payment->amount >= 0.01) {
            throw new OrderException('Maximum amount that can be refunded in a single request is ' . $payment->amount);

        }

        $body = $this->refundBuilder->create($order, $payment, $refundSummary);
        $this->responseHandler->parse(
            $this->refundHandler->refund(
                $body,
                $payment->payment_method
            ),
            $body,
            $order->id
        );
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
     * Get refund data
     *
     * @param \Order $order
     * @param IssueStandardRefundCommand|IssuePartialRefundCommand|IssueReturnProductCommand $command
     *
     * @return OrderRefundSummary
     */
    public function getRefundSummary(\Order $order, $command): OrderRefundSummary
    {
        if ($command instanceof IssuePartialRefundCommand) {
            $shippingRefundAmount = $command->getShippingCostRefundAmount();
        } else {
            $shippingRefundAmount = new DecimalNumber((string) ($command->refundShippingCost() ? $order->total_shipping_tax_incl : 0));
        }
        /* @var OrderRefundSummary $orderRefundSummary */
        return $this->orderRefundCalculator->computeOrderRefund(
            $order,
            $command->getOrderDetailRefunds(),
            $shippingRefundAmount,
            $command->getVoucherRefundType(),
            $command->getVoucherRefundAmount()
        );
    }
}
