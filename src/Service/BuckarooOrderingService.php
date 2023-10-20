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

use Buckaroo\PrestaShop\Src\Repository\BkOrderingRepositoryInterface;

class BuckarooOrderingService
{
    protected BkOrderingRepositoryInterface $orderingRepository;

    public function __construct(BkOrderingRepositoryInterface $bkOrderingRepository)
    {
        $this->orderingRepository = $bkOrderingRepository;
    }

    public function getOrderingByCountryIsoCode(?string $isoCode2)
    {
        return $this->orderingRepository->getOrdering($isoCode2);
    }

    public function updateOrderingByCountryId($value, $countryId)
    {
        return $this->orderingRepository->updateOrdering($value, $countryId);
    }

    public function getPositionByCountryId(int $countryId): ?array
    {
        return $this->orderingRepository->fetchPositions($countryId) ?? $this->orderingRepository->fetchPositions(null);
    }
}
