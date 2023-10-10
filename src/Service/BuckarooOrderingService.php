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

use Buckaroo\PrestaShop\Src\Entity\BkOrdering;
use Buckaroo\PrestaShop\Src\Entity\BkPaymentMethods;
use Doctrine\ORM\EntityManager;

class BuckarooOrderingService
{
    protected $entityManager;
    protected $orderingRepository;

    protected $paymentMethodRepository;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->orderingRepository = $this->entityManager->getRepository(BkOrdering::class);
        $this->paymentMethodRepository = $this->entityManager->getRepository(BkPaymentMethods::class);
    }

    public function getOrderingByCountryIsoCode(?string $isoCode2)
    {
        return $this->orderingRepository->getOrdering($isoCode2);
    }

    public function updateOrderingByCountryId($value, $countryId)
    {
        return $this->orderingRepository->updateOrdering($value, $countryId);
    }

    public function insertCountryOrdering(int $countryId = null, array $paymentMethodsArray = null): bool
    {
        if ($paymentMethodsArray === null) {
            $paymentMethods = $this->paymentMethodRepository->findAll();
            $paymentMethodsArray = [];
            foreach ($paymentMethods as $method) {
                $paymentMethodsArray[] = $method->getId();
            }
        }

        $ordering = new BkOrdering();
        $ordering->setCountryId($countryId);
        $ordering->setValue(serialize($paymentMethodsArray));
        $ordering->setCreatedAt(new \DateTime());

        $this->entityManager->persist($ordering);
        $this->entityManager->flush();

        return true;
    }

    public function getPositionByCountryId(int $countryId): ?array
    {
        return $this->orderingRepository->fetchPositions($countryId) ?? $this->orderingRepository->fetchPositions(null);
    }
}
