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

use Buckaroo\PrestaShop\Src\Context\ContextAwareTrait;

if (!defined('_PS_VERSION_')) {
    exit;
}

class CountryRepository
{
    use ContextAwareTrait;

    /**
     * @param object|null $legacyContext PrestaShop context, or the prestashop.adapter.legacy.context service
     */
    public function __construct($legacyContext = null)
    {
        $this->setContext($legacyContext);
    }

    private function processCountries($countries)
    {
        $result = [];

        foreach ($countries as $country) {
            $result[] = [
                'id' => $country['id_country'],
                'name' => $country['name'],
                'iso_code_2' => $country['iso_code'],
                'iso_code_3' => \Country::getIsoById($country['id_country']),
                'call_prefix' => $country['call_prefix'],
                'icon' => \Tools::strtolower($country['iso_code']) . '.jpg',
            ];
        }

        return $result;
    }

    public function getCountries(): array
    {
        $rawCountries = \Country::getCountries($this->getLanguageId(), true);

        return $this->processCountries($rawCountries);
    }

    /**
     * Language of the current context; falls back to the shop default language
     * when the repository is used before the context language is initialised.
     */
    public function getLanguageId(): int
    {
        $context = $this->getContext();
        if ($context && isset($context->language) && is_object($context->language) && !empty($context->language->id)) {
            return (int) $context->language->id;
        }

        return (int) \Configuration::get('PS_LANG_DEFAULT');
    }

    public function getCountryByIsoCode2($isoCode2)
    {
        $countries = $this->getCountries();
        $country = array_filter($countries, function ($c) use ($isoCode2) {
            return $c['iso_code_2'] == $isoCode2;
        });

        if (empty($country)) {
            return null;
        }

        return reset($country);
    }
}
