<?php

namespace Buckaroo\PrestaShop\Src\Repository;

interface BkConfigurationRepositoryInterface
{
    public function getConfigArray(int $paymentId);

    public function updateConfig(int $paymentId, array $config);

    public function getActiveCreditCards();
}
