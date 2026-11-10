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

use Buckaroo\PrestaShop\Src\ApplePay\DomainAssociation;
use Buckaroo\PrestaShop\Src\ApplePay\ExpressSettings as ApplePayExpressSettings;
use Buckaroo\PrestaShop\Src\Config\Config;
use Buckaroo\PrestaShop\Src\Entity\BkConfiguration;
use Buckaroo\PrestaShop\Src\Entity\BkPaymentMethods;
use Buckaroo\PrestaShop\Src\Express\AbstractExpressSettings;
use Buckaroo\PrestaShop\Src\GooglePay\ExpressSettings as GooglePayExpressSettings;
use Buckaroo\PrestaShop\Src\PayPal\ExpressSettings as PayPalExpressSettings;
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

    public function __construct(EntityManager $entityManager)
    {
        $this->paymentMethodRepository = $entityManager->getRepository(BkPaymentMethods::class);
        $this->configurationRepository = $entityManager->getRepository(BkConfiguration::class);
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

        if ($name === ApplePayExpressSettings::METHOD_NAME) {
            $mergedConfig = ApplePayExpressSettings::sanitize($mergedConfig);
            $this->installApplePayDomainAssociation();
            $this->registerExpressHooks();
        }

        if ($name === GooglePayExpressSettings::METHOD_NAME) {
            $mergedConfig = GooglePayExpressSettings::sanitize($mergedConfig);
            $this->registerExpressHooks();
        }

        if ($name === PayPalExpressSettings::METHOD_NAME) {
            $mergedConfig = PayPalExpressSettings::sanitize($mergedConfig);
            $this->registerExpressHooks();
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

    /**
     * Apple verifies the shop domain for Apple Pay with Buckaroo's association
     * file in /.well-known/, so it is placed (when missing) whenever Apple Pay is saved.
     */
    private function installApplePayDomainAssociation(): void
    {
        $status = DomainAssociation::forModule(_PS_MODULE_DIR_ . 'buckaroo3')->install();

        if ($status === DomainAssociation::STATUS_FAILED || $status === DomainAssociation::STATUS_OTHER_FILE) {
            \PrestaShopLogger::addLog(
                'Buckaroo: Apple Pay domain association file not placed in /.well-known/ (' . $status . ')',
                2
            );
        }
    }

    /**
     * The upgrade script registers the express button hooks, but it does not run
     * again for a shop that already had this module version (e.g. a development
     * build). Registering them when Apple Pay or Google Pay is saved or enabled
     * repairs that;
     * hooks that are already registered are left alone.
     */
    private function registerExpressHooks(): void
    {
        $module = \Module::getInstanceByName('buckaroo3');
        if (!$module instanceof \Module) {
            return;
        }

        foreach (AbstractExpressSettings::HOOKS as $hook) {
            if ($module->isRegisteredInHook($hook)) {
                continue;
            }
            if (!$module->registerHook($hook)) {
                \PrestaShopLogger::addLog('Buckaroo: express button hook ' . $hook . ' could not be registered', 2);
            }
        }
    }

    public function updatePaymentMethodMode(string $name, string $mode): bool
    {
        $paymentMethod = $this->paymentMethodRepository->findOneBy(['name' => $name]);

        if (!$paymentMethod) {
            return false;
        }

        $configArray = $this->configurationRepository->getConfigArray($paymentMethod->getId());
        $configArray['mode'] = $mode;

        if (in_array($name, [ApplePayExpressSettings::METHOD_NAME, GooglePayExpressSettings::METHOD_NAME, PayPalExpressSettings::METHOD_NAME], true) && Config::isPaymentModeEnabled($mode)) {
            $this->registerExpressHooks();
        }

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

    /**
     * Giftcard service codes the merchant enabled: the legacy PS config key,
     * falling back to the Vue admin selection.
     *
     * @return string[]
     */
    public function getAllowedGiftcardCodes(): array
    {
        $codes = self::splitCodes((string) \Configuration::get('BUCKAROO_GIFTCARD_ALLOWED_CARDS'));
        if (!empty($codes)) {
            return $codes;
        }

        $active = $this->getConfigArrayForMethod('giftcard')['activeGiftcards'] ?? [];
        foreach (['giftcards', 'customGiftcards'] as $key) {
            if (empty($active[$key]) || !is_array($active[$key])) {
                continue;
            }
            foreach ($active[$key] as $card) {
                $code = is_array($card) ? trim((string) ($card['code'] ?? $card['service_code'] ?? '')) : '';
                if ($code !== '') {
                    $codes[] = $code;
                }
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * @return string[]
     */
    private static function splitCodes(string $codes): array
    {
        $result = [];
        foreach (explode(',', $codes) as $code) {
            $code = trim($code);
            if ($code !== '') {
                $result[] = $code;
            }
        }

        return array_values(array_unique($result));
    }
}
