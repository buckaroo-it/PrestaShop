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

namespace Buckaroo\PrestaShop\Src\Service;

use Buckaroo\PrestaShop\Src\Entity\BkConfiguration;
use Buckaroo\PrestaShop\Src\Entity\BkPaymentMethods;
use Doctrine\ORM\EntityManager;

class BuckarooFeeService
{
    private $paymentMethodRepository;
    private $configurationRepository;
    public $logger;

    public function __construct(EntityManager $entityManager, $logger)
    {
        $this->configurationRepository = $entityManager->getRepository(BkConfiguration::class);
        $this->paymentMethodRepository = $entityManager->getRepository(BkPaymentMethods::class);
        $this->logger = $logger;
    }

    public function getBuckarooFees(): array
    {
        $result = [];
        $paymentMethods = $this->paymentMethodRepository->findAllPaymentMethods();

        foreach ($paymentMethods as $method) {
            $buckarooFee = $this->getBuckarooFeeValue($method->getName());

            if ($buckarooFee > 0) {
                $result[$method->getName()] = [
                    'buckarooFee' => $buckarooFee,
                    'buckarooFeeDisplay' => \Tools::displayPrice($buckarooFee),
                ];
            }
        }

        return $result;
    }

    public function getBuckarooFeeInputs($method)
    {
        return $this->getFeeData($this->getSpecificValueFromConfig($method, 'payment_fee'));
    }

    public function getConfigArrayForMethod($method)
    {
        $paymentMethod = $this->paymentMethodRepository->findOneByName($method);

        if (!$paymentMethod) {
            $this->logger->logError('Payment method not found: ' . $method);

            return null;
        }

        return $this->configurationRepository->getConfigArray($paymentMethod->getId());
    }

    public function getSpecificValueFromConfig($method, $key)
    {
        $configArray = $this->getConfigArrayForMethod($method);

        return $configArray[$key] ?? null;
    }

    public function getBuckarooFeeValue($method)
    {
        return $this->getSpecificValueFromConfig($method, 'payment_fee');
    }

    private function getFeeData($configArray): array
    {
        return $configArray > 0 ? [
            [
                'type' => 'hidden',
                'name' => 'payment-fee-price',
                'value' => $configArray,
            ],
            [
                'type' => 'hidden',
                'name' => 'payment-fee-price-display',
                'value' => \Tools::displayPrice($configArray),
            ],
        ] : [];
    }
}
