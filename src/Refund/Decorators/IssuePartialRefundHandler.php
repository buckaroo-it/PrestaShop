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

namespace Buckaroo\Prestashop\Refund\Decorators;

use Buckaroo\Prestashop\Refund\Handler;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\IssuePartialRefundCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\CommandHandler\IssuePartialRefundHandlerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

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

    public function __construct(
        IssuePartialRefundHandlerInterface $handler,
        Handler $refundHandler,
        SessionInterface $session
    ) {
        $this->handler = $handler;
        $this->refundHandler = $refundHandler;
        $this->session = $session;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(IssuePartialRefundCommand $command): void
    {
        $refundSummary = $this->refundHandler->getRefundSummary($command);
        $this->handler->handle($command);
        if(
            !$this->session->has(self::KEY_SKIP_REFUND_REQUEST)
        ) {
            $this->refundHandler->execute($command, $refundSummary);
            $this->session->remove(self::KEY_SKIP_REFUND_REQUEST);
        }

    }
}
