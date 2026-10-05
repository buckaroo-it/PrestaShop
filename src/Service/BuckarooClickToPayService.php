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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Resolves the settings the Click to Pay Drop-in UI needs and exchanges the
 * Token API credentials for a short-lived OAuth access token.
 */
class BuckarooClickToPayService
{
    public const METHOD_NAME = 'clicktopay';

    private const AUTH_ENDPOINT = 'https://auth.buckaroo.io/oauth/token';
    private const TOKEN_SCOPE = 'clicktopay:save';
    private const SDK_SCRIPT_URL = 'https://checkout.buckaroo.nl/api/buckaroosdk/script';

    /**
     * Cached token entry, keyed by a hash of the credentials so a credential
     * change invalidates it immediately.
     */
    private const TOKEN_CACHE_KEY = 'BUCKAROO_CLICKTOPAY_TOKEN';

    /**
     * Keeps a safety window so a token handed to the browser never expires
     * halfway through the Drop-in UI flow.
     */
    private const TOKEN_EXPIRY_MARGIN = 60;

    /**
     * A hung auth endpoint would otherwise hold the checkout XHR and a PHP
     * worker for the full default socket timeout.
     */
    private const REQUEST_TIMEOUT = 10;

    /** @var BuckarooConfigService */
    private $buckarooConfigService;

    public function __construct(BuckarooConfigService $buckarooConfigService)
    {
        $this->buckarooConfigService = $buckarooConfigService;
    }

    public function getSdkScriptUrl(): string
    {
        return self::SDK_SCRIPT_URL;
    }

    /**
     * Click to Pay can only render when the merchant filled in the Token API
     * credentials and the merchant GUID.
     */
    public function isConfigured(): bool
    {
        return $this->getClientId() !== ''
            && $this->getClientSecret() !== ''
            && $this->getMerchantIdentifier() !== '';
    }

    /**
     * Settings the Drop-in UI is initialized with in the browser. The client
     * secret deliberately stays server side; the browser receives only an
     * access token minted by the token front controller.
     *
     * @param \Cart $cart
     *
     * @return array<string, mixed>
     */
    public function getCheckoutConfig($cart): array
    {
        return [
            'merchantIdentifier' => $this->getMerchantIdentifier(),
            'targetOrigins' => [$this->getShopOrigin()],
            'country' => $this->getCountryIso($cart),
            'locale' => $this->getLocale(),
            'currency' => $this->getCurrencyIso($cart),
            'totalAmount' => $this->getTotalAmount($cart),
        ];
    }

    /**
     * Exchange the Token API credentials for an access token, reusing a cached
     * one while it is still valid.
     *
     * @return array{access_token: string, expires_in: int}|null null when no token could be obtained
     */
    public function getAccessToken(): ?array
    {
        $clientId = $this->getClientId();
        $clientSecret = $this->getClientSecret();

        if ($clientId === '' || $clientSecret === '') {
            return null;
        }

        $credentialHash = hash('sha256', $clientId . ':' . $clientSecret . ':' . self::TOKEN_SCOPE);

        $cached = $this->loadCachedToken($credentialHash);
        if ($cached !== null) {
            return $cached;
        }

        $token = $this->requestToken($clientId, $clientSecret);
        if ($token === null) {
            return null;
        }

        $expiresIn = max((int) $token['expires_in'] - self::TOKEN_EXPIRY_MARGIN, 0);
        $this->saveCachedToken($credentialHash, $token['access_token'], $expiresIn);

        return [
            'access_token' => $token['access_token'],
            'expires_in' => $expiresIn,
        ];
    }

    /**
     * @return array{access_token: string, expires_in: int}|null
     */
    private function requestToken(string $clientId, string $clientSecret): ?array
    {
        $curl = curl_init(self::AUTH_ENDPOINT);
        if ($curl === false) {
            return null;
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::REQUEST_TIMEOUT,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $clientId . ':' . $clientSecret,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'client_credentials',
                'scope' => self::TOKEN_SCOPE,
            ]),
        ]);

        $body = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if (!is_string($body) || $body === '') {
            $this->logError('Click to Pay token request failed: ' . ($error !== '' ? $error : 'empty response'));

            return null;
        }

        $data = json_decode($body, true);
        if (!is_array($data) || empty($data['access_token'])) {
            $this->logError('Click to Pay token request returned an unexpected response.');

            return null;
        }

        return [
            'access_token' => (string) $data['access_token'],
            'expires_in' => (int) ($data['expires_in'] ?? 0),
        ];
    }

    /**
     * @return array{access_token: string, expires_in: int}|null
     */
    private function loadCachedToken(string $credentialHash): ?array
    {
        $cached = json_decode((string) \Configuration::getGlobalValue(self::TOKEN_CACHE_KEY), true);

        if (!is_array($cached)
            || ($cached['hash'] ?? '') !== $credentialHash
            || empty($cached['access_token'])
        ) {
            return null;
        }

        $remaining = (int) ($cached['expires_at'] ?? 0) - time();
        if ($remaining <= 0) {
            return null;
        }

        return [
            'access_token' => (string) $cached['access_token'],
            'expires_in' => $remaining,
        ];
    }

    private function saveCachedToken(string $credentialHash, string $accessToken, int $expiresIn): void
    {
        if ($expiresIn <= 0) {
            return;
        }

        \Configuration::updateGlobalValue(self::TOKEN_CACHE_KEY, json_encode([
            'hash' => $credentialHash,
            'access_token' => $accessToken,
            'expires_at' => time() + $expiresIn,
        ]));
    }

    private function getClientId(): string
    {
        return $this->getConfigString('client_id');
    }

    private function getClientSecret(): string
    {
        return $this->getConfigString('client_secret');
    }

    private function getMerchantIdentifier(): string
    {
        return $this->getConfigString('merchant_identifier');
    }

    private function getConfigString(string $key): string
    {
        return trim((string) $this->buckarooConfigService->getConfigValue(self::METHOD_NAME, $key));
    }

    /**
     * Scheme + host (+ port) of the shop, matching the target origins that must
     * be whitelisted for Click to Pay in Buckaroo Plaza.
     */
    private function getShopOrigin(): string
    {
        $baseUrl = \Context::getContext()->shop->getBaseURL(true);
        $parts = parse_url((string) $baseUrl);

        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return rtrim((string) $baseUrl, '/');
        }

        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return $origin;
    }

    /**
     * @param \Cart $cart
     */
    private function getCountryIso($cart): string
    {
        $addressId = (int) ($cart->id_address_invoice ?: $cart->id_address_delivery);
        if ($addressId > 0) {
            $address = new \Address($addressId);
            if (\Validate::isLoadedObject($address)) {
                $iso = \Country::getIsoById((int) $address->id_country);
                if (!empty($iso)) {
                    return \Tools::strtoupper($iso);
                }
            }
        }

        $contextCountry = \Context::getContext()->country;

        return $contextCountry ? \Tools::strtoupper((string) $contextCountry->iso_code) : 'NL';
    }

    /**
     * Click to Pay expects an ISO 639-1 language and ISO 3166 country pair,
     * e.g. nl_NL. PrestaShop stores that as a dash-separated locale.
     */
    private function getLocale(): string
    {
        $language = \Context::getContext()->language;

        if ($language && !empty($language->locale)) {
            return str_replace('-', '_', (string) $language->locale);
        }

        $isoCode = $language ? (string) $language->iso_code : 'nl';

        return $isoCode . '_' . \Tools::strtoupper($isoCode);
    }

    /**
     * @param \Cart $cart
     */
    private function getCurrencyIso($cart): string
    {
        $currency = new \Currency((int) $cart->id_currency);

        return \Validate::isLoadedObject($currency) ? \Tools::strtoupper((string) $currency->iso_code) : '';
    }

    /**
     * Amount shown in the Drop-in UI. It has to match what we charge, so any
     * configured payment fee for Click to Pay is included.
     *
     * @param \Cart $cart
     */
    private function getTotalAmount($cart): string
    {
        $total = (float) $cart->getOrderTotal(true, \Cart::BOTH);

        $module = \Module::getInstanceByName('buckaroo3');
        if ($module) {
            $fee = $module->getBuckarooFee(self::METHOD_NAME, $total);
            if (is_array($fee) && isset($fee['buckaroo_fee_tax_incl'])) {
                $total += (float) $fee['buckaroo_fee_tax_incl'];
            }
        }

        return number_format($total, 2, '.', '');
    }

    private function logError(string $message): void
    {
        if (class_exists('\PrestaShopLogger')) {
            \PrestaShopLogger::addLog('Buckaroo: ' . $message, 3);
        }
    }
}
