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

namespace BuckarooTest\Unit\Module\MainModule;

use Buckaroo\PrestaShop\Src\ApplePay\ExpressSettings;
use Buckaroo\PrestaShop\Src\Service\PriceFormatter;

require_once __DIR__ . '/MainModuleTestCase.php';

/**
 * Constructor, configuration check and the small accessors of Buckaroo3.
 */
class ModuleConstructionTest extends MainModuleTestCase
{
    private const REQUIRED = [
        'BUCKAROO_MERCHANT_KEY',
        'BUCKAROO_SECRET_KEY',
        'BUCKAROO_ORDER_STATE_DEFAULT',
        'BUCKAROO_ORDER_STATE_SUCCESS',
        'BUCKAROO_ORDER_STATE_FAILED',
    ];

    public function testConstructorDescribesTheModule(): void
    {
        $module = new \Buckaroo3();

        self::assertSame('buckaroo3', $module->name);
        self::assertSame('payments_gateways', $module->tab);
        self::assertSame(\Buckaroo3::MODULE_VERSION, $module->version);
        self::assertSame('Buckaroo', $module->author);
        self::assertSame(1, $module->need_instance);
        self::assertTrue($module->bootstrap);
        self::assertSame(['min' => '1.7.8.0', 'max' => '9.2.99'], $module->ps_versions_compliancy);
        self::assertSame('Buckaroo Payments', $module->displayName);
        self::assertStringContainsString('Compatible with PrestaShop 1.7.8', $module->description);
        self::assertSame('Are you sure you want to delete Buckaroo Payments module?', $module->confirmUninstall);
        self::assertSame('buckaroo3', $module->tpl_folder);
        self::assertInstanceOf(\Logger::class, $module->logger);
        self::assertSame($this->context(), $module->getModuleContext());
    }

    /**
     * @dataProvider missingConfigurationProvider
     */
    public function testConstructorWarnsAboutTheFirstMissingRequiredSetting(string $missing): void
    {
        foreach (self::REQUIRED as $key) {
            if ($key !== $missing) {
                \Configuration::updateValue($key, 'value');
            }
        }

        $module = new \Buckaroo3();

        self::assertSame('Missing required configuration: ' . $missing, $module->warning);
    }

    public function missingConfigurationProvider(): array
    {
        $cases = [];
        foreach (self::REQUIRED as $key) {
            $cases[$key] = [$key];
        }

        return $cases;
    }

    public function testConstructorLeavesNoWarningWhenConfigured(): void
    {
        foreach (self::REQUIRED as $key) {
            \Configuration::updateValue($key, '1');
        }

        $module = new \Buckaroo3();

        self::assertFalse(isset($module->warning));
    }

    public function testPriceFormatterIsSharedAndUsesTheContextCurrency(): void
    {
        $module = $this->createModule();

        $formatter = $module->getPriceFormatter();

        self::assertInstanceOf(PriceFormatter::class, $formatter);
        self::assertSame($formatter, $module->getPriceFormatter());
        self::assertSame('EUR 12.50', $formatter->format(12.5));
    }

    public function testPaymentLabelComesFromTheResponse(): void
    {
        $module = $this->createModule();
        $response = new class {
            public $payment_method = 'iDEAL';

            public function isValid()
            {
                return true;
            }

            public function hasSucceeded()
            {
                return true;
            }
        };

        self::assertSame('iDEAL', $module->getPaymentLabel($response));
    }

    public function testPaymentLabelFallsBackToTheModuleNameForTheCurrentRequest(): void
    {
        $module = $this->createModule();

        self::assertSame('Buckaroo Payments', $module->getPaymentLabel());
    }

    /**
     * @dataProvider serviceAccessorProvider
     */
    public function testServiceAccessorsReadTheModuleContainer(string $accessor, string $serviceId): void
    {
        $service = new ServiceDouble();
        $module = $this->createModule([$serviceId => $service]);

        self::assertSame($service, $module->{$accessor}());
    }

    public function serviceAccessorProvider(): array
    {
        return [
            'config' => ['getBuckarooConfigService', 'buckaroo.config.api.config.service'],
            'fee' => ['getBuckarooFeeService', 'buckaroo.config.api.fee.service'],
            'click to pay' => ['getBuckarooClickToPayService', 'buckaroo.config.api.clicktopay.service'],
        ];
    }

    public function testApplePayExpressSettingsAreBuiltOnceFromTheApplePayConfig(): void
    {
        $config = $this->configService([
            'applepay' => ['merchant_guid' => ' guid-1 ', 'button_style' => 'white', 'express_show_on' => ['cart']],
        ]);
        $module = $this->createModule(['buckaroo.config.api.config.service' => $config]);

        $settings = $module->getApplePayExpressSettings();

        self::assertInstanceOf(ExpressSettings::class, $settings);
        self::assertSame($settings, $module->getApplePayExpressSettings());
        self::assertSame('guid-1', $settings->getMerchantGuid());
        self::assertSame('white', $settings->getButtonStyle());
        self::assertSame(['cart'], $settings->getPlacements());
        self::assertSame([['applepay']], $config->callsTo('getConfigArrayForMethod'));
    }

    /**
     * @dataProvider paymentModeProvider
     */
    public function testIsPaymentModeActive(array $config, bool $expected): void
    {
        $module = $this->createModule([
            'buckaroo.config.api.config.service' => $this->configService(['ideal' => $config]),
        ]);

        self::assertSame($expected, $module->isPaymentModeActive('ideal'));
    }

    public function paymentModeProvider(): array
    {
        return [
            'live' => [['mode' => 'live'], true],
            'test' => [['mode' => 'test'], true],
            'off' => [['mode' => 'off'], false],
            'no mode' => [['label' => 'iDEAL'], false],
            'no config' => [[], false],
        ];
    }
}
