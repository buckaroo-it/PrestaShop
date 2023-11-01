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

namespace Buckaroo\PrestaShop\Src\Repository;

use Buckaroo\PrestaShop\Src\Entity\BkCountries;
use Buckaroo\PrestaShop\Src\Entity\BkOrdering;
use Buckaroo\PrestaShop\Src\Entity\BkPaymentMethods;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;

class OrderingRepository extends EntityRepository
{
    public function findOneByCountryId($country_id)
    {
        return $this->findOneBy(['country_id' => $country_id]);
    }

    /**
     * @throws NonUniqueResultException
     */
    public function findOneByCountryIsoCode(?string $isoCode2)
    {
        $qb = $this->_em->createQueryBuilder()->select('bo')
            ->from(BkOrdering::class, 'bo');
        if ($isoCode2 !== null) {
            $qb->join(BkCountries::class, 'c', 'WITH', 'bo.country_id = c.country_id')
                ->where('c.iso_code_2 = :isoCode2')
                ->setParameter('isoCode2', $isoCode2);
        } else {
            $qb->where('bo.country_id IS NULL');
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function updateOrdering($value, $countryId = null)
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Handle JSON decode error
                return false;
            }
        }

        $idArray = array_column($value, 'id');
        $value = json_encode($idArray);

        if ($countryId !== null) {
            return $this->updateOrderingForSpecificCountry($value, $countryId);
        }

        return $this->updateOrderingValue($value, null);
    }

    private function updateOrderingForSpecificCountry($value, $countryId)
    {
        $countryRepo = $this->_em->getRepository(BkCountries::class);
        $country = $countryRepo->findOneBy(['country_id' => $countryId]);

        if (!$country) {
            return false;
        }

        return $this->updateOrderingValue($value, $countryId);
    }

    private function updateOrderingValue($value, $countryId)
    {
        $orderingRepo = $this->_em->getRepository(BkOrdering::class);
        $ordering = $orderingRepo->findOneBy(['country_id' => $countryId]);

        if (!$ordering) {
            return false;
        }

        $ordering->setValue($value);
        $this->_em->persist($ordering);
        $this->_em->flush();

        return true;
    }

    public function getOrdering(?string $isoCode2)
    {
        $ordering = $this->findOneByCountryIsoCode($isoCode2);

        if (empty($ordering)) {
            return null;
        }

        $result = [
            'id' => $ordering->getId(),
            'country_id' => $ordering->getCountryId(),
            'value' => [],
            'createdAt' => $ordering->getCreatedAt(),
            'updatedAt' => $ordering->getUpdatedAt(),
            'status' => true,
        ];

        $paymentMethodIds = is_string($ordering->getValue())
            ? json_decode($ordering->getValue(), true)
            : $ordering->getValue();

        $paymentMethodRepo = $this->_em->getRepository(BkPaymentMethods::class);

        foreach ($paymentMethodIds as $id) {
            $paymentMethodData = $paymentMethodRepo->findOneBy(['id' => $id]);

            if ($paymentMethodData) {
                $result['value'][] = [
                    'id' => $paymentMethodData->getId(),
                    'name' => $paymentMethodData->getName(),
                    'label' => $paymentMethodData->getLabel(),
                    'icon' => $paymentMethodData->getIcon(),
                ];
            }
        }

        return $result;
    }

    public function fetchPositions($isoCode2)
    {
        $ordering = $this->findOneByCountryIsoCode($isoCode2);

        if (empty($ordering)) {
            return null;
        }

        $positionsArray = json_decode($ordering->getValue(), true);
        $output = [];
        $paymentMethodRepo = $this->_em->getRepository(BkPaymentMethods::class);

        foreach ($positionsArray as $id) {
            $paymentMethodData = $paymentMethodRepo->findOneBy(['id' => $id]);
            if ($paymentMethodData) {
                $output[] = $paymentMethodData->getName();
            }
        }

        return $output;
    }

    /**
     * Creates a new BkOrdering with given data.
     *
     * @param int   $countryId
     * @param array $paymentMethodIds
     *
     * @return BkOrdering
     */
    public function createNewOrdering(int $countryId, array $paymentMethodIds): BkOrdering
    {
        $ordering = new BkOrdering();
        $ordering->setCountryId($countryId);
        $ordering->setValue(json_encode($paymentMethodIds));
        $ordering->setCreatedAt(new \DateTime());
        $ordering->setUpdatedAt(new \DateTime());

        $this->_em->persist($ordering);
        $this->_em->flush();

        return $ordering;
    }

    /**
     * Add a payment method ID to the ordering if it doesn't exist.
     *
     * @param BkOrdering $ordering
     * @param int        $paymentMethodId
     *
     * @return bool indicates whether the ordering was updated or not
     */
    public function addPaymentMethodToOrdering(BkOrdering $ordering, int $paymentMethodId): bool
    {
        $paymentMethodIds = json_decode($ordering->getValue(), true);

        // Check if the paymentMethodId is already in the ordering for the country
        if (!in_array($paymentMethodId, $paymentMethodIds)) {
            // Add paymentMethodId to the ordering for the country
            $paymentMethodIds[] = $paymentMethodId;
            $ordering->setValue(json_encode($paymentMethodIds));
            $ordering->setCreatedAt(new \DateTime());
            $ordering->setUpdatedAt(new \DateTime());

            $this->_em->persist($ordering);
            $this->_em->flush();

            return true;
        }

        return false;
    }

    /**
     * Remove the given payment method ID from all orderings if it's not in the new country IDs.
     *
     * @param int   $paymentMethodId
     * @param array $newCountryIds
     */
    public function removePaymentMethodFromOrderings(int $paymentMethodId, array $newCountryIds): void
    {
        $allOrderings = $this->findAll();

        foreach ($allOrderings as $ordering) {
            if ($ordering->getCountryId() === null) {
                continue;
            }

            $paymentMethodIds = json_decode($ordering->getValue(), true);

            if (in_array($paymentMethodId, $paymentMethodIds) && !in_array($ordering->getCountryId(), $newCountryIds)) {
                $key = array_search($paymentMethodId, $paymentMethodIds);

                if ($key !== false) {
                    unset($paymentMethodIds[$key]);

                    if (empty($paymentMethodIds)) {
                        $this->_em->remove($ordering);
                    } else {
                        $ordering->setValue(json_encode(array_values($paymentMethodIds)));
                        $this->_em->persist($ordering);
                    }
                }
            }
        }

        $this->_em->flush();
    }
}
