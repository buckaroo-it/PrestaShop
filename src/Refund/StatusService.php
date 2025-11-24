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

        $orderState = $order->getCurrentOrderState();
        $isCurrentlyRefunded = $orderState !== null && $orderState->id == $statusRefunded;

        if ($this->isReadyToBeRefunded($order) && !$isCurrentlyRefunded) {
            $this->update($order->id, $statusRefunded);
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

        return abs($order->total_paid - $refunded) < 0.005;
    }

    /**
     * Update order status
     * Enhanced for PrestaShop 9.0.1 compatibility with proper validation
     *
     * @param int $orderId
     * @param int $status
     *
     * @return void
     */
    public function update(int $orderId, $status)
    {
        try {
            $order = new \Order($orderId);

            // Validate order exists
            if (!\Validate::isLoadedObject($order)) {
                return;
            }

            $currentState = (int) $order->getCurrentState();
            $newStatus = (int) $status;

            // Skip if same state
            if ($currentState === $newStatus) {
                return;
            }

            // Validate order state transition (if Buckaroo3 class is available)
            if (class_exists('Buckaroo3') && method_exists('Buckaroo3', 'isValidOrderStateTransition')) {
                if (!Buckaroo3::isValidOrderStateTransition($orderId, $newStatus)) {
                    return;
                }
            }

            // Validate order state exists
            $orderState = new \OrderState($newStatus);
            if (!\Validate::isLoadedObject($orderState)) {
                return;
            }

            $history = new \OrderHistory();
            $history->id_order = $orderId;
            $history->date_add = date('Y-m-d H:i:s');
            $history->date_upd = date('Y-m-d H:i:s');

            // Use order object instead of ID for better PrestaShop 9.0.1 compatibility
            // Determine if we should use existing payments
            $useExistingPayments = !$order->hasInvoice();

            // Change order state with proper order object and payment handling
            $history->changeIdOrderState($newStatus, $order, $useExistingPayments);

            // Use addWithemail (correct method name for PrestaShop 9.0.1)
            $historyAdded = $history->addWithemail(false);

            if (!$historyAdded) {
                // Log error if logging is available
                if (class_exists('\Logger')) {
                    $logger = new \Logger(\Logger::ERROR, 'refund_status');
                    $logger->logError('Failed to add order history entry for order #' . $orderId);
                }
            }
        } catch (\Exception $e) {
            // Log error if logging is available
            if (class_exists('\Logger')) {
                $logger = new \Logger(\Logger::ERROR, 'refund_status');
                $logger->logError('Error updating order status for order #' . $orderId . ': ' . $e->getMessage());
            }
        }
    }
}
