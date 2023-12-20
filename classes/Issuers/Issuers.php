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

namespace Buckaroo\PrestaShop\Classes\Issuers;

use Buckaroo\BuckarooClient;
use Buckaroo\PrestaShop\Classes\Config;

abstract class Issuers
{
    protected const CACHE_ISSUERS_DATE_KEY = 'BUCKAROO_ISSUERS_CACHE_DATE';
    protected const CACHE_ISSUERS_KEY = 'BUCKAROO_ISSUERS_CACHE';
    protected const ISSUERS_IMAGES = [
        'ABNANL2A' => 'ABNAMRO.svg',
        'ASNBNL21' => 'ASNBank.svg',
        'INGBNL2A' => 'ING.svg',
        'RABONL2U' => 'Rabobank.svg',
        'SNSBNL2A' => 'SNS.svg',
        'RBRBNL21' => 'RegioBank.svg',
        'TRIONL2U' => 'Triodos.svg',
        'FVLBNL22' => 'vanLanschot.svg',
        'KNABNL2H' => 'KNAB.svg',
        'BUNQNL2A' => 'Bunq.svg',
        'REVOLT21' => 'Revolut.svg',
        'NNBANL2G' => 'NN.svg',
        'BITSNL2A' => 'YourSafe.svg',
        'NTSBDEB1' => 'N26.svg',
    ];

    public function __construct($method)
    {
        $this->method = $method;
    }

    /**
     * @throws \Exception
     */
    public function get(): array
    {
        $issuers = $this->getCacheIssuers();
        $cacheDate = $this->getCacheDate();

        if (!is_array($issuers) || $cacheDate !== (new \DateTime())->format('Y-m-d')) {
            return $this->updateCacheIssuers($issuers);
        }

        return $issuers;
    }

    /**
     * Add logos to the issuers
     *
     * @param array $issuers
     *
     * @return array
     */
    private function formatIssuers($issuers): array
    {
        return array_reduce($issuers, function ($result, $issuer) {
            if (isset($issuer['id'], self::ISSUERS_IMAGES[$issuer['id']])) {
                $result[$issuer['id']] = [
                    'name' => $issuer['name'],
                    'logo' => self::ISSUERS_IMAGES[$issuer['id']],
                ];
            }

            return $result;
        }, []);
    }

    /**
     * update cache with issues from payment engine
     *
     * @return array
     *
     * @throws \Exception
     */
    private function updateCacheIssuers($issuers): array
    {
        $retrievedIssuers = $this->formatIssuers(
            $this->requestIssuers()
        );
        if (count($retrievedIssuers)) {
            $this->saveIssuers($retrievedIssuers);

            return $retrievedIssuers;
        }

        return $issuers ?? [];
    }

    /**
     * @throws \Exception
     */
    private function requestIssuers()
    {
        if (\Configuration::get('BUCKAROO_MERCHANT_KEY') || \Configuration::get('BUCKAROO_SECRET_KEY')) {
            $buckaroo = new BuckarooClient(
                \Configuration::get('BUCKAROO_MERCHANT_KEY'),
                \Configuration::get('BUCKAROO_SECRET_KEY'),
                Config::getMode($this->method)
            );

            return $buckaroo->method($this->method)->issuers();
        } else {
            throw new \Exception('Buckaroo master settings not found.');
        }
    }

    /**
     * Save issuers to cache with new date
     *
     * @param array $issuers
     *
     * @return void
     */
    private function saveIssuers($issuers)
    {
        if (!is_array($issuers)) {
            return;
        }
        \Configuration::updateValue($this::CACHE_ISSUERS_KEY, json_encode($issuers));
        \Configuration::updateValue($this::CACHE_ISSUERS_DATE_KEY, (new \DateTime())->format('Y-m-d'));
    }

    /**
     * Get cached issuers
     *
     * @return array|null
     */
    private function getCacheIssuers()
    {
        $issuersString = \Configuration::get(self::CACHE_ISSUERS_KEY);
        if (!is_string($issuersString)) {
            return null;
        }

        return json_decode($issuersString, true);
    }

    /**
     * Get cached date
     *
     * @return bool|string
     */
    private function getCacheDate()
    {
        return \Configuration::get(self::CACHE_ISSUERS_DATE_KEY);
    }
}
