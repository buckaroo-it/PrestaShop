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

namespace Buckaroo\Prestashop\Refund\Request\Response;

use Order;
use Configuration;
use Doctrine\ORM\EntityManager;
use Buckaroo\Prestashop\Refund\Settings;
use Buckaroo\Prestashop\Refund\Payment\Service as PaymentService;
use Buckaroo\Prestashop\Entity\BkRefundRequest;
use Buckaroo\Transaction\Response\TransactionResponse;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderException;

class Handler
{

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var PaymentService
     */
    private $paymentService;

    public function __construct(
        EntityManager $entityManager,
        PaymentService $paymentService
    ) {
        $this->entityManager = $entityManager;
        $this->paymentService = $paymentService;
    }

    public function parse(TransactionResponse $response, array $body, int $orderId)
    {
        $this->createRefundRequest($response, $body, $orderId);

        if (!$response->isSuccess()) {
            $message = '';
            if ($response->hasSomeError()) {
                $message = $response->getSomeError();
            }
            if (strlen($message) == 0) {
                $message = "Cannot create refund, check Buckaroo plaza for details https://plaza.buckaroo.nl/Transaction/Transactions/Details?transactionKey=" . $response->getTransactionKey(); //phpcs: ignore Generic.Files.LineLength.TooLong
            }
            throw new OrderException($message);
        }
        $this->createNegativePayment(new Order($orderId), $response);
    }


    private function createRefundRequest(TransactionResponse $response, array $body, int $orderId)
    {
        $amountRefunded = $this->getRefundedAmount($response);

        $refundRequest = new BkRefundRequest();
        $refundRequest->setStatus(
            $response->isSuccess() ? BkRefundRequest::STATUS_SUCCESS : BkRefundRequest::STATUS_FAILED
        );
        $refundRequest->setAmount($amountRefunded);
        $refundRequest->setOrderId($orderId);
        $refundRequest->setKey($response->getTransactionKey());
        $refundRequest->setPaymentKey($this->getPaymentKey($response));
        $refundRequest->setPayload($body);
        $refundRequest->setData(["response" => $response->toArray()]);
        $refundRequest->setCreatedAt(new \DateTime());

        $this->entityManager->persist($refundRequest);
        $this->entityManager->flush();
        return $refundRequest;
    }

    /**
     * Get refunded amount from response
     *
     * @param TransactionResponse $response
     *
     * @return float
     */
    private function getRefundedAmount(TransactionResponse $response): float
    {
        $amountRefunded = $response->get('AmountCredit');

        if (!is_scalar($amountRefunded)) {
            $amountRefunded = 0;
        }

        return floatval($amountRefunded);
    }

    protected function getPaymentKey(TransactionResponse $response): string
    {
        $related = $response->get('RelatedTransactions');
        if (
            !is_array($related) ||
            !isset($related['RelationType']) ||
            !isset($related['RelatedTransactionKey'])
        ) {

            if (
                !isset($related[0]['RelationType']) ||
                !isset($related[0]['RelatedTransactionKey'])
            ) {
                return $related[0]['RelatedTransactionKey'];
            }
            return '';
        }

        if ($related['RelationType'] === 'refund') {
            return $related['RelatedTransactionKey'];
        }

        return '';
    }

    /**
     * Create negative payment if enabled and the push is successful
     *
     * @param Order $order
     *
     * @return void
     */
    private function createNegativePayment(Order $order, TransactionResponse $response)
    {
        if (
            Configuration::get(Settings::LABEL_REFUND_CREATE_NEGATIVE_PAYMENT) == true
        ) {
            $this->paymentService->create(
                $order,
                $response->getTransactionKey(),
                $response->getMethod(),
                (-1) * $this->getRefundedAmount($response)
            );
        }
    }
}
