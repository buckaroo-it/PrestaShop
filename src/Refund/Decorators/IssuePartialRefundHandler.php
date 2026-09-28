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

namespace Buckaroo\PrestaShop\Src\Refund\Decorators;

use Buckaroo\PrestaShop\Src\Refund\Handler;
use Buckaroo\PrestaShop\Src\Refund\Settings;
use Buckaroo\PrestaShop\Src\Refund\StatusService;
use Buckaroo\PrestaShop\Src\Repository\RawBuckarooFeeRepository;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\IssuePartialRefundCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\CommandHandler\IssuePartialRefundHandlerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

class IssuePartialRefundHandler implements IssuePartialRefundHandlerInterface
{
    public const KEY_SKIP_REFUND_REQUEST = 'buckaroo_skip_refund';

    /**
     * @var IssuePartialRefundHandlerInterface
     */
    protected $handler;

    /**
     * @var Handler
     */
    protected $refundHandler;

    /**
     * @var SessionInterface
     */
    protected $session;

    /**
     * @var StatusService
     */
    private $statusService;

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(
        IssuePartialRefundHandlerInterface $handler,
        Handler $refundHandler,
        SessionInterface $session,
        StatusService $statusService,
        RequestStack $requestStack
    ) {
        $this->handler = $handler;
        $this->refundHandler = $refundHandler;
        $this->session = $session;
        $this->statusService = $statusService;
        $this->requestStack = $requestStack;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(IssuePartialRefundCommand $command): void
    {
        $buckarooRefundEnabled = (bool) \Configuration::get(Settings::LABEL_REFUND_CONF);
        $orderId = $command->getOrderId()->getValue();

        if ($buckarooRefundEnabled) {
            $refundSummary = $this->refundHandler->getRefundSummary($command);
        }

        $this->handler->handle($command);

        if ($buckarooRefundEnabled && !$this->session->has(self::KEY_SKIP_REFUND_REQUEST)) {
            $feeAmount = $this->getRequestedFeeAmount($orderId);

            $this->refundHandler->execute($command, $refundSummary, $feeAmount);

            if ($feeAmount > 0.0) {
                (new RawBuckarooFeeRepository())->addRefundedFeeAmount($orderId, $feeAmount);
            }

            $this->session->remove(self::KEY_SKIP_REFUND_REQUEST);
        } elseif (!$buckarooRefundEnabled) {
            $order = new \Order($command->getOrderId()->getValue());
            $this->statusService->setRefunded($order);
        }
    }

    /**
     * Payment fee amount submitted along with the native partial refund form.
     *
     * The submitted value is never trusted: it is capped at the part of the fee
     * that has not been refunded yet.
     *
     * @param int $orderId
     *
     * @return float
     */
    private function getRequestedFeeAmount(int $orderId): float
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return 0.0;
        }

        $submitted = $request->request->get(Settings::FIELD_FEE_AMOUNT);

        if ($submitted === null || $submitted === '') {
            return 0.0;
        }

        $amount = (float) str_replace(',', '.', (string) $submitted);

        if ($amount <= 0) {
            return 0.0;
        }

        $refundable = (new RawBuckarooFeeRepository())->getRefundableFeeAmount($orderId);

        return round(min($amount, $refundable), 2);
    }
}
