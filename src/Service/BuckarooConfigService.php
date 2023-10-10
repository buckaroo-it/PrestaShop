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

use Buckaroo\PrestaShop\Src\Entity\BkConfiguration;
use Buckaroo\PrestaShop\Src\Entity\BkCountries;
use Buckaroo\PrestaShop\Src\Entity\BkOrdering;
use Buckaroo\PrestaShop\Src\Entity\BkPaymentMethods;
use Doctrine\ORM\EntityManager;

class BuckarooConfigService
{
    protected $logger;
    private $paymentMethodRepository;

    private $configurationRepository;
    private $countryRepository;
    private $orderingRepository;

    private $entityManager;

    public function __construct(EntityManager $entityManager, $logger)
    {
        $this->entityManager = $entityManager;
        $this->configurationRepository = $entityManager->getRepository(BkConfiguration::class);
        $this->paymentMethodRepository = $entityManager->getRepository(BkPaymentMethods::class);
        $this->countryRepository = $entityManager->getRepository(BkCountries::class);
        $this->orderingRepository = $entityManager->getRepository(BkOrdering::class);
        $this->logger = $logger;
    }

    public function getConfigArrayForMethod($method)
    {
        $paymentMethod = $this->paymentMethodRepository->findOneByName($method);

        if (!$paymentMethod) {
            $this->logger->logError('Payment method not found: ' . $method);

            return null;
        }

        return $this->configurationRepository->getConfigArray($paymentMethod->getId());
    }

    public function getSpecificValueFromConfig($method, $key)
    {
        $configArray = $this->getConfigArrayForMethod($method);

        return isset($configArray[$key]) ? $configArray[$key] : null;
    }

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
                    $ordering = new BkOrdering();
                    $ordering->setCountryId($countryId);
                    $ordering->setValue(json_encode([$paymentMethodId]));
                    $ordering->setCreatedAt(new \DateTime());
                    $this->entityManager->persist($ordering);
                } else {
                    $paymentMethodIds = json_decode($ordering->getValue(), true);

                    // Check if the paymentMethodId is already in the ordering for the country
                    if (!in_array($paymentMethodId, $paymentMethodIds)) {
                        // Add paymentMethodId to the ordering for the country
                        $paymentMethodIds[] = $paymentMethodId;
                        $ordering->setValue(json_encode($paymentMethodIds));
                        $this->entityManager->persist($ordering);
                    }
                }
            }

            // Fetch all orderings
            $allOrderings = $this->orderingRepository->findAll();
            foreach ($allOrderings as $ordering) {
                if ($ordering->getCountryId() === null) {
                    continue;
                }
                $paymentMethodIds = json_decode($ordering->getValue(), true);
                if (in_array($paymentMethodId, $paymentMethodIds) && !in_array($ordering->getCountryId(), $newCountryIds)) {
                    // Remove the paymentMethodId from the ordering as it's not in the new data
                    $key = array_search($paymentMethodId, $paymentMethodIds);
                    if ($key !== false) {
                        unset($paymentMethodIds[$key]);
                        $ordering->setValue(json_encode(array_values($paymentMethodIds)));  // reindex array
                        $this->entityManager->persist($ordering);
                    }
                }
            }

            $this->entityManager->flush();
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

    public function getPaymentMethodsFromDBWithConfig()
    {
        return $this->paymentMethodRepository->getPaymentMethodsFromDBWithConfig();
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
