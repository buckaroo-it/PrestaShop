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

namespace Buckaroo\Prestashop\Refund\Decorators;

use Buckaroo\Prestashop\Refund\Handler;
use Buckaroo\Prestashop\Refund\Commands\IssuePartialRefund;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\IssuePartialRefundCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\CommandHandler\IssuePartialRefundHandlerInterface;


class IssuePartialRefundHandler implements IssuePartialRefundHandlerInterface
{
    /**
     * @var IssuePartialRefundHandlerInterface
     */
    protected $handler;

    /**
     * @var Handler
     */
    protected $refundHandler;

    public function __construct(
        IssuePartialRefundHandlerInterface $handler,
        Handler $refundHandler
    ) {
        $this->handler = $handler;
        $this->refundHandler = $refundHandler;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(IssuePartialRefundCommand $command): void
    {
        if(!$command instanceof IssuePartialRefund) {
            $this->refundHandler->execute($command);
        }
        $this->handler->handle($command);
    }
}
