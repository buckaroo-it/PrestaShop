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

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Tests\Unit\Repository;

use Buckaroo\PrestaShop\Src\Repository\CountryRepository;
use PHPUnit\Framework\TestCase;

final class CountryRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        \Module::$instances = [];
        \Country::$countries = [
            ['id_country' => 1, 'name' => 'Netherlands', 'iso_code' => 'NL', 'call_prefix' => 31],
            ['id_country' => 2, 'name' => 'Germany', 'iso_code' => 'DE', 'call_prefix' => 49],
        ];
        \Country::$isoById = [1 => 'NLD', 2 => 'DEU'];
        \Country::$lastLanguageId = null;
        \Configuration::$values['PS_LANG_DEFAULT'] = 1;
    }

    public function testUsesTheLanguageOfTheInjectedContext(): void
    {
        $context = new \Context();
        $context->language = (object) ['id' => 3];

        $repository = new CountryRepository($context);
        $repository->getCountries();

        self::assertSame(3, $repository->getLanguageId());
        self::assertSame(3, \Country::$lastLanguageId);
    }

    public function testFallsBackToTheDefaultShopLanguageWithoutContext(): void
    {
        $repository = new CountryRepository();
        $repository->getCountries();

        self::assertSame(1, $repository->getLanguageId());
        self::assertSame(1, \Country::$lastLanguageId);
    }

    public function testCountriesAreMappedToTheCheckoutStructure(): void
    {
        $countries = (new CountryRepository(new \Context()))->getCountries();

        self::assertSame([
            'id' => 1,
            'name' => 'Netherlands',
            'iso_code_2' => 'NL',
            'iso_code_3' => 'NLD',
            'call_prefix' => 31,
            'icon' => 'nl.jpg',
        ], $countries[0]);
    }

    public function testLookupByIsoCode(): void
    {
        $repository = new CountryRepository(new \Context());

        self::assertSame(2, $repository->getCountryByIsoCode2('DE')['id']);
        self::assertNull($repository->getCountryByIsoCode2('XX'));
    }
}
