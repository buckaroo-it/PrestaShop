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
use PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException;

if (!defined('_PS_VERSION_')) {
    exit;
}

class BuckarooFeeService
{
    private $paymentMethodRepository;
    private $configurationRepository;
    private $locale;

    public function __construct(EntityManager $entityManager)
    {
        $this->configurationRepository = $entityManager->getRepository(BkConfiguration::class);
        $this->paymentMethodRepository = $entityManager->getRepository(BkPaymentMethods::class);
        $this->locale = \Tools::getContextLocale(\Context::getContext());
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
        foreach ($this->paymentMethodRepository->findAll() as $m) {
            $val = $this->getBuckarooFeeValue($m->getName());

            if (!$val) {
                continue;
            }

            // fixed → nice price format,  percent → keep “2%”
            $display = str_contains($val, '%') ? $val : $this->formatPrice($val);

            $result[$m->getName()] = [
                'buckarooFee'        => $val,
                'buckarooFeeDisplay' => $display,
            ];
        }
        return $result;
    }

    public function getBuckarooFeeInputs(string $method): array
    {
        $raw = $this->getBuckarooFeeValue($method);

        if (!$raw) {
            return [];
        }

        return [
            ['type' => 'hidden', 'name' => 'payment-fee-price',         'value' => $raw],
            ['type' => 'hidden', 'name' => 'payment-fee-price-display', 'value' => $raw],
        ];
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

    private function feePair(string $method): array
    {
        $cfg  = $this->getConfigArrayForMethod($method) ?: [];

        return [
            'fixed'   => isset($cfg['fee_fixed'])   ? (float) $cfg['fee_fixed']   : 0.0,
            'percent' => isset($cfg['fee_percent']) ? (float) $cfg['fee_percent'] : 0.0,
        ];
    }

    public function getBuckarooFeeValue(string $method)
    {
        [$fixed, $percent] = array_values($this->feePair($method));

        if ($fixed && $percent) {
            // fixed wins (same rule as UI + getBuckarooFee() in main module)
            $percent = 0;
        }

        return $fixed > 0
            ? number_format($fixed, 2, '.', '')                // e.g. 1.50
            : ($percent > 0 ? rtrim(rtrim($percent, '0'), '.') . '%' : null);  // e.g. 2%
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
