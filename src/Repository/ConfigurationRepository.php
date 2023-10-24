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

use Buckaroo\PrestaShop\Src\Entity\BkPaymentMethods;
use Doctrine\ORM\EntityRepository;

class ConfigurationRepository extends EntityRepository implements BkConfigurationRepositoryInterface
{
    private function getPaymentMethodByName(string $name): ?BkPaymentMethods
    {
        return $this->_em->getRepository(BkPaymentMethods::class)->findOneBy(['name' => $name]);
    }

    public function getConfigArray(int $paymentId): array
    {
        $configuration = $this->findOneBy(['configurable_id' => $paymentId]);
        if (!$configuration) {
            throw new \Exception("Configuration not found for payment id {$paymentId}");
        }

        $configArray = json_decode($configuration->getValue(), true);
        if ($configArray === null) {
            throw new \Exception('JSON decode error: ' . json_last_error_msg());
        }

        return $configArray;
    }

    public function updateConfig(int $paymentId, array $config): bool
    {
        $configuration = $this->findOneBy(['configurable_id' => $paymentId]);
        if (!$configuration) {
            throw new \Exception("Configuration not found for payment id {$paymentId}");
        }

        $configuration->setValue(json_encode($config));
        $this->_em->persist($configuration);
        $this->_em->flush();

        return true;
    }

    /**
     * @throws \Exception
     */
    public function getActiveCreditCards(): array
    {
        $paymentMethod = $this->getPaymentMethodByName('creditcard');

        $configArray = $this->getConfigArray($paymentMethod->getId());
        $result = $configArray['activeCreditcards'] ?? [];

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
