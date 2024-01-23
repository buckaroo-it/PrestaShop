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

use Buckaroo\PrestaShop\Src\Entity\BkConfiguration;
use Buckaroo\PrestaShop\Src\Entity\BkPaymentMethods;
use Doctrine\ORM\EntityRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PaymentMethodRepository extends EntityRepository implements BkPaymentMethodRepositoryInterface
{
    /**
     * Fetches payment methods from the database.
     *
     * @param int $isPaymentMethod
     *
     * @return array
     */
    private function fetchPaymentMethods(int $isPaymentMethod, int $allMethods): array
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('pm.id, pm.name AS payment_name', 'pm.icon AS payment_icon', 'config.value AS config_value')
            ->from(BkPaymentMethods::class, 'pm');
        if (!$allMethods)
            $qb->where('pm.is_payment_method = :isPaymentMethod')
                ->setParameter('isPaymentMethod', $isPaymentMethod);
        $qb->leftJoin(BkConfiguration::class, 'config', 'WITH', 'pm.id = config.configurable_id');

        return $qb->getQuery()->getArrayResult();
    }

    public function fetchMethodsFromDBWithConfig(int $isPaymentMethod): array
    {
        $results = $this->fetchPaymentMethods($isPaymentMethod, false);

        if (!$results) {
            throw new \Exception('Database error: Could not fetch payment methods with config');
        }

        return $this->formatPaymentMethods($results);
    }

    /**
     * Formats payment methods with configuration.
     *
     * @param array $results
     *
     * @return array
     *
     * @throws Exception
     */
    private function formatPaymentMethods(array $results): array
    {
        $capayableIn3 = \Module::getInstanceByName('buckaroo3')->get('buckaroo.classes.issuers.capayableIn3');

        $payments = [];
        foreach ($results as $result) {
            $payment = [
                'name' => $result['payment_name'],
                'icon' => $result['payment_icon'],
            ];

            if ($result['payment_name'] === 'in3') {
                $payment['icon'] = $capayableIn3->getLogo();
            }

            if (isset($result['config_value']) && ($configArray = json_decode($result['config_value'], true)) !== null) {
                if (json_last_error() === JSON_ERROR_NONE) {
                    $payment = array_merge($payment, $configArray);
                } else {
                    throw new \Exception('JSON decode error: ' . json_last_error_msg());
                }
            }

            $payments[] = $payment;
        }

        return $payments;
    }

    public function getActivePaymentMethods($countryId)
    {
        $results = $this->fetchPaymentMethods(1, true);

        return $this->filterPaymentMethodsByCountry($results, $countryId);
    }

    /**
     * Filters payment methods by country.
     *
     * @param array $results
     * @param int $countryId
     *
     * @return array
     */
    private function filterPaymentMethodsByCountry(array $results, int $countryId): array
    {
        $filteredResults = [];
        foreach ($results as $result) {
            $configValue = json_decode($result['config_value'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }

            if ($this->isCountryInConfig($configValue, $countryId)) {
                $filteredResults[] = $result;
            }
        }

        return $filteredResults;
    }

    /**
     * Checks if a country is in the configuration.
     *
     * @param array|null $configValue
     * @param int $countryId
     *
     * @return bool
     */
    private function isCountryInConfig(?array $configValue, int $countryId): bool
    {
        if (isset($configValue['countries']) && is_array($configValue['countries'])) {
            foreach ($configValue['countries'] as $country) {
                if (isset($country['id']) && $country['id'] == $countryId) {
                    return true;
                }
            }
            return false;
        }

        return true;
    }
}
