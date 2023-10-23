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

use Buckaroo\PrestaShop\Src\Repository\BkConfigurationRepositoryInterface;
use Buckaroo\PrestaShop\Src\Repository\BkOrderingRepositoryInterface;
use Buckaroo\PrestaShop\Src\Repository\BkPaymentMethodRepositoryInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;

class BuckarooConfigService
{
    private BkPaymentMethodRepositoryInterface $paymentMethodRepository;
    private BkConfigurationRepositoryInterface $configurationRepository;
    private BkOrderingRepositoryInterface $orderingRepository;

    public function __construct(
        BkPaymentMethodRepositoryInterface $paymentMethodRepository,
        BkOrderingRepositoryInterface $orderingRepository,
        BkConfigurationRepositoryInterface $configurationRepository
    ) {
        $this->configurationRepository = $configurationRepository;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->orderingRepository = $orderingRepository;
    }

    public function getConfigArrayForMethod($method)
    {
        $paymentMethod = $this->paymentMethodRepository->findOneByName($method);

        if (!$paymentMethod) {
            return null;
        }

        return $this->configurationRepository->getConfigArray($paymentMethod->getId());
    }

    public function getSpecificValueFromConfig($method, $key)
    {
        $configArray = $this->getConfigArrayForMethod($method);

        return isset($configArray[$key]) ? $configArray[$key] : null;
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function updatePaymentMethodConfig($name, array $data): bool
    {
        $paymentMethod = $this->paymentMethodRepository->findOneByName($name);

        if (!$paymentMethod) {
            return false;
        }

        $paymentMethodId = $paymentMethod->getId();

        // Existing config
        $configArray = $this->configurationRepository->getConfigArray($paymentMethodId);
        $mergedConfig = array_merge($configArray, $data);

        if (isset($data['countries'])) {
            $newCountryIds = array_column($data['countries'], 'id');

            // Update each country's ordering
            foreach ($newCountryIds as $countryId) {
                $ordering = $this->orderingRepository->findOneByCountryId($countryId);
                if (!$ordering) {
                    // No existing ordering for this country. Create a new one.
                    $this->orderingRepository->createNewOrdering($countryId, [$paymentMethodId]);
                } else {
                    $paymentMethodIds = json_decode($ordering->getValue(), true);

                    // Check if the paymentMethodId is already in the ordering for the country
                    if (!in_array($paymentMethodId, $paymentMethodIds)) {
                        $this->orderingRepository->addPaymentMethodToOrdering($ordering, $paymentMethodId);
                    }
                }
            }

            $this->orderingRepository->removePaymentMethodFromOrderings($paymentMethodId, $newCountryIds);
        }

        return $this->configurationRepository->updateConfig($paymentMethodId, $mergedConfig);
    }

    public function updatePaymentMethodMode(string $name, string $mode): bool
    {
        $paymentMethod = $this->paymentMethodRepository->findOneByName($name);

        if (!$paymentMethod) {
            return false;
        }

        $configArray = $this->configurationRepository->getConfigArray($paymentMethod->getId());
        $configArray['mode'] = $mode;

        return $this->configurationRepository->updateConfig($paymentMethod->getId(), $configArray);
    }

    /**
     * @throws \Exception
     */
    public function getPaymentMethodsFromDBWithConfig()
    {
        return $this->paymentMethodRepository->getPaymentMethodsFromDBWithConfig();
    }

    /**
     * @throws \Exception
     */
    public function getVerificationMethodsFromDBWithConfig()
    {
        return $this->paymentMethodRepository->getVerificationMethodsFromDBWithConfig();
    }

    public function getActiveCreditCards()
    {
        $result = $this->configurationRepository->getActiveCreditCards();

        $issuerArray = [];
        foreach ($result as $card) {
            $issuerArray[strtolower($card['service_code'])] = [
                'name' => $card['name'],
                'logo' => $card['icon'],
            ];
        }

        return $issuerArray;
    }
}
