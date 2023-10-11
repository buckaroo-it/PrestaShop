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

class OrderingRepository extends EntityRepository
{
    public function findOneByCountryId($country_id)
    {
        return $this->findOneBy(['country_id' => $country_id]);
    }

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

        return $this->updateOrderingForNullCountry($value);
    }

    private function updateOrderingForSpecificCountry($value, $countryId)
    {
        $countryRepo = $this->_em->getRepository(BkCountries::class);
        $country = $countryRepo->findOneBy(['country_id' => $countryId]);

        if (!$country) {
            return false;
        }

        $orderingRepo = $this->_em->getRepository(BkOrdering::class);
        $ordering = $orderingRepo->findOneBy(['country_id' => $country->getCountryId()]);

        if (!$ordering) {
            return false;
        }

        $ordering->setValue($value);
        $this->_em->persist($ordering);
        $this->_em->flush();

        return true;
    }

    private function updateOrderingForNullCountry($value)
    {
        $orderingRepo = $this->_em->getRepository(BkOrdering::class);
        $ordering = $orderingRepo->findOneBy(['country_id' => null]);

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

        foreach ($paymentMethodIds as $id) {
            $paymentMethodRepo = $this->_em->getRepository(BkPaymentMethods::class);
            $paymentMethodData = $paymentMethodRepo->findOneById($id);

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

    public function fetchPositions($countryId)
    {
        $qb = $this->_em->createQueryBuilder()
            ->select('bo.value')
            ->from(BkOrdering::class, 'bo')
            ->where($countryId !== null ? 'bo.country_id = :countryId' : 'bo.country_id IS NULL');

        if ($countryId !== null) {
            $qb->setParameter('countryId', $countryId);
        }

        $result = $qb->getQuery()->getOneOrNullResult();

        if ($result && isset($result['value'])) {
            $positionsArray = json_decode($result['value'], true);
            $output = [];
            foreach ($positionsArray as $id) {
                $paymentMethodData = $this->_em->getRepository(BkPaymentMethods::class)->find($id);
                if ($paymentMethodData) {
                    $output[] = $paymentMethodData->getName();
                }
            }

            return $output;
        }

        return null;
    }
}
