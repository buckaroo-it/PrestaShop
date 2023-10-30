<?php

namespace Buckaroo\PrestaShop\Src\Repository;

interface BkPaymentMethodRepositoryInterface
{
    public function fetchMethodsFromDBWithConfig(int $isPaymentMethod): array;
}
