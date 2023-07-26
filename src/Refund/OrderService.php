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

namespace Buckaroo\Prestashop\Refund;

use Group;
use Order;
use Customer;
use OrderSlip;
use OrderDetail;
use Configuration;
use PrestaShop\Decimal\DecimalNumber;
use Buckaroo\Prestashop\Refund\Settings;
use PrestaShop\PrestaShop\Core\CommandBus\CommandBusInterface;
use PrestaShop\PrestaShop\Core\Domain\Order\VoucherRefundType;
use Buckaroo\Prestashop\Refund\Commands\IssuePartialRefund as IssuePartialRefundCommand;

class OrderService
{

    /**
     * @var CommandBusInterface
     */
    private $commandBus;

    /**
     * @param CommandBusInterface $commandBus
     */
    public function __construct(
        CommandBusInterface $commandBus
    )
    {
        $this->commandBus = $commandBus;
    }

    public function refund(Order $order, float $amount)
    {
        $refundData = $this->determineRefundData($order, $amount);

        $createCreditSlipValue = Configuration::get(Settings::LABEL_REFUND_CREDIT_SLIP, null, null, null, true);
        if (!is_scalar($createCreditSlipValue)) {
            $createCreditSlipValue = true;
        }

        $command = new IssuePartialRefundCommand(
            $order->id,
            $refundData['products'],
            $refundData['shipping_amount'],
            Configuration::get(Settings::LABEL_REFUND_RESTOCK) == true,
            (bool)$createCreditSlipValue,
            Configuration::get(Settings::LABEL_REFUND_VOUCHER) == true,
            VoucherRefundType::PRODUCT_PRICES_EXCLUDING_VOUCHER_REFUND
        );

        $this->commandBus->handle($command);
    }

    /**
     * Determine refund data, products and shipping amounts
     *
     * @param Order $order
     * @param float $refundAmount
     *
     * @return array
     */
    private function determineRefundData($order, float $refundAmount): array
    {
        $refundItems = [];

        $orderDetails  = OrderDetail::getList($order->id);

        $remainingRefundAmount = $refundAmount;
        foreach ($orderDetails as $orderDetail) {
            $quantityAvailable = $this->getProductQuantityAvailable($orderDetail);
            $unitPrice = $this->getProductUnitPrice($orderDetail, $this->isTaxIncludedInOrder($order));

            $product = $this->getProductQuantityForRefund($quantityAvailable, $unitPrice, $remainingRefundAmount);
            $remainingRefundAmount -= $product['amount'];
            $refundItems[$orderDetail['id_order_detail']] = $product;

            if ($remainingRefundAmount < 0.005) {
                break;
            }
        }

        $shippingAmount = 0;
        if ($remainingRefundAmount > 0.005) {
            $shippingAmount = $this->determineShippingRefundAmount(
                $this->getShippingAmountAvailable($order),
                $remainingRefundAmount
            );
        }

        return [
            "products" => $refundItems,
            "shipping_amount" => $shippingAmount,
        ];
    }

    /**
     * Determine shipping amount to be refunded
     *
     * @param float $availableShippingAmount
     * @param float $remainingRefundAmount
     *
     * @return float
     */
    private function determineShippingRefundAmount(
        float $availableShippingAmount,
        float $remainingRefundAmount
    ): float {
        if ($remainingRefundAmount > $availableShippingAmount) {
            return $availableShippingAmount;
        }
        return $remainingRefundAmount;
    }

    /**
     * Get shipping amount available for refund
     *
     * @param Order $order
     *
     * @return float
     */
    private function getShippingAmountAvailable(Order $order): float
    {
        $shippingMaxRefund = new DecimalNumber(
            $this->isTaxIncludedInOrder($order) ?
                (string) $order->total_shipping_tax_incl :
                (string) $order->total_shipping_tax_excl
        );

        $shippingSlipResume = OrderSlip::getShippingSlipResume($order->id);
        $shippingSlipTotalTaxIncl = new DecimalNumber((string) ($shippingSlipResume['total_shipping_tax_incl'] ?? 0));
        return (float) (string) $shippingMaxRefund->minus($shippingSlipTotalTaxIncl);
    }

    /**
     * Get product quantity and amount, full quantity or partial quantity
     *
     * @param integer $quantityAvailable
     * @param float $unitPrice
     * @param float $remainingRefundAmount
     *
     * @return array
     */
    private function getProductQuantityForRefund(
        int $quantityAvailable,
        float $unitPrice,
        float $remainingRefundAmount
    ): array {
        $productMaxRefund = $quantityAvailable * $unitPrice;
        if ($productMaxRefund <= $remainingRefundAmount) {
            return [
                "amount" => $productMaxRefund,
                "quantity" => $quantityAvailable
            ];
        } else {
            return [
                "amount" => $remainingRefundAmount,
                "quantity" => ceil($remainingRefundAmount / $unitPrice)
            ];
        }
    }

    /**
     * Get quantity available for refund for order item
     *
     * @param array $orderDetail
     *
     * @return integer
     */
    protected function getProductQuantityAvailable(array $orderDetail): int
    {
        return (int)$orderDetail['product_quantity'] - (int)$orderDetail['product_quantity_return'] - (int)$orderDetail['product_quantity_refunded'];
    }

    /**
     * Get unit price for order item
     *
     * @param array $orderDetail
     * @param boolean $isTaxIncluded
     *
     * @return float
     */
    private function getProductUnitPrice(array $orderDetail, bool $isTaxIncluded): float
    {
        return $isTaxIncluded ? (float) $orderDetail['unit_price_tax_incl'] : (float) $orderDetail['unit_price_tax_excl'];
    }

    /**
     * @param Order $order
     *
     * @return bool
     */
    private function isTaxIncludedInOrder(Order $order): bool
    {
        $customer = new Customer($order->id_customer);

        $taxCalculationMethod = Group::getPriceDisplayMethod((int) $customer->id_default_group);

        return $taxCalculationMethod === PS_TAX_INC;
    }
}
