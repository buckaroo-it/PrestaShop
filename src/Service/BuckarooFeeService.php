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

use Buckaroo\PrestaShop\Src\Config\Config;
use Buckaroo\PrestaShop\Src\Context\ContextAwareTrait;
use Buckaroo\PrestaShop\Src\Entity\BkConfiguration;
use Buckaroo\PrestaShop\Src\Entity\BkPaymentMethods;
use Doctrine\ORM\EntityManager;
use PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException;

if (!defined('_PS_VERSION_')) {
    exit;
}

class BuckarooFeeService
{
    use ContextAwareTrait;

    private $paymentMethodRepository;
    private $configurationRepository;

    /**
     * @var PriceFormatter|null
     */
    private $priceFormatter;

    /**
     * @param EntityManager $entityManager
     * @param object|null $legacyContext PrestaShop context, or the prestashop.adapter.legacy.context service
     */
    public function __construct(EntityManager $entityManager, $legacyContext = null)
    {
        $this->configurationRepository = $entityManager->getRepository(BkConfiguration::class);
        $this->paymentMethodRepository = $entityManager->getRepository(BkPaymentMethods::class);
        $this->setContext($legacyContext);
    }

    public function getPaymentMethodByLabel($label)
    {
        $paymentMethod = $this->paymentMethodRepository->findOneBy(['label' => $label]);
        return $paymentMethod ? $paymentMethod->getName() : null;
    }

    /**
     * @throws LocalizationException
     */
    public function getBuckarooFees(): array
    {
        $result = [];
        $paymentMethods = $this->paymentMethodRepository->findAll();

        foreach ($paymentMethods as $method) {
            $buckarooFee = $this->getBuckarooFeeValue($method->getName());

            if (!$buckarooFee) {
                continue;
            }

            $isPercentage = is_string($buckarooFee) && strpos(trim($buckarooFee), '%') !== false;
            
            if ($isPercentage) {
                $result[$method->getName()] = [
                    'buckarooFee' => trim($buckarooFee),
                    'buckarooFeeDisplay' => trim($buckarooFee),
                ];
            } else {
                $buckarooFee = (float) $buckarooFee;
                if ($buckarooFee > 0) {
                    $formattedPrice = $this->formatPrice($buckarooFee);

                    $result[$method->getName()] = [
                        'buckarooFee' => $buckarooFee,
                        'buckarooFeeDisplay' => $formattedPrice,
                    ];
                }
            }
        }

        return $result;
    }

    public function getBuckarooFeeInputs($method)
    {
        $feeData = $this->getFeeData($this->getBuckarooFeeValue($method));

        $buckarooKeyInput = [
            'type' => 'hidden',
            'name' => 'buckarooKey',
            'value' => $method,
        ];

        return array_merge([$buckarooKeyInput], $feeData);
    }

    public function getConfigArrayForMethod($method)
    {
        $paymentMethod = $this->paymentMethodRepository->findOneByName($method);

        if (!$paymentMethod) {
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
        if (!Config::isPaymentFeeAllowed((string) $method)) {
            return null;
        }

        return $this->getSpecificValueFromConfig($method, 'payment_fee');
    }

    /**
     * @throws LocalizationException
     */
    private function formatPrice($amount): string
    {
        return $this->getPriceFormatter()->format($amount);
    }

    private function getPriceFormatter(): PriceFormatter
    {
        if ($this->priceFormatter === null) {
            $this->priceFormatter = new PriceFormatter($this->getContext());
        }

        return $this->priceFormatter;
    }

    /**
     * @throws LocalizationException
     */
    private function getFeeData($configArray): array
    {
        if (empty($configArray)) {
            return [];
        }

        // Check if fee is a percentage (contains %)
        $isPercentage = is_string($configArray) && strpos(trim($configArray), '%') !== false;

        if ($isPercentage) {
            return [
                [
                    'type' => 'hidden',
                    'name' => 'payment-fee-price',
                    'value' => trim($configArray),
                ],
                [
                    'type' => 'hidden',
                    'name' => 'payment-fee-price-display',
                    'value' => trim($configArray),
                ],
            ];
        }

        $feeAmount = (float) $configArray;
        if ($feeAmount > 0) {
            return [
                [
                    'type' => 'hidden',
                    'name' => 'payment-fee-price',
                    'value' => $feeAmount,
                ],
                [
                    'type' => 'hidden',
                    'name' => 'payment-fee-price-display',
                    'value' => $this->formatPrice($feeAmount),
                ],
            ];
        }

        return [];
    }
}
