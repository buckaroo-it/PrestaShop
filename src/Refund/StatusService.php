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

namespace Buckaroo\PrestaShop\Src\Refund;

use Buckaroo\PrestaShop\Src\Entity\BkRefundRequest;
use Doctrine\ORM\EntityManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

class StatusService
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Set order to refunded if its not already refunded
     *
     * @param \Order $order
     *
     * @return void
     */
    public function setRefunded(\Order $order)
    {
        $statusRefunded = \Configuration::get('PS_OS_REFUND');
        $statusPartialRefunded = \Configuration::get('PS_CHECKOUT_STATE_PARTIALLY_REFUNDED');

        // If required order states are not configured, do not attempt to update history
        if ((int) $statusRefunded <= 0 && (int) $statusPartialRefunded <= 0) {
            return;
        }

        $orderState = $order->getCurrentOrderState();
        $currentStatusId = $orderState !== null ? (int) $orderState->id : 0;
        $isCurrentlyRefunded = $currentStatusId === (int) $statusRefunded;
        $isCurrentlyPartiallyRefunded = $currentStatusId === (int) $statusPartialRefunded;

        // If order is fully refunded, set the "Refunded" status
        if ((int) $statusRefunded > 0 && $this->isReadyToBeRefunded($order) && !$isCurrentlyRefunded) {
            $this->update($order->id, $statusRefunded);

            return;
        }

        // set the "Partial refund" status (unless it's already set)
        if ((int) $statusPartialRefunded > 0 && $this->isPartiallyRefunded($order) && !$isCurrentlyPartiallyRefunded) {
            $this->update($order->id, $statusPartialRefunded);
        }
    }

    /**
     * Check to see if order is ready to be refunded
     *
     * @param \Order $order
     *
     * @return bool
     */
    private function isReadyToBeRefunded(\Order $order)
    {
        $refunded = $this->getRefundedAmount($order);

        // Fully refunded if (almost) the entire total has been refunded
        return $refunded >= ($order->total_paid - 0.005);
    }

    /**
     * Check whether order is partially refunded
     *
     * @param \Order $order
     *
     * @return bool
     */
    private function isPartiallyRefunded(\Order $order)
    {
        $refunded = $this->getRefundedAmount($order);

        // Nothing refunded
        if ($refunded <= 0) {
            return false;
        }

        // Partially refunded if some amount is refunded, but not (almost) the full total
        return $refunded < ($order->total_paid - 0.005);
    }

    /**
     * Get total successful refunded amount for an order
     *
     * @param \Order $order
     *
     * @return float
     */
    private function getRefundedAmount(\Order $order): float
    {
        $refundRequestRepository = $this->entityManager->getRepository(BkRefundRequest::class);
        $refunds = $refundRequestRepository->findBy([
            'orderId' => $order->id,
            'status' => BkRefundRequest::STATUS_SUCCESS,
        ]);

        $refunded = array_sum(array_map(
            function ($refund) {
                return $refund->getAmount();
            },
            $refunds
        ));

        return (float) $refunded;
    }

    /**
     * Update order status
     *
     * @param int $orderId
     * @param int $status
     *
     * @return void
     */
    public function update(int $orderId, $status)
    {
        $status = (int) $status;
        if ($status <= 0) {
            // Invalid status, do not create history entry
            return;
        }

        $history = new \OrderHistory();
        $history->id_order = $orderId;
        $history->date_add = date('Y-m-d H:i:s');
        $history->date_upd = date('Y-m-d H:i:s');
        $history->changeIdOrderState($status, $orderId);
        $history->addWithemail(false);
    }
}
