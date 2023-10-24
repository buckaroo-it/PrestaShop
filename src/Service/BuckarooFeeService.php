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

use PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException;

class BuckarooFeeService
{
    private $paymentMethodRepository;
    private $configurationRepository;
    public $logger;
    private $locale;

    public function __construct($bkConfigurationRepository, $bkPaymentMethodRepository, $logger)
    {
        $this->configurationRepository = $bkConfigurationRepository;
        $this->paymentMethodRepository = $bkPaymentMethodRepository;
        $this->logger = $logger;
        $this->locale = \Tools::getContextLocale(\Context::getContext());
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

            if ($buckarooFee > 0) {
                $formattedPrice = $this->formatPrice($buckarooFee);

                $result[$method->getName()] = [
                    'buckarooFee' => $buckarooFee,
                    'buckarooFeeDisplay' => $formattedPrice,
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

    /**
     * @throws LocalizationException
     */
    private function formatPrice($amount): string
    {
        $currency = \Context::getContext()->currency;

        return $this->locale->formatPrice($amount, $currency->iso_code);
    }

    /**
     * @throws LocalizationException
     */
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
                'value' => $this->formatPrice($configArray),
            ],
        ] : [];
    }
}
