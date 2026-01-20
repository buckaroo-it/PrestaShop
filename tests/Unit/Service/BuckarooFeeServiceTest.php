<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Src\Service;

use Buckaroo\PrestaShop\Src\Entity\BkConfiguration;
use Buckaroo\PrestaShop\Src\Entity\BkPaymentMethods;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for BuckarooFeeService fee values and display formatting.
 */
class BuckarooFeeServiceTest extends TestCase
{
    private function createService(array $paymentMethods, array $configByMethodId): BuckarooFeeService
    {
        $paymentRepo = new class($paymentMethods) {
            /** @var array<int, object> */
            private $methods;

            public function __construct(array $methods)
            {
                $this->methods = $methods;
            }

            public function findAll(): array
            {
                return $this->methods;
            }

            public function findOneBy(array $criteria)
            {
                if (!isset($criteria['label'])) {
                    return null;
                }
                foreach ($this->methods as $method) {
                    if ($method->getLabel() === $criteria['label']) {
                        return $method;
                    }
                }

                return null;
            }
        };

        $configRepo = new class($configByMethodId) {
            private $config;

            public function __construct(array $config)
            {
                $this->config = $config;
            }

            public function getConfigArray(int $paymentMethodId): ?array
            {
                return $this->config[$paymentMethodId] ?? null;
            }
        };

        $entityManager = new class($paymentRepo, $configRepo) extends EntityManager {
            private $paymentRepo;
            private $configRepo;

            public function __construct($paymentRepo, $configRepo)
            {
                $this->paymentRepo = $paymentRepo;
                $this->configRepo = $configRepo;
            }

            public function getRepository($className)
            {
                if ($className === BkPaymentMethods::class) {
                    return $this->paymentRepo;
                }
                if ($className === BkConfiguration::class) {
                    return $this->configRepo;
                }

                return null;
            }
        };

        return new BuckarooFeeService($entityManager);
    }

    public function testGetBuckarooFeesPercentageFee(): void
    {
        $method = new class {
            public function getId(): int
            {
                return 1;
            }

            public function getName(): string
            {
                return 'ideal';
            }

            public function getLabel(): string
            {
                return 'iDEAL';
            }
        };

        $service = $this->createService(
            [$method],
            [
                1 => ['payment_fee' => '2.5%'],
            ]
        );

        $fees = $service->getBuckarooFees();

        $this->assertArrayHasKey('ideal', $fees);
        $this->assertSame('2.5%', $fees['ideal']['buckarooFee']);
        $this->assertSame('2.5%', $fees['ideal']['buckarooFeeDisplay']);
    }

    public function testGetBuckarooFeesFixedFee(): void
    {
        $method = new class {
            public function getId(): int
            {
                return 2;
            }

            public function getName(): string
            {
                return 'creditcard';
            }

            public function getLabel(): string
            {
                return 'Credit Card';
            }
        };

        $service = $this->createService(
            [$method],
            [
                2 => ['payment_fee' => 1.23],
            ]
        );

        $fees = $service->getBuckarooFees();

        $this->assertArrayHasKey('creditcard', $fees);
        $this->assertSame(1.23, $fees['creditcard']['buckarooFee']);
        // Our Tools stub formats as "EUR {amount}"
        $this->assertSame('EUR 1.23', $fees['creditcard']['buckarooFeeDisplay']);
    }

    public function testGetBuckarooFeesSkipsZeroOrEmptyFee(): void
    {
        $method = new class {
            public function getId(): int
            {
                return 3;
            }

            public function getName(): string
            {
                return 'free';
            }

            public function getLabel(): string
            {
                return 'Free';
            }
        };

        $service = $this->createService(
            [$method],
            [
                3 => ['payment_fee' => 0],
            ]
        );

        $fees = $service->getBuckarooFees();

        $this->assertArrayNotHasKey('free', $fees);
    }

    public function testGetBuckarooFeeInputsPercentage(): void
    {
        $method = new class {
            public function getId(): int
            {
                return 4;
            }

            public function getName(): string
            {
                return 'afterpay';
            }

            public function getLabel(): string
            {
                return 'AfterPay';
            }
        };

        $service = $this->createService(
            [$method],
            [
                4 => ['payment_fee' => '5%'],
            ]
        );

        $inputs = $service->getBuckarooFeeInputs('afterpay');

        $this->assertCount(3, $inputs);
        $this->assertSame('afterpay', $inputs[0]['value']);
        $this->assertSame('5%', $inputs[1]['value']);
        $this->assertSame('5%', $inputs[2]['value']);
    }

    public function testGetBuckarooFeeInputsFixedAmount(): void
    {
        $method = new class {
            public function getId(): int
            {
                return 5;
            }

            public function getName(): string
            {
                return 'paypal';
            }

            public function getLabel(): string
            {
                return 'PayPal';
            }
        };

        $service = $this->createService(
            [$method],
            [
                5 => ['payment_fee' => 2.50],
            ]
        );

        $inputs = $service->getBuckarooFeeInputs('paypal');

        $this->assertCount(3, $inputs);
        $this->assertSame('paypal', $inputs[0]['value']);
        $this->assertSame(2.50, $inputs[1]['value']);
        $this->assertSame('EUR 2.50', $inputs[2]['value']);
    }

    public function testGetPaymentMethodByLabelResolvesMethodName(): void
    {
        $method = new class {
            public function getId(): int
            {
                return 6;
            }

            public function getName(): string
            {
                return 'ideal';
            }

            public function getLabel(): string
            {
                return 'iDEAL';
            }
        };

        $service = $this->createService(
            [$method],
            [
                6 => ['payment_fee' => 1.00],
            ]
        );

        $this->assertSame('ideal', $service->getPaymentMethodByLabel('iDEAL'));
        $this->assertNull($service->getPaymentMethodByLabel('Unknown'));
    }
}

