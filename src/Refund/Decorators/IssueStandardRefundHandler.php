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

namespace Buckaroo\Src\Refund\Decorators;

use Buckaroo\Src\Refund\Handler;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\IssueStandardRefundCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\CommandHandler\IssueStandardRefundHandlerInterface;

class IssueStandardRefundHandler implements IssueStandardRefundHandlerInterface
{
    /**
     * @var IssueStandardRefundHandlerInterface
     */
    protected $handler;

    /**
     * @var Handler
     */
    protected $refundHandler;

    public function __construct(
        IssueStandardRefundHandlerInterface $handler,
        Handler $refundHandler
    ) {
        $this->handler = $handler;
        $this->refundHandler = $refundHandler;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(IssueStandardRefundCommand $command): void
    {
        $refundSummary = $this->refundHandler->getRefundSummary($command);
        $this->handler->handle($command);
        $this->refundHandler->execute($command, $refundSummary);
    }
}
