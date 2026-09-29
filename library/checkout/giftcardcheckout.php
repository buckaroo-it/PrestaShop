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
include_once _PS_MODULE_DIR_ . 'buckaroo3/library/checkout/checkout.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

class GiftCardCheckout extends Checkout
{
    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    final public function setCheckout()
    {
        parent::setCheckout();

        $this->customVars = [
            'servicesSelectableByClient' => $this->getServicesSelectableByClient(),
            'continueOnIncomplete'       => '1',
        ];

        if (!empty($this->customer->email) && Validate::isEmail($this->customer->email)) {
            $this->customVars['additionalParameters'] = [
                'email' => $this->customer->email,
            ];
        }
    }

    public function startPayment()
    {
        $this->payment_response = $this->payment_request->pay($this->customVars);
    }

    public function isRedirectRequired()
    {
        return true;
    }

    public function isVerifyRequired()
    {
        return false;
    }

    /**
     * Giftcard brands plus the payment methods selected for the hosted page.
     */
    private function getServicesSelectableByClient(): string
    {
        $services = array_merge(
            $this->splitServiceList($this->getAllowedGiftcardServices()),
            $this->getAllowedRedirectPaymentServices()
        );

        return implode(',', array_values(array_unique($services)));
    }

    /**
     * Payment methods chosen under Giftcards > Allowed payment methods.
     * Cards are sent as the selected card brands, which is what the hosted page expects.
     *
     * @return string[]
     */
    private function getAllowedRedirectPaymentServices(): array
    {
        if (!$this->buckarooConfigService) {
            return [];
        }

        try {
            $config = $this->buckarooConfigService->getConfigArrayForMethod('giftcard');
        } catch (\Throwable $e) {
            return [];
        }

        if (!is_array($config)) {
            return [];
        }

        $services = [];
        foreach ($this->normalizeAllowedPayments($config['allowed_payments'] ?? []) as $method) {
            if ($method === 'creditcard') {
                $services = array_merge($services, $this->getActiveCreditCardServices());
                continue;
            }

            $services[] = $method;
        }

        return $services;
    }

    /**
     * @param mixed $allowed
     *
     * @return string[]
     */
    private function normalizeAllowedPayments($allowed): array
    {
        if (is_string($allowed)) {
            $allowed = explode(',', $allowed);
        }

        if (!is_array($allowed)) {
            return [];
        }

        $methods = [];
        foreach ($allowed as $method) {
            if (is_array($method)) {
                $method = $method['name'] ?? $method['code'] ?? '';
            }

            $method = strtolower(trim((string) $method));
            if ($method === '' || in_array($method, ['giftcard', 'idin'], true)) {
                continue;
            }

            $methods[] = $method;
        }

        return array_values(array_unique($methods));
    }

    /**
     * @return string[]
     */
    private function getActiveCreditCardServices(): array
    {
        if (!$this->buckarooConfigService) {
            return [];
        }

        try {
            $cards = $this->buckarooConfigService->getActiveCreditCards();
        } catch (\Throwable $e) {
            return [];
        }

        if (!is_array($cards)) {
            return [];
        }

        $codes = [];
        foreach (array_keys($cards) as $code) {
            $code = strtolower(trim((string) $code));
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    /**
     * @return string[]
     */
    private function splitServiceList(string $services): array
    {
        $codes = [];
        foreach (explode(',', $services) as $service) {
            $service = strtolower(trim($service));
            if ($service !== '') {
                $codes[] = $service;
            }
        }

        return $codes;
    }

    /**
     * Prefer the legacy PS config key; fall back to Vue activeGiftcards JSON.
     */
    private function getAllowedGiftcardServices(): string
    {
        $allowed = (string) Configuration::get('BUCKAROO_GIFTCARD_ALLOWED_CARDS');
        if ($allowed !== '') {
            return $allowed;
        }

        try {
            $module = \Module::getInstanceByName('buckaroo3');
            if (!$module || !method_exists($module, 'get')) {
                return '';
            }
            $configService = $module->get('buckaroo.config.api.config.service');
            if (!$configService || !method_exists($configService, 'getConfigArrayForMethod')) {
                return '';
            }
            $config = $configService->getConfigArrayForMethod('giftcard');
            $codes = [];
            foreach (['giftcards', 'customGiftcards'] as $key) {
                if (empty($config['activeGiftcards'][$key]) || !is_array($config['activeGiftcards'][$key])) {
                    continue;
                }
                foreach ($config['activeGiftcards'][$key] as $card) {
                    $code = $card['code'] ?? $card['service_code'] ?? null;
                    if (!empty($code)) {
                        $codes[] = (string) $code;
                    }
                }
            }

            return implode(',', array_unique($codes));
        } catch (\Throwable $e) {
            return '';
        }
    }

    protected function initialize()
    {
        $this->payment_request = PaymentRequestFactory::create(PaymentRequestFactory::REQUEST_TYPE_GIFTCARD);
    }
}
