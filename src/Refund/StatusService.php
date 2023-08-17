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

use Order;
use Configuration;
use Doctrine\ORM\EntityManager;
use Buckaroo\Prestashop\Entity\BkRefundRequest;

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
    
        $statusRefunded = Configuration::get('PS_OS_REFUND');

        $orderState = $order->getCurrentOrderState();
        $isCurrentlyRefunded =  $orderState !== null && $orderState->id == $statusRefunded;

        if ($this->isReadyToBeRefunded($order) && !$isCurrentlyRefunded) {
            $this->update($order->id, $statusRefunded);
        }
    }

    /**
     * Check to see if order is ready to be refunded
     *
     * @param Order $order
     *
     * @return bool
     */
    private function isReadyToBeRefunded(Order $order)
    {
        $refundRequestRepository = $this->entityManager->getRepository(BkRefundRequest::class);
        $refunds = $refundRequestRepository->findBy([
            "orderId" => $order->id,
            "status" => BkRefundRequest::STATUS_SUCCESS
        ]);

        $refunded = array_sum(array_map(
            function ($refund) {
                return $refund->getAmount();
            },
            $refunds
        ));

        return abs($order->total_paid - $refunded) < 0.005;
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
        $history = new \OrderHistory();
        $history->id_order = $orderId;
        $history->date_add = date('Y-m-d H:i:s');
        $history->date_upd = date('Y-m-d H:i:s');
        $history->changeIdOrderState($status, $orderId);
        $history->addWithemail(false);
    }
}
