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

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Tests\Unit\Service;

use Buckaroo\PrestaShop\Src\Service\PaymentLabelResolver;
use PHPUnit\Framework\TestCase;

final class PaymentLabelResolverTest extends TestCase
{
    private const DEFAULT_LABEL = 'Buckaroo Payments';

    public function testNullOrInvalidResponseFallsBackToTheDefaultLabel(): void
    {
        $resolver = new PaymentLabelResolver();

        self::assertSame(self::DEFAULT_LABEL, $resolver->resolve(null, self::DEFAULT_LABEL));
        self::assertSame(self::DEFAULT_LABEL, $resolver->resolve($this->response(false, true, 'ideal'), self::DEFAULT_LABEL));
    }

    public function testGroupTransactionsGetAFixedLabel(): void
    {
        $response = $this->response(true, true, 'giftcard', 'BUCKAROO_SUCCESS', PaymentLabelResolver::GROUP_TRANSACTION_TYPE);

        self::assertSame('Group transaction', (new PaymentLabelResolver())->resolve($response, self::DEFAULT_LABEL));
    }

    public function testSucceededResponseUsesThePaymentMethodCode(): void
    {
        $response = $this->response(true, true, 'ideal', 'BUCKAROO_SUCCESS');

        self::assertSame('ideal', (new PaymentLabelResolver())->resolve($response, self::DEFAULT_LABEL));
    }

    public function testPendingResponseUsesTheConfiguredMethodLabel(): void
    {
        $repository = new class {
            public function getPaymentMethodsLabel($name)
            {
                return $name === 'ideal' ? 'iDEAL | Wero' : null;
            }
        };
        $resolver = new PaymentLabelResolver($repository);

        self::assertSame('iDEAL | Wero', $resolver->resolve($this->response(true, false, 'ideal', 'BUCKAROO_PENDING_PAYMENT'), self::DEFAULT_LABEL));
        self::assertSame(self::DEFAULT_LABEL, $resolver->resolve($this->response(true, false, 'unknown', 'BUCKAROO_FAILED'), self::DEFAULT_LABEL));
    }

    public function testResponseWithoutUsableDataFallsBackToTheDefaultLabel(): void
    {
        $resolver = new PaymentLabelResolver();

        self::assertSame(self::DEFAULT_LABEL, $resolver->resolve($this->response(true, true, null), self::DEFAULT_LABEL));
        self::assertSame(self::DEFAULT_LABEL, $resolver->resolve($this->response(true, false, 'ideal', null), self::DEFAULT_LABEL));
    }

    public function testResolvingALabelNeverTouchesTheModuleDisplayName(): void
    {
        $module = new \stdClass();
        $module->displayName = self::DEFAULT_LABEL;

        $label = (new PaymentLabelResolver())->resolve($this->response(true, true, 'creditcard', 'BUCKAROO_SUCCESS'), $module->displayName);

        self::assertSame('creditcard', $label);
        self::assertSame(self::DEFAULT_LABEL, $module->displayName);
    }

    private function response(bool $valid, bool $succeeded, ?string $method, ?string $status = null, ?string $transactionType = null): object
    {
        return new class($valid, $succeeded, $method, $status, $transactionType) {
            public $payment_method;
            public $status;
            public $brq_transaction_type;
            private $valid;
            private $succeeded;

            public function __construct(bool $valid, bool $succeeded, ?string $method, ?string $status, ?string $transactionType)
            {
                $this->valid = $valid;
                $this->succeeded = $succeeded;
                $this->payment_method = $method;
                $this->status = $status;
                $this->brq_transaction_type = $transactionType;
            }

            public function isValid(): bool
            {
                return $this->valid;
            }

            public function hasSucceeded(): bool
            {
                return $this->succeeded;
            }
        };
    }
}
