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

use Buckaroo\PrestaShop\Src\Repository\PaymentMethodRepository;
use PHPUnit\Framework\TestCase;

final class PaymentMethodRepositoryTest extends TestCase
{
    private const NETHERLANDS = 13;
    private const FRANCE = 8;

    /**
     * @param array<string, mixed>|null $config
     */
    private function isShownFor(?array $config, int $countryId): bool
    {
        $class = new \ReflectionClass(PaymentMethodRepository::class);
        $repository = $class->newInstanceWithoutConstructor();
        $filter = $class->getMethod('filterPaymentMethodsByCountry');
        $filter->setAccessible(true);

        $results = [['id' => 1, 'payment_name' => 'eps', 'config_value' => json_encode($config)]];

        return $filter->invoke($repository, $results, $countryId) !== [];
    }

    public function testMethodWithoutCountrySettingIsShownForAllCountries(): void
    {
        self::assertTrue($this->isShownFor(['mode' => 'test'], self::NETHERLANDS));
    }

    public function testMethodWithClearedCountriesIsShownForAllCountries(): void
    {
        self::assertTrue($this->isShownFor(['mode' => 'test', 'countries' => []], self::NETHERLANDS));
    }

    public function testMethodIsOnlyShownForTheSelectedCountries(): void
    {
        $config = ['mode' => 'test', 'countries' => [['id' => self::FRANCE, 'name' => 'France']]];

        self::assertTrue($this->isShownFor($config, self::FRANCE));
        self::assertFalse($this->isShownFor($config, self::NETHERLANDS));
    }
}
