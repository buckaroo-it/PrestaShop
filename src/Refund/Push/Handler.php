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

namespace Buckaroo\Prestashop\Refund\Push;

use Buckaroo\Prestashop\Entity\BkRefundRequest;
use Buckaroo\Prestashop\Refund\OrderService;
use Buckaroo\Prestashop\Refund\Payment\Service as PaymentService;
use Buckaroo\Prestashop\Refund\Settings;
use Buckaroo\Resources\Constants\ResponseStatus;
use Doctrine\ORM\EntityManager;
use Order;
use Symfony\Component\HttpFoundation\Request;

class Handler
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var PaymentService
     */
    protected $paymentService;

    /**
     * @var OrderService
     */
    protected $refundOrderService;

    public function __construct(
        EntityManager $entityManager,
        OrderService $refundOrderService,
        PaymentService $paymentService
    ) {
        $this->request = Request::createFromGlobals();
        $this->entityManager = $entityManager;
        $this->refundOrderService = $refundOrderService;
        $this->paymentService = $paymentService;
    }

    public function handle()
    {
        $order = $this->getOrder();
        if ($order === null) {
            throw new \Exception('Cannot determine order');
        }

        $refundRequest = $this->getRefundRequest();
        if ($refundRequest === null) {
            return $this->addRefundToOrder($order);
        }

        return $this->updateRefundRequest($refundRequest);
    }

    /**
     * Attempt to do a prestashop refund
     *
     * @param \Order $order
     *
     * @return void
     */
    private function addRefundToOrder(\Order $order)
    {
        try {
            $this->createRefundRequest($order->id);
            $this->createNegativePayment($order);
            $this->refundOrderService->refund(
                $order,
                $this->getRefundAmount(),
            );
        } catch (\Throwable $th) {
            throw new \Exception('Cannot update order with refund', 0, $th);
        }
    }

    /**
     * Create negative payment if enabled and the push is successful
     *
     * @param \Order $order
     *
     * @return void
     */
    private function createNegativePayment(\Order $order)
    {
        if (
            \Configuration::get(Settings::LABEL_REFUND_CREATE_NEGATIVE_PAYMENT) == true
            && $this->refundIsSuccessful()
        ) {
            $this->paymentService->create(
                $order,
                $this->getRefundKey(),
                $this->request->get('brq_transaction_method'),
                (-1) * $this->getRefundAmount()
            );
        }
    }

    /**
     * Get refund amount
     *
     * @return float
     *
     * @throws \Exception
     */
    private function getRefundAmount(): float
    {
        $amount = $this->request->get('brq_amount_credit');

        if (!is_scalar($amount) || (float) $amount == (float) 0) {
            throw new \Exception('Invalid refund amount');
        }

        return (float) $amount;
    }

    /**
     * Get refund key
     *
     * @return string
     *
     * @throws \Exception
     */
    private function getRefundKey()
    {
        $refundKey = $this->request->get('brq_transactions');
        if (!is_string($refundKey)) {
            throw new \Exception('Invalid value for `brq_transactions`');
        }

        return $refundKey;
    }

    /**
     * Create a refund request to store state
     *
     * @param int $orderId
     *
     * @return void
     */
    private function createRefundRequest(int $orderId)
    {
        $paymentKey = $this->request->get('brq_relatedtransaction_refund');

        if (!is_string($paymentKey)) {
            throw new \Exception('Invalid value for `brq_relatedtransaction_refund`');
        }

        $refundRequest = new BkRefundRequest();
        $refundRequest->setData(['pushes' => [$this->request->request->all()]]);
        $refundRequest->setAmount($this->getRefundAmount());
        $refundRequest->setStatus(
            $this->refundIsSuccessful() ? BkRefundRequest::STATUS_SUCCESS : BkRefundRequest::STATUS_FAILED
        );
        $refundRequest->setOrderId($orderId);
        $refundRequest->setKey($this->getRefundKey());
        $refundRequest->setPaymentKey($paymentKey);
        $refundRequest->setCreatedAt(new \DateTime());

        $this->entityManager->persist($refundRequest);
        $this->entityManager->flush();
    }

    /**
     * Update refund request with the new status and
     * append the push data
     *
     * @param BkRefundRequest $refundRequest
     *
     * @return void
     */
    private function updateRefundRequest(BkRefundRequest $refundRequest)
    {
        $refundRequest->setData(['pushes' => [$this->request->request->all()]]);
        $refundRequest->setStatus(
            $this->refundIsSuccessful() ? BkRefundRequest::STATUS_SUCCESS : BkRefundRequest::STATUS_FAILED
        );
        $this->entityManager->flush($refundRequest);

        return $refundRequest;
    }

    public function refundIsSuccessful(): bool
    {
        return $this->request->request->get('brq_statuscode') === ResponseStatus::BUCKAROO_STATUSCODE_SUCCESS;
    }

    /**
     * Get saved refund request if any
     *
     * @return BkRefundRequest|null
     */
    private function getRefundRequest()
    {
        $refundKey = $this->request->get('brq_transactions');

        if (!is_string($refundKey)) {
            return null;
        }

        $refundRequestRepository = $this->entityManager->getRepository(BkRefundRequest::class);

        return $refundRequestRepository->findOneBy([
            'key' => $refundKey,
        ]);
    }

    /**
     * Get order by cart id from invoice number
     *
     * @return \Order|null
     */
    private function getOrder()
    {
        $cartId = $this->getCartId();
        if ($cartId === null) {
            return null;
        }

        return \Order::getByCartId($cartId);
    }

    /**
     * Get cart id from invoice number
     *
     * @return int|null
     */
    private function getCartId()
    {
        $invoiceNumber = $this->request->get('brq_invoicenumber');

        if (!is_string($invoiceNumber)) {
            return null;
        }
        $parts = explode('_', urldecode($invoiceNumber));

        if (!isset($parts[1])) {
            return null;
        }

        return (int) $parts[1];
    }
}
