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

use Buckaroo\PrestaShop\Src\Config\Config;
use Buckaroo\PrestaShop\Src\Entity\BkConfiguration;
use Buckaroo\PrestaShop\Src\Entity\BkOrdering;
use Buckaroo\PrestaShop\Src\Entity\BkPaymentMethods;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;

if (!defined('_PS_VERSION_')) {
    exit;
}

class BuckarooConfigService
{
    private $paymentMethodRepository;
    private $configurationRepository;
    private $orderingRepository;

    public function __construct(EntityManager $entityManager)
    {
        $this->paymentMethodRepository = $entityManager->getRepository(BkPaymentMethods::class);
        $this->configurationRepository = $entityManager->getRepository(BkConfiguration::class);
        $this->orderingRepository = $entityManager->getRepository(BkOrdering::class);
    }

    public function getConfigArrayForMethod($method)
    {
        $paymentMethod = $this->paymentMethodRepository->findOneBy(['name' => $method]);

        if (!$paymentMethod) {
            return null;
        }

        $configArray = $this->configurationRepository->getConfigArray($paymentMethod->getId());
        $configArray['payment_fee_allowed'] = Config::isPaymentFeeAllowed((string) $method);

        return $configArray;
    }

    public function getConfigValue($method, $key)
    {
        $configArray = $this->getConfigArrayForMethod($method);

        return $configArray[$key] ?? null;
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function updatePaymentMethodConfig($name, array $data): bool
    {
        $paymentMethod = $this->paymentMethodRepository->findOneBy(['name' => $name]);

        if (!$paymentMethod) {
            return false;
        }

        $paymentMethodId = $paymentMethod->getId();

        if (!Config::isPaymentFeeAllowed((string) $name)) {
            $data['payment_fee'] = '';
        }

        // Existing config
        $configArray = $this->configurationRepository->getConfigArray($paymentMethodId);
        $mergedConfig = array_merge($configArray, $data);

        if ($name === 'giftcard') {
            $this->syncGiftcardAllowedCards($mergedConfig);
        }

        return $this->configurationRepository->updateConfig($paymentMethodId, $mergedConfig);
    }

    /**
     * Keep the legacy HPP config key in sync with the Vue admin selection so
     * grouped (redirect) giftcard checkout still receives the allowed brands.
     */
    private function syncGiftcardAllowedCards(array $config): void
    {
        $codes = [];
        $active = $config['activeGiftcards'] ?? [];

        foreach (['giftcards', 'customGiftcards'] as $key) {
            if (empty($active[$key]) || !is_array($active[$key])) {
                continue;
            }
            foreach ($active[$key] as $card) {
                if (!is_array($card)) {
                    continue;
                }
                $code = $card['code'] ?? $card['service_code'] ?? null;
                if (!empty($code)) {
                    $codes[] = (string) $code;
                }
            }
        }

        \Configuration::updateValue('BUCKAROO_GIFTCARD_ALLOWED_CARDS', implode(',', array_unique($codes)));
    }

    public function updatePaymentMethodMode(string $name, string $mode): bool
    {
        $paymentMethod = $this->paymentMethodRepository->findOneBy(['name' => $name]);

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
        return $this->paymentMethodRepository->fetchMethodsFromDBWithConfig(1);
    }

    /**
     * @throws \Exception
     */
    public function getVerificationMethodsFromDBWithConfig()
    {
        return $this->paymentMethodRepository->fetchMethodsFromDBWithConfig(0);
    }

    public function getActiveCreditCards()
    {
        return $this->configurationRepository->getActiveCreditCards();
    }
}
