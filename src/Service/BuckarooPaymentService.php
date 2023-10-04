<?php

namespace Buckaroo\PrestaShop\Src\Service;

use Buckaroo\PrestaShop\Src\Repository\OrderingRepository;
class BuckarooPaymentService
{
    private $orderingRepository;
    private $context;

    public function __construct() {
        $this->orderingRepository = new OrderingRepository();
        $this->context = \Context::getContext();
    }

    public function getSortedPaymentOptions($payment_options): array
    {
        $countryId = $this->context->country->id;
        $positions = $this->orderingRepository->getPositionByCountryId($countryId);
        $positions = array_flip($positions);

        $filteredOptions = $this->filterActivePaymentOptions($payment_options, $positions);
        return $this->sortPaymentOptionsByPosition($filteredOptions, $positions);
    }

    private function filterActivePaymentOptions($payment_options, array $positions): array {
        return array_filter($payment_options, function($option) use ($positions) {
            return isset($positions[$option->getModuleName()]);
        });
    }

    private function sortPaymentOptionsByPosition(array $payment_options, array $positions): array {
        usort($payment_options, function ($a, $b) use ($positions) {
            $positionA = isset($positions[$a->getModuleName()]) ? $positions[$a->getModuleName()] : 0;
            $positionB = isset($positions[$b->getModuleName()]) ? $positions[$b->getModuleName()] : 0;
            return $positionA - $positionB;
        });

        return $payment_options;
    }
}