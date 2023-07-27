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

use Order;
use OrderHistory;
use Configuration;
use PrestaShop\PrestaShop\Core\Domain\Order\Query\GetOrderForViewing;
use PrestaShop\PrestaShop\Core\Domain\Order\QueryResult\OrderForViewing;
use PrestaShop\PrestaShop\Adapter\Order\QueryHandler\GetOrderForViewingHandler;

class StatusService
{
    /**
     * @var GetOrderForViewingHandler
     */
    private $getOrderForViewingHandler;

    public function __construct(GetOrderForViewingHandler $getOrderForViewingHandler)
    {
        $this->getOrderForViewingHandler = $getOrderForViewingHandler;
    }

    /**
     * Set order to refunded if its not already refunded
     *
     * @param Order $order
     *
     * @return void
     */
    public function setRefunded(Order $order)
    {
        /** @var \PrestaShop\PrestaShop\Core\Domain\Order\QueryResult\OrderForViewing */
        $orderForViewing = $this->getOrderForViewingHandler->handle(
            new GetOrderForViewing((int) $order->id)
        );

        $statusRefunded = Configuration::get('PS_OS_REFUND');

        $orderState = $order->getCurrentOrderState();
        $isCurrentlyRefunded =  $orderState !== null && $orderState->id == $statusRefunded;

        if ($this->isReadyToBeRefunded($orderForViewing) && !$isCurrentlyRefunded) {
            $this->update($order->id, $statusRefunded);
        }
    }

    /**
     * Check prestashop to see if order is ready to be refunded
     *
     * @param OrderForViewing $orderForViewing
     *
     * @return boolean
     */
    private function isReadyToBeRefunded(OrderForViewing $orderForViewing)
    {
        /** @var OrderProductForViewing $product */
        foreach ($orderForViewing->getProducts()->getProducts() as $product) {
            if ($product->getQuantity() > $product->getQuantityRefunded()) {
                return false;
            }
        }

        return abs((float)(string)$orderForViewing->getPrices()->getShippingRefundableAmountRaw()) < 0.005;
    }


    /**
     * Update order status 
     *
     * @param integer $orderId
     * @param int $status
     *
     * @return void
     */
    public function update(int $orderId, $status)
    {
        $history           = new OrderHistory();
        $history->id_order = $orderId;
        $history->date_add = date('Y-m-d H:i:s');
        $history->date_upd = date('Y-m-d H:i:s');
        $history->changeIdOrderState($status, $orderId);
        $history->addWithemail(false);
    }
}
