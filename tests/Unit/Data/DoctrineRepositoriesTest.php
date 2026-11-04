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

namespace BuckarooTest\Unit\Data;

use Buckaroo\PrestaShop\Src\Entity\BkConfiguration;
use Buckaroo\PrestaShop\Src\Entity\BkGiftcards;
use Buckaroo\PrestaShop\Src\Entity\BkOrdering;
use Buckaroo\PrestaShop\Src\Entity\BkPaymentMethods;
use Buckaroo\PrestaShop\Src\Repository\ConfigurationRepository;
use Buckaroo\PrestaShop\Src\Repository\GiftCardsRepository;
use Buckaroo\PrestaShop\Src\Repository\OrderingRepository;
use Buckaroo\PrestaShop\Src\Repository\PaymentMethodRepository;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Doctrine repositories, run against the in-memory EntityRepository/EntityManager stubs.
 */
class DoctrineRepositoriesTest extends TestCase
{
    /** @var EntityManager */
    private $em;

    protected function setUp(): void
    {
        parent::setUp();
        // Like Doctrine, flush() hands out ids to new entities.
        $this->em = new class extends EntityManager {
            /** @var int */
            public $nextId = 500;

            public function flush()
            {
                parent::flush();
                foreach ($this->persisted as $entity) {
                    $property = new \ReflectionProperty($entity, 'id');
                    if (PHP_VERSION_ID < 80100) {
                        $property->setAccessible(true);
                    }
                    if ($property->getValue($entity) === null) {
                        $property->setValue($entity, $this->nextId++);
                    }
                }
            }
        };
        \Country::$countries = [
            ['id_country' => 13, 'name' => 'Netherlands', 'iso_code' => 'NL', 'call_prefix' => 31],
            ['id_country' => 1, 'name' => 'Germany', 'iso_code' => 'DE', 'call_prefix' => 49],
        ];
        \Country::$isoById = [13 => 'NLD', 1 => 'DEU'];
    }

    private static function ordering(?int $countryId, array $ids, int $id = 1): BkOrdering
    {
        $ordering = new BkOrdering();
        $ordering->setId($id);
        $ordering->setCountryId($countryId);
        $ordering->setValue(json_encode($ids));
        $ordering->setCreatedAt(new \DateTime('2020-01-01'));

        return $ordering;
    }

    private static function method(int $id, string $name): BkPaymentMethods
    {
        $method = new BkPaymentMethods();
        $method->setId($id);
        $method->setName($name);
        $method->setLabel(strtoupper($name));
        $method->setIcon($name . '.svg');

        return $method;
    }

    /**
     * @param BkOrdering[] $orderings
     * @param BkPaymentMethods[] $methods
     */
    private function orderingRepository(array $orderings, array $methods = []): OrderingRepository
    {
        $repository = new OrderingRepository($this->em, new ClassMetadata(BkOrdering::class));
        $repository->rows = $orderings;
        $methodRepository = new EntityRepository($this->em, new ClassMetadata(BkPaymentMethods::class));
        $methodRepository->rows = $methods;
        $this->em->repositories[BkOrdering::class] = $repository;
        $this->em->repositories[BkPaymentMethods::class] = $methodRepository;

        return $repository;
    }

    // ------------------------------------------------------------------
    // OrderingRepository
    // ------------------------------------------------------------------

    public function testFindOneByCountryFindsTheCountryOrTheGlobalOrdering(): void
    {
        $global = self::ordering(null, [1], 1);
        $dutch = self::ordering(13, [2], 2);
        $repository = $this->orderingRepository([$dutch, $global]);

        self::assertSame($dutch, $repository->findOneByCountry(13));
        self::assertSame($dutch, $repository->findOneByCountryId(13));
        self::assertSame($global, $repository->findOneByCountry(null));
        self::assertNull($repository->findOneByCountry(99));
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public function orderingValueProvider(): array
    {
        $methods = [['id' => 3, 'name' => 'ideal'], ['id' => 1, 'name' => 'paypal']];

        return [
            'array' => [$methods],
            'json string' => [json_encode($methods)],
        ];
    }

    /**
     * @dataProvider orderingValueProvider
     */
    public function testUpdateOrderingStoresOnlyTheIdsOnTheExistingGlobalOrdering($value): void
    {
        $global = self::ordering(null, [1, 3]);
        $repository = $this->orderingRepository([$global]);

        self::assertTrue($repository->updateOrdering($value));

        self::assertSame('[3,1]', $global->getValue());
        self::assertSame([$global], $this->em->persisted);
        self::assertSame(1, $this->em->flushes);
    }

    public function testUpdateOrderingCreatesTheCountryOrderingWhenMissing(): void
    {
        $repository = $this->orderingRepository([self::ordering(null, [1])]);

        self::assertTrue($repository->updateOrdering([['id' => 7], ['id' => 8]], 13));

        self::assertCount(1, $this->em->persisted);
        $created = $this->em->persisted[0];
        self::assertInstanceOf(BkOrdering::class, $created);
        self::assertSame(13, $created->getCountryId());
        self::assertSame('[7,8]', $created->getValue());
        self::assertInstanceOf(\DateTime::class, $created->getCreatedAt());
    }

    public function testUpdateOrderingRejectsInvalidJson(): void
    {
        $repository = $this->orderingRepository([]);

        self::assertFalse($repository->updateOrdering('{invalid'));
        self::assertSame([], $this->em->persisted);
        self::assertSame(0, $this->em->flushes);
    }

    public function testUpdateOrderingRejectsAnUnknownCountry(): void
    {
        $repository = $this->orderingRepository([]);

        self::assertFalse($repository->updateOrdering([['id' => 1]], 999));
        self::assertSame([], $this->em->persisted);
    }

    public function testGetOrderingListsTheMethodsOfTheGlobalOrderingForAnUnknownCountry(): void
    {
        $repository = $this->orderingRepository(
            [self::ordering(null, [2, 404, 1])],
            [self::method(1, 'ideal'), self::method(2, 'paypal')]
        );

        self::assertSame([
            'country_id' => null,
            'value' => [
                ['id' => 2, 'name' => 'paypal', 'label' => 'PAYPAL', 'icon' => 'paypal.svg'],
                ['id' => 1, 'name' => 'ideal', 'label' => 'IDEAL', 'icon' => 'ideal.svg'],
            ],
            'status' => true,
        ], $repository->getOrdering('XX'));
    }

    public function testGetOrderingFallsBackToEveryMethodWhenTheCountryHasNoOrdering(): void
    {
        $repository = $this->orderingRepository([], [self::method(1, 'ideal'), self::method(2, 'paypal')]);

        $result = $repository->getOrdering('NL');

        self::assertSame(13, $result['country_id']);
        self::assertSame(['ideal', 'paypal'], array_column($result['value'], 'name'));
    }

    public function testGetOrderingLooksUpTheOrderingByTheCountryId(): void
    {
        // Regression: the whole country row used to be passed as the criterion, which
        // Doctrine turns into "country_id IN (id, name, iso, iso3, call_prefix, icon)".
        $repository = new class($this->em, new ClassMetadata(BkOrdering::class)) extends OrderingRepository {
            /** @var array */
            public $criteria = [];

            public function findOneBy(array $criteria, ?array $orderBy = null)
            {
                $this->criteria[] = $criteria;

                return parent::findOneBy($criteria, $orderBy);
            }
        };
        $this->em->repositories[BkPaymentMethods::class] = new EntityRepository();

        $repository->getOrdering('NL');

        self::assertSame(['country_id' => 13], $repository->criteria[0]);
    }

    public function testGetOrderingDoesNotPickTheOrderingOfACountryWhoseIdIsTheCallPrefix(): void
    {
        // NL has call prefix 31; the ordering of country 31 must not be returned for NL.
        $repository = $this->orderingRepository(
            [self::ordering(31, [2], 1), self::ordering(13, [1], 2)],
            [self::method(1, 'ideal'), self::method(2, 'paypal')]
        );

        $result = $repository->getOrdering('NL');

        self::assertSame(13, $result['country_id']);
        self::assertSame(['ideal'], array_column($result['value'], 'name'));
    }

    public function testGetOrderingForAnUnknownCountryWithoutAnyOrderingListsEveryMethod(): void
    {
        $repository = $this->orderingRepository([], [self::method(1, 'ideal'), self::method(2, 'paypal')]);

        $result = $repository->getOrdering('XX');

        self::assertNull($result['country_id']);
        self::assertTrue($result['status']);
        self::assertSame(['ideal', 'paypal'], array_column($result['value'], 'name'));
    }

    public function testGetOrderingIgnoresACorruptStoredValue(): void
    {
        $broken = self::ordering(13, [], 1);
        $broken->setValue('{not json');
        $repository = $this->orderingRepository([$broken], [self::method(1, 'ideal')]);

        self::assertSame(['country_id' => 13, 'value' => [], 'status' => true], $repository->getOrdering('NL'));
    }

    public function testFetchPositionsReturnsActiveMethodsInTheCountryOrder(): void
    {
        $repository = $this->orderingRepository(
            [self::ordering(null, [1, 2, 3], 1), self::ordering(13, [3, 1, 2, 9], 2)],
            [self::method(1, 'ideal'), self::method(2, 'paypal'), self::method(3, 'klarna')]
        );

        self::assertSame(['klarna', 'ideal'], $repository->fetchPositions(13, [1, 3, 9]));
    }

    public function testFetchPositionsFallsBackToTheGlobalOrdering(): void
    {
        $repository = $this->orderingRepository(
            [self::ordering(null, [2, 1], 1)],
            [self::method(1, 'ideal'), self::method(2, 'paypal')]
        );

        self::assertSame(['paypal', 'ideal'], $repository->fetchPositions(1, [1, 2]));
    }

    public function testFetchPositionsReturnsNothingWithoutAnyOrdering(): void
    {
        $repository = $this->orderingRepository([], [self::method(1, 'ideal')]);

        self::assertSame([], $repository->fetchPositions(13, [1]));
    }

    public function testFetchPositionsReturnsNothingForACorruptOrdering(): void
    {
        $broken = self::ordering(null, [], 1);
        $broken->setValue('{not json');
        $repository = $this->orderingRepository([$broken], [self::method(1, 'ideal')]);

        self::assertSame([], $repository->fetchPositions(13, [1]));
    }

    public function testCreateNewOrderingPersistsTheMethodIds(): void
    {
        $repository = $this->orderingRepository([]);

        $ordering = $repository->createNewOrdering(13, [4, 5]);

        self::assertSame(13, $ordering->getCountryId());
        self::assertSame('[4,5]', $ordering->getValue());
        self::assertSame([$ordering], $this->em->persisted);
        self::assertSame(1, $this->em->flushes);
    }

    /**
     * @return array<string, array{0: int[], 1: int, 2: bool, 3: string}>
     */
    public function addPaymentMethodProvider(): array
    {
        return [
            'new method is appended' => [[1, 2], 3, true, '[1,2,3]'],
            'existing method is left alone' => [[1, 2], 2, false, '[1,2]'],
        ];
    }

    /**
     * @dataProvider addPaymentMethodProvider
     */
    public function testAddPaymentMethodToOrdering(array $ids, int $methodId, bool $updated, string $expectedValue): void
    {
        $ordering = self::ordering(13, $ids);
        $repository = $this->orderingRepository([$ordering]);

        self::assertSame($updated, $repository->addPaymentMethodToOrdering($ordering, $methodId));
        self::assertSame($expectedValue, $ordering->getValue());
        self::assertSame($updated ? 1 : 0, $this->em->flushes);
    }

    public function testRemovePaymentMethodFromOrderingsOfCountriesNoLongerConfigured(): void
    {
        $global = self::ordering(null, [5, 6], 1);
        $kept = self::ordering(13, [5, 6], 2);         // country still configured
        $shrunk = self::ordering(1, [6, 5, 7], 3);     // loses method 5
        $emptied = self::ordering(2, [5], 4);          // nothing left: removed
        $without = self::ordering(3, [6], 5);          // never had method 5
        $repository = $this->orderingRepository([$global, $kept, $shrunk, $emptied, $without]);

        $repository->removePaymentMethodFromOrderings(5, [13]);

        self::assertSame('[5,6]', $global->getValue());
        self::assertSame('[5,6]', $kept->getValue());
        self::assertSame('[6,7]', $shrunk->getValue());
        self::assertSame([$shrunk], $this->em->persisted);
        self::assertSame([$emptied], $this->em->removed);
        self::assertSame(1, $this->em->flushes);
    }

    // ------------------------------------------------------------------
    // PaymentMethodRepository
    // ------------------------------------------------------------------

    /**
     * Query builder double recording the calls and returning $rows.
     */
    private function queryBuilder(array $rows)
    {
        $qb = new class($rows) {
            /** @var array */
            public $rows;

            /** @var array<int, array{0: string, 1: array}> */
            public $calls = [];

            public function __construct(array $rows)
            {
                $this->rows = $rows;
            }

            public function __call($method, $args)
            {
                $this->calls[] = [$method, $args];

                return $this;
            }

            public function getQuery()
            {
                return $this;
            }

            public function getArrayResult()
            {
                return $this->rows;
            }
        };
        Stubs::returns('EntityManager->createQueryBuilder', function () use ($qb) {
            return $qb;
        });

        return $qb;
    }

    private function registerIn3Logo(string $logo): void
    {
        $in3 = new class($logo) {
            /** @var string */
            private $logo;

            public function __construct(string $logo)
            {
                $this->logo = $logo;
            }

            public function getLogo()
            {
                return $this->logo;
            }
        };
        $module = new \Module();
        $module->stub('get', function ($service) use ($in3) {
            return $service === 'buckaroo.classes.issuers.capayableIn3' ? $in3 : null;
        });
        \Module::$instances['buckaroo3'] = $module;
    }

    private function paymentMethodRepository(): PaymentMethodRepository
    {
        return new PaymentMethodRepository($this->em, new ClassMetadata(BkPaymentMethods::class));
    }

    public function testFetchMethodsFromDbWithConfigMergesTheConfiguration(): void
    {
        $this->registerIn3Logo('In3-new.svg');
        $qb = $this->queryBuilder([
            ['id' => 1, 'payment_name' => 'ideal', 'payment_icon' => 'iDEAL.svg', 'config_value' => '{"mode":"live","show_issuers":true}'],
            ['id' => 2, 'payment_name' => 'in3', 'payment_icon' => 'In3.svg', 'config_value' => '{"mode":"test","version":"V3"}'],
            ['id' => 3, 'payment_name' => 'eps', 'payment_icon' => 'EPS.svg', 'config_value' => null],
            ['id' => 4, 'payment_name' => 'blik', 'payment_icon' => 'Blik.svg', 'config_value' => 'not json'],
        ]);

        $methods = $this->paymentMethodRepository()->fetchMethodsFromDBWithConfig(1);

        self::assertSame([
            ['name' => 'ideal', 'icon' => 'iDEAL.svg', 'mode' => 'live', 'show_issuers' => true],
            ['name' => 'in3', 'icon' => 'In3-new.svg', 'mode' => 'test', 'version' => 'V3'],
            ['name' => 'eps', 'icon' => 'EPS.svg'],
            ['name' => 'blik', 'icon' => 'Blik.svg'],
        ], $methods);

        $calls = array_column($qb->calls, 1, 0);
        self::assertSame([BkPaymentMethods::class, 'pm'], $calls['from']);
        self::assertSame(['pm.is_payment_method = :isPaymentMethod'], $calls['where']);
        self::assertSame(['isPaymentMethod', 1], $calls['setParameter']);
        self::assertSame([BkConfiguration::class, 'config', 'WITH', 'pm.id = config.configurable_id'], $calls['leftJoin']);
    }

    public function testFetchMethodsFromDbWithConfigThrowsWithoutResults(): void
    {
        $this->queryBuilder([]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not fetch payment methods with config');

        $this->paymentMethodRepository()->fetchMethodsFromDBWithConfig(0);
    }

    public function testGetActivePaymentMethodsFiltersByConfiguredCountries(): void
    {
        $rows = [
            'all countries' => ['id' => 1, 'payment_name' => 'ideal', 'config_value' => '{"mode":"live"}'],
            'empty list' => ['id' => 2, 'payment_name' => 'eps', 'config_value' => '{"countries":[]}'],
            'dutch only' => ['id' => 3, 'payment_name' => 'in3', 'config_value' => '{"countries":[{"id":"13"},{"name":"x"}]}'],
            'german only' => ['id' => 4, 'payment_name' => 'sofort', 'config_value' => '{"countries":[{"id":1}]}'],
            'not a list' => ['id' => 5, 'payment_name' => 'blik', 'config_value' => '{"countries":"all"}'],
            'invalid json' => ['id' => 6, 'payment_name' => 'paypal', 'config_value' => '{bad'],
        ];
        $qb = $this->queryBuilder(array_values($rows));

        $active = $this->paymentMethodRepository()->getActivePaymentMethods(13);

        self::assertSame(['ideal', 'in3', 'blik'], array_column($active, 'payment_name'));
        // All methods are fetched: no is_payment_method filter.
        self::assertNotContains('where', array_column($qb->calls, 0));
    }

    // ------------------------------------------------------------------
    // ConfigurationRepository
    // ------------------------------------------------------------------

    private static function configuration(int $configurableId, string $value): BkConfiguration
    {
        $configuration = new BkConfiguration();
        $configuration->setId($configurableId + 100);
        $configuration->setConfigurableId($configurableId);
        $configuration->setValue($value);

        return $configuration;
    }

    private function configurationRepository(array $configurations, array $methods = []): ConfigurationRepository
    {
        $repository = new ConfigurationRepository($this->em, new ClassMetadata(BkConfiguration::class));
        $repository->rows = $configurations;
        $methodRepository = new EntityRepository();
        $methodRepository->rows = $methods;
        $this->em->repositories[BkPaymentMethods::class] = $methodRepository;

        return $repository;
    }

    public function testGetConfigArrayDecodesTheMethodConfiguration(): void
    {
        $repository = $this->configurationRepository([
            self::configuration(1, '{"mode":"test"}'),
            self::configuration(2, '{"mode":"live","due_days":"7"}'),
        ]);

        self::assertSame(['mode' => 'live', 'due_days' => '7'], $repository->getConfigArray(2));
    }

    /**
     * @return array<string, array{0: string|null, 1: string}>
     */
    public function brokenConfigurationProvider(): array
    {
        return [
            'missing' => [null, 'Configuration not found for payment id 2'],
            'invalid json' => ['{oops', 'JSON decode error: Syntax error'],
        ];
    }

    /**
     * @dataProvider brokenConfigurationProvider
     */
    public function testGetConfigArrayThrowsForABrokenConfiguration(?string $storedValue, string $message): void
    {
        $rows = $storedValue === null ? [] : [self::configuration(2, $storedValue)];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($message);

        $this->configurationRepository($rows)->getConfigArray(2);
    }

    public function testUpdateConfigStoresTheEncodedConfiguration(): void
    {
        $configuration = self::configuration(2, '{"mode":"off"}');
        $repository = $this->configurationRepository([$configuration]);

        self::assertTrue($repository->updateConfig(2, ['mode' => 'live', 'countries' => []]));

        self::assertSame('{"mode":"live","countries":[]}', $configuration->getValue());
        self::assertSame([$configuration], $this->em->persisted);
        self::assertSame(1, $this->em->flushes);
    }

    public function testUpdateConfigThrowsForAnUnknownMethod(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Configuration not found for payment id 9');

        $this->configurationRepository([])->updateConfig(9, []);
    }

    /**
     * @return array<string, array{0: array, 1: array}>
     */
    public function activeCreditCardsProvider(): array
    {
        return [
            'cards keyed by lower-case service code' => [
                ['mode' => 'live', 'activeCreditcards' => [
                    ['service_code' => 'VISA', 'name' => 'Visa', 'icon' => 'Visa.svg'],
                    ['service_code' => 'amex', 'name' => 'American Express', 'icon' => 'AMEX.svg'],
                ]],
                [
                    'visa' => ['name' => 'Visa', 'logo' => 'Visa.svg'],
                    'amex' => ['name' => 'American Express', 'logo' => 'AMEX.svg'],
                ],
            ],
            'no cards configured' => [['mode' => 'live'], []],
        ];
    }

    /**
     * @dataProvider activeCreditCardsProvider
     */
    public function testGetActiveCreditCards(array $config, array $expected): void
    {
        $repository = $this->configurationRepository(
            [self::configuration(8, json_encode($config))],
            [self::method(1, 'ideal'), self::method(8, 'creditcard')]
        );

        self::assertSame($expected, $repository->getActiveCreditCards());
    }

    // ------------------------------------------------------------------
    // GiftCardsRepository
    // ------------------------------------------------------------------

    private static function giftCard(int $id, string $code, int $isCustom): BkGiftcards
    {
        $card = new BkGiftcards();
        $property = new \ReflectionProperty($card, 'id');
        if (PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }
        $property->setValue($card, $id);
        $card->setCode($code);
        $card->setName(ucfirst($code));
        $card->setLogo($code . '.svg');
        $card->setIsCustom($isCustom);
        $card->setCreatedAt(new \DateTime('2025-05-06 07:08:09'));

        return $card;
    }

    private function giftCardsRepository(array $cards): GiftCardsRepository
    {
        $repository = new GiftCardsRepository($this->em, new ClassMetadata(BkGiftcards::class));
        $repository->rows = $cards;

        return $repository;
    }

    public function testCreateGiftCardPersistsACustomCard(): void
    {
        $result = $this->giftCardsRepository([])->createGiftCard('My Card', 'mycard', 'https://cdn/mycard.png');

        self::assertSame([
            'id' => 500,
            'name' => 'My Card',
            'service_code' => 'mycard',
            'is_custom' => 1,
            'logo_url' => 'https://cdn/mycard.png',
        ], $result);
        self::assertCount(1, $this->em->persisted);
        self::assertInstanceOf(\DateTime::class, $this->em->persisted[0]->getCreatedAt());
        self::assertSame(1, $this->em->flushes);
    }

    public function testEditGiftCardUpdatesTheExistingCard(): void
    {
        $card = self::giftCard(4, 'old', 1);
        $repository = $this->giftCardsRepository([$card]);

        $result = $repository->editGiftCard(4, 'New', 'new', 'new.svg');

        self::assertSame(['id' => 4, 'name' => 'New', 'service_code' => 'new', 'is_custom' => 1, 'logo_url' => 'new.svg'], $result);
        self::assertSame([], $this->em->persisted, 'a managed entity only needs a flush');
        self::assertSame(1, $this->em->flushes);
    }

    public function testEditGiftCardCreatesTheCardWhenItDoesNotExist(): void
    {
        $result = $this->giftCardsRepository([])->editGiftCard(99, 'New', 'new', 'new.svg');

        self::assertSame(500, $result['id']);
        self::assertSame(1, $result['is_custom']);
        self::assertCount(1, $this->em->persisted);
    }

    /**
     * @return array<string, array{0: bool, 1: string[]}>
     */
    public function giftCardTypeProvider(): array
    {
        return [
            'custom cards' => [true, ['mine']],
            'built-in cards' => [false, ['boekenbon', 'yourgift']],
        ];
    }

    /**
     * @dataProvider giftCardTypeProvider
     */
    public function testGetGiftCardsReturnsTheCardsOfOneType(bool $custom, array $codes): void
    {
        $repository = $this->giftCardsRepository([
            self::giftCard(1, 'boekenbon', 0),
            self::giftCard(2, 'mine', 1),
            self::giftCard(3, 'yourgift', 0),
        ]);

        $cards = $repository->getGiftCards($custom);

        self::assertSame($codes, array_column($cards, 'code'));
        self::assertSame([
            'id' => $cards[0]['id'],
            'code' => $codes[0],
            'name' => ucfirst($codes[0]),
            'logo' => $codes[0] . '.svg',
            'is_custom' => $custom ? 1 : 0,
            'created_at' => '2025-05-06 07:08:09',
        ], $cards[0]);
    }

    public function testRemoveGiftCardRemovesIt(): void
    {
        $card = self::giftCard(4, 'mine', 1);

        self::assertSame(['status' => true], $this->giftCardsRepository([$card])->removeGiftCard(4));
        self::assertSame([$card], $this->em->removed);
        self::assertSame(1, $this->em->flushes);
    }

    public function testRemoveGiftCardThrowsForAnUnknownId(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Gift card not found with ID: 12');

        $this->giftCardsRepository([])->removeGiftCard(12);
    }
}
