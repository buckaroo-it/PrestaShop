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

use Buckaroo\PrestaShop\Src\Repository\PaymentMethodRepository;

class BuckarooFeeService
{
    /** @var PaymentMethodRepository */
    private $paymentMethodRepository;

    /** @var BuckarooConfigService */
    private $buckarooConfigService;

    public function __construct()
    {
        $this->paymentMethodRepository = new PaymentMethodRepository();
        $this->buckarooConfigService = new BuckarooConfigService();
    }

    public function getBuckarooFees(): array
    {
        $result = [];
        $paymentMethods = $this->paymentMethodRepository->getPaymentMethodNames();

        foreach ($paymentMethods as $method) {
            $buckarooFee = $this->getBuckarooFeeValue($method['name']);

            if ($buckarooFee > 0) {
                $result[$method['name']] = [
                    'buckarooFee' => $buckarooFee,
                    'buckarooFeeDisplay' => \Tools::displayPrice($buckarooFee),
                ];
            }
        }

        return $result;
    }

    public function getBuckarooFeeInputs($method)
    {
        return $this->getFeeData($this->buckarooConfigService->getSpecificValueFromConfig($method, 'payment_fee'));
    }

    public function getBuckarooFeeValue($method)
    {
        return $this->buckarooConfigService->getSpecificValueFromConfig($method, 'payment_fee');
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
