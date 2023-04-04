<?php

use Buckaroo\BuckarooClient;

require_once _PS_MODULE_DIR_ . 'buckaroo3/config.php';
require_once _PS_MODULE_DIR_ . 'buckaroo3/vendor/autoload.php';

class IssuersIdeal
{
    protected const CACHE_ISSUERS_KEY = 'BUCKAROO_IDEAL_ISSUERS_CACHE';
    protected const CACHE_ISSUERS_DATE_KEY = 'BUCKAROO_IDEAL_ISSUERS_CACHE_DATE';
    
    protected const ISSUERS_IMAGES = [
        'ABNANL2A' => 'ABNAMRO.png',
        'ASNBNL21' => 'ASNBANK.png',
        'INGBNL2A' => 'ING.png',
        'RABONL2U' => 'Rabobank.png',
        'SNSBNL2A' => 'SNS.png',
        'RBRBNL21' => 'Regiobank.png',
        'TRIONL2U' => 'Triodos.png',
        'FVLBNL22' => 'vanLanschot.png',
        'KNABNL2H' => 'KNAB.png',
        'BUNQNL2A' => 'Bunq.png',
        'REVOLT21' => 'Revolut.png',
        'BITSNL2A' =>  'YourSafe.png',
    ];

    public function get()
    {
        $issuers = $this->getCacheIssuers();
        $cacheDate = $this->getCacheDate();

        if(!is_array($issuers) || $cacheDate !== (new DateTime())->format('Y-m-d')) {
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
    private function addLogos($issuers)
    {
        return array_map(
            function($issuer) {
                $logo = null;
                if(
                    isset($issuer['id']) && 
                    isset(self::ISSUERS_IMAGES[$issuer['id']])
                ) {
                    $logo = self::ISSUERS_IMAGES[$issuer['id']];
                }
                $issuer['logo'] = $logo;
                return $issuer;
            },
            $issuers
        );
    }

    /**
     * update cache with issues from payment engine
     *
     * @return array
     */
    private function updateCacheIssuers($issuers)
    {
        $retrievedIssuers = $this->addLogos(
            $this->requestIssuers()
        );

        if(count($retrievedIssuers)) {
            $this->saveIssuers($retrievedIssuers);
            return $retrievedIssuers;
        }
        return $issuers;
    }

    private function requestIssuers()
    {
        $buckaroo = new BuckarooClient(
            Configuration::get('BUCKAROO_MERCHANT_KEY'),
            Configuration::get('BUCKAROO_SECRET_KEY'),
            Config::getMode("ideal")
        );
        return $buckaroo->method('ideal')->issuers();
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
        if(!is_array($issuers)) {
            return;
        }
        Configuration::updateValue(self::CACHE_ISSUERS_KEY, json_encode($issuers));
        Configuration::updateValue(self::CACHE_ISSUERS_DATE_KEY, (new DateTime())->format('Y-m-d'));
    }

    /**
     * Get cached issuers
     *
     * @return null|array
     */
    private function getCacheIssuers()
    {
        $issuersString = Configuration::get(self::CACHE_ISSUERS_KEY);
        if(!is_string($issuersString)) {
           return;
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
        return Configuration::get(self::CACHE_ISSUERS_DATE_KEY);
    }
}