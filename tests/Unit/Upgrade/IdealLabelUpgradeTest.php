<?php

namespace BuckarooTest\Unit\Upgrade;

use BuckarooTest\Stubs;

require_once __DIR__ . '/UpgradeTestCase.php';

/**
 * upgrade-5.0.1.php and upgrade-5.1.0.php (identical): rename iDEAL to
 * "iDEAL | Wero", including a frontend label the merchant left at "iDEAL".
 */
class IdealLabelUpgradeTest extends UpgradeTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Loaded inside the test run so the files' top-level guard counts as covered.
        self::requireUpgrade('5.0.1');
        self::requireUpgrade('5.1.0');
    }

    public static function upgrades(): array
    {
        return [
            '5.0.1' => ['upgrade_module_5_0_1'],
            '5.1.0' => ['upgrade_module_5_1_0'],
        ];
    }

    /**
     * @dataProvider upgrades
     */
    public function testRenamesTheMethodAndOnlyTheDefaultFrontendLabel(string $function): void
    {
        Stubs::db('/^SELECT id FROM `ps_bk_payment_methods` WHERE \(name = "ideal"\)$/', '7');
        Stubs::db('/^SELECT id, value FROM `ps_bk_configuration` WHERE \(configurable_id = 7\)$/', [
            ['id' => '1', 'value' => '{"mode":"live","frontend_label":"iDEAL"}'],
            ['id' => '2', 'value' => ''],
            ['id' => '3', 'value' => null],
            ['id' => '4', 'value' => 'not json'],
            ['id' => '5', 'value' => '{"mode":"test","frontend_label":"Pay with iDEAL"}'],
            ['id' => '6', 'value' => '{"mode":"test"}'],
        ]);

        self::assertTrue($function($this->module()));

        self::assertSame([['bk_payment_methods', ['label' => 'iDEAL | Wero'], 'id = 7']], self::updates('bk_payment_methods'));

        $configUpdates = self::updates('bk_configuration');
        self::assertCount(1, $configUpdates);
        self::assertSame('id = 1', $configUpdates[0][2]);
        self::assertSame(
            ['mode' => 'live', 'frontend_label' => 'iDEAL | Wero'],
            json_decode(stripslashes($configUpdates[0][1]['value']), true)
        );
    }

    /**
     * @dataProvider upgrades
     */
    public function testDoesNothingWithoutAnIdealMethod(string $function): void
    {
        self::assertTrue($function($this->module()));

        self::assertSame([], Stubs::sql('update'));
        self::assertSame([], Stubs::sql('executeS'));
    }

    /**
     * @dataProvider upgrades
     */
    public function testRenamesTheMethodWhenTheConfigurationCannotBeRead(string $function): void
    {
        Stubs::db('/name = "ideal"/', '7');
        Stubs::db('/ps_bk_configuration/', false);

        self::assertTrue($function($this->module()));

        self::assertCount(1, self::updates('bk_payment_methods'));
        self::assertSame([], self::updates('bk_configuration'));
    }
}
