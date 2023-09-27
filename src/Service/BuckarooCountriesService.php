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
namespace Buckaroo\Src\Service;

use Buckaroo\Src\Entity\BkCountries;
use Doctrine\ORM\EntityManager;

class BuckarooCountriesService
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    public function __construct(
        EntityManager $entityManager,
    ) {
        $this->entityManager = $entityManager;
    }

    public function createCountries()
    {
        $context = \Context::getContext();
        $langId = $context->language->id;
        $rawCountries = \Country::getCountries($langId, true);

        $processedCountries = $this->processCountries($rawCountries);

        foreach ($processedCountries as $countryData) {
            $countries = new BkCountries();
            $countries->setName($countryData['name']);
            $countries->setIsoCode2($countryData['iso_code_2']);
            $countries->setIsoCode3($countryData['iso_code_3']);
            $countries->setCallPrefix($countryData['call_prefix']);
            $countries->setIcon($countryData['icon']);
            $countries->setCreatedAt(new \DateTime());

            $this->entityManager->persist($countries);
        }

        $this->entityManager->flush();

        return $countries;
    }

    private function processCountries($countries)
    {
        $result = [];

        foreach ($countries as $country) {
            $result[] = [
                'id' => $country['id_country'],
                'name' => strtolower($country['name']),
                'iso_code_2' => $country['iso_code'],
                'iso_code_3' => \Country::getIsoById($country['id_country']),
                'call_prefix' => $country['call_prefix'],
                'icon' => \Tools::strtolower($country['iso_code']) . '.jpg',
            ];
        }

        return $result;
    }
}
