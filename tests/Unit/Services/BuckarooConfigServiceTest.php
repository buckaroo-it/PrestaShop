<?php

declare(strict_types=1);

namespace BuckarooTest\Unit\Services;

use Buckaroo\PrestaShop\Src\ApplePay\DomainAssociation;
use Buckaroo\PrestaShop\Src\Entity\BkConfiguration;
use Buckaroo\PrestaShop\Src\Entity\BkOrdering;
use Buckaroo\PrestaShop\Src\Entity\BkPaymentMethods;
use Buckaroo\PrestaShop\Src\Service\BuckarooConfigService;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;
use Doctrine\ORM\EntityManager;

final class BuckarooConfigServiceTest extends TestCase
{
    /** @var object */
    private $methods;

    /** @var object */
    private $configurations;

    /** @var EntityManager */
    private $entityManager;

    /** @var string|false|null content of the Apple Pay association file before the test */
    private $associationBackup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->methods = new class {
            /** @var array<string, int> */
            public $ids = ['ideal' => 1, 'giftcard' => 2, 'paybybank' => 3, 'applepay' => 4];

            /** @var array<int, int> */
            public $fetched = [];

            public function findOneBy(array $criteria)
            {
                $id = $this->ids[$criteria['name']] ?? null;

                return $id === null ? null : new class($id) {
                    private $id;

                    public function __construct($id)
                    {
                        $this->id = $id;
                    }

                    public function getId()
                    {
                        return $this->id;
                    }
                };
            }

            public function fetchMethodsFromDBWithConfig($isPayment)
            {
                $this->fetched[] = $isPayment;

                return ['payment' => $isPayment];
            }
        };

        $this->configurations = new class {
            /** @var array<int, array<string, mixed>> */
            public $configs = [];

            /** @var array<int, array{0: int, 1: array}> */
            public $updates = [];

            /** @var bool */
            public $updateResult = true;

            public function getConfigArray($id)
            {
                return $this->configs[$id] ?? [];
            }

            public function updateConfig($id, array $config)
            {
                $this->updates[] = [$id, $config];

                return $this->updateResult;
            }

            public function getActiveCreditCards()
            {
                return ['visa' => ['name' => 'VISA']];
            }
        };

        $this->entityManager = new EntityManager();
        $this->entityManager->repositories = [
            BkPaymentMethods::class => $this->methods,
            BkConfiguration::class => $this->configurations,
            BkOrdering::class => new \stdClass(),
        ];

        $file = self::associationFile();
        $this->associationBackup = is_file($file) ? file_get_contents($file) : null;
    }

    protected function tearDown(): void
    {
        $file = self::associationFile();
        if ($this->associationBackup === null) {
            @unlink($file);
        } else {
            file_put_contents($file, $this->associationBackup);
        }
        parent::tearDown();
    }

    private static function associationFile(): string
    {
        return DomainAssociation::forModule(_PS_MODULE_DIR_ . 'buckaroo3')->getDestinationFile();
    }

    private function service(): BuckarooConfigService
    {
        return new BuckarooConfigService($this->entityManager);
    }

    public function testConfigArrayForAKnownMethodAddsTheFeeFlag(): void
    {
        $this->configurations->configs[1] = ['mode' => 'live', 'frontend_label' => 'iDEAL'];
        $this->configurations->configs[3] = ['mode' => 'test'];

        self::assertSame(
            ['mode' => 'live', 'frontend_label' => 'iDEAL', 'payment_fee_allowed' => true],
            $this->service()->getConfigArrayForMethod('ideal')
        );
        self::assertFalse($this->service()->getConfigArrayForMethod('paybybank')['payment_fee_allowed']);
        self::assertSame('live', $this->service()->getConfigValue('ideal', 'mode'));
        self::assertNull($this->service()->getConfigValue('ideal', 'missing'));
    }

    public function testUnknownMethodHasNoConfig(): void
    {
        self::assertNull($this->service()->getConfigArrayForMethod('nope'));
        self::assertNull($this->service()->getConfigValue('nope', 'mode'));
        self::assertFalse($this->service()->updatePaymentMethodConfig('nope', ['mode' => 'live']));
        self::assertFalse($this->service()->updatePaymentMethodMode('nope', 'live'));
        self::assertSame([], $this->configurations->updates);
    }

    public function testUpdatePaymentMethodConfigMergesWithTheStoredConfig(): void
    {
        $this->configurations->configs[1] = ['mode' => 'test', 'frontend_label' => 'Old', 'min_order_amount' => 5];

        self::assertTrue($this->service()->updatePaymentMethodConfig('ideal', ['frontend_label' => 'New', 'payment_fee' => '1.50']));

        self::assertSame([[1, [
            'mode' => 'test', 'frontend_label' => 'New', 'min_order_amount' => 5, 'payment_fee' => '1.50',
        ]]], $this->configurations->updates);
        self::assertArrayNotHasKey('BUCKAROO_GIFTCARD_ALLOWED_CARDS', \Configuration::$values);
    }

    public function testUpdateReturnsTheRepositoryResult(): void
    {
        $this->configurations->updateResult = false;

        self::assertFalse($this->service()->updatePaymentMethodConfig('ideal', []));
    }

    public function testFeeIsClearedForMethodsWithoutPaymentFee(): void
    {
        $this->configurations->configs[3] = ['payment_fee' => '2'];

        $this->service()->updatePaymentMethodConfig('paybybank', ['payment_fee' => '5', 'mode' => 'live']);

        self::assertSame([3, ['payment_fee' => '', 'mode' => 'live']], $this->configurations->updates[0]);
    }

    public function testSavingGiftcardSyncsTheAllowedCardCodes(): void
    {
        $this->service()->updatePaymentMethodConfig('giftcard', ['activeGiftcards' => [
            'giftcards' => [['code' => 'boekenbon'], ['service_code' => 'vvvgiftcard'], ['code' => 'boekenbon'], 'invalid', ['code' => '']],
            'customGiftcards' => [['code' => 'mycard']],
            'other' => [['code' => 'ignored']],
        ]]);

        self::assertSame('boekenbon,vvvgiftcard,mycard', \Configuration::get('BUCKAROO_GIFTCARD_ALLOWED_CARDS'));
        self::assertCount(1, $this->configurations->updates);
    }

    public function testSavingGiftcardWithoutSelectionClearsTheAllowedCards(): void
    {
        \Configuration::updateValue('BUCKAROO_GIFTCARD_ALLOWED_CARDS', 'old');
        $this->configurations->configs[2] = ['activeGiftcards' => ['giftcards' => 'not-a-list']];

        $this->service()->updatePaymentMethodConfig('giftcard', []);

        self::assertSame('', \Configuration::get('BUCKAROO_GIFTCARD_ALLOWED_CARDS'));
    }

    public function testSavingApplePaySanitizesAndPlacesTheDomainAssociation(): void
    {
        @unlink(self::associationFile());

        $this->service()->updatePaymentMethodConfig('applepay', [
            'button_style' => 'pink',
            'merchant_guid' => ' abc-123 ',
            'frontend_label' => 'Apple Pay',
        ]);

        $saved = $this->configurations->updates[0][1];
        self::assertSame('black', $saved['button_style']);
        self::assertSame('abc-123', $saved['merchant_guid']);
        self::assertSame('Apple Pay', $saved['frontend_label']);

        self::assertFileExists(self::associationFile());
        self::assertSame(0, Stubs::callCount('PrestaShopLogger::addLog'), 'no warning when the file was placed');
    }

    public function testApplePayWarnsWhenAnotherAssociationFileIsPresent(): void
    {
        $file = self::associationFile();
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        file_put_contents($file, 'another provider');

        $this->service()->updatePaymentMethodConfig('applepay', []);

        self::assertSame(
            [['Buckaroo: Apple Pay domain association file not placed in /.well-known/ (other_file)', 2]],
            Stubs::callsTo('PrestaShopLogger::addLog')
        );
        self::assertSame('another provider', file_get_contents($file), 'an existing file is never replaced');
        self::assertCount(1, $this->configurations->updates, 'the config is still saved');
    }

    public function testUpdatePaymentMethodModeOnlyChangesTheMode(): void
    {
        $this->configurations->configs[1] = ['mode' => 'off', 'frontend_label' => 'iDEAL'];

        self::assertTrue($this->service()->updatePaymentMethodMode('ideal', 'live'));
        self::assertSame([[1, ['mode' => 'live', 'frontend_label' => 'iDEAL']]], $this->configurations->updates);
    }

    public function testMethodListsAndCreditCardsComeFromTheRepositories(): void
    {
        self::assertSame(['payment' => 1], $this->service()->getPaymentMethodsFromDBWithConfig());
        self::assertSame(['payment' => 0], $this->service()->getVerificationMethodsFromDBWithConfig());
        self::assertSame([1, 0], $this->methods->fetched);
        self::assertSame(['visa' => ['name' => 'VISA']], $this->service()->getActiveCreditCards());
    }

    public function testAllowedGiftcardCodesPreferTheLegacySetting(): void
    {
        \Configuration::updateValue('BUCKAROO_GIFTCARD_ALLOWED_CARDS', ' boekenbon , ,vvvgiftcard,boekenbon ');
        $this->configurations->configs[2] = ['activeGiftcards' => ['giftcards' => [['code' => 'ignored']]]];

        self::assertSame(['boekenbon', 'vvvgiftcard'], $this->service()->getAllowedGiftcardCodes());
    }

    public function testAllowedGiftcardCodesFallBackToTheAdminSelection(): void
    {
        $this->configurations->configs[2] = ['activeGiftcards' => [
            'giftcards' => [['code' => ' boekenbon '], ['service_code' => 'yourgift'], 'bad', ['name' => 'no code']],
            'customGiftcards' => [['code' => 'boekenbon'], ['code' => 'custom']],
        ]];

        self::assertSame(['boekenbon', 'yourgift', 'custom'], $this->service()->getAllowedGiftcardCodes());
    }

    public function testAllowedGiftcardCodesAreEmptyWithoutAnySelection(): void
    {
        self::assertSame([], $this->service()->getAllowedGiftcardCodes());

        $this->methods->ids = [];
        self::assertSame([], $this->service()->getAllowedGiftcardCodes(), 'no giftcard method at all');
    }
}
