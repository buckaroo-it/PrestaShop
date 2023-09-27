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
namespace Buckaroo\Src\Refund\Admin;

use Buckaroo\Src\Entity\BkRefundRequest;
use Doctrine\ORM\EntityManager;
use PrestaShopBundle\Service\Routing\Router;

class Provider
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var Router
     */
    protected $router;

    public function __construct(
        EntityManager $entityManager,
        Router $router
    ) {
        $this->entityManager = $entityManager;
        $this->router = $router;
    }

    public function get(\Order $order): array
    {
        $refunds = $this->getRefundRequests($order->id);

        return [
            'orderId' => $order->id,
            'currencyId' => $order->id_currency,
            'refunds' => $refunds,
            'maxAvailableAmount' => $this->getAvailableRefundAmount($order, $refunds),
            'ajaxUrl' => $this->router->generate('buckaroo_refund'),
        ];
    }

    /**
     * Get available amount for refund
     *
     * @param \Order $order
     * @param array  $refunds
     *
     * @return float
     */
    private function getAvailableRefundAmount(\Order $order, array $refunds): float
    {
        $refunded = array_sum(
            array_map(
                function ($refund) {
                    return $refund->getAmount();
                },
                array_filter(
                    $refunds,
                    function ($refund) {
                        return $refund->getStatus() === BkRefundRequest::STATUS_SUCCESS;
                    }
                )
            )
        );

        $amount = $order->total_paid - $refunded;
        if (abs($amount) <= 0.005) {
            return 0;
        }

        return round($amount, 2);
    }

    /**
     * Get refund requests from databases
     *
     * @param int $orderId
     *
     * @return array
     */
    private function getRefundRequests(int $orderId): array
    {
        $repository = $this->entityManager->getRepository(BkRefundRequest::class);

        return $repository->findBy(
            ['orderId' => $orderId],
            ['createdAt' => 'desc']
        );
    }
}
