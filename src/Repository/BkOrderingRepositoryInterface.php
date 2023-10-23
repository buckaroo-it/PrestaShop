<?php

namespace Buckaroo\PrestaShop\Src\Repository;

interface BkOrderingRepositoryInterface
{
    public function getOrdering(?string $isoCode2);

    public function updateOrdering($value, $countryId = null);

    public function fetchPositions($countryId);
}
