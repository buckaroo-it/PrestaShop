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

namespace Buckaroo\PrestaShop\Tests\Unit\Install;

use Buckaroo\PrestaShop\Src\Config\Config;
use Buckaroo\PrestaShop\Src\Install\ConfigurationKeyMigrator;
use PHPUnit\Framework\TestCase;

final class ConfigurationKeyMigratorTest extends TestCase
{
    protected function setUp(): void
    {
        \Configuration::$values = [];
    }

    public function testCopiesTheLegacyValuesToThePrefixedKeysAndRemovesTheOldOnes(): void
    {
        \Configuration::$values['PAYMENT_FEE_MODE'] = 'subtotal_incl_tax';
        \Configuration::$values['PAYMENT_FEE_FRONTEND_LABEL'] = 'Service fee';

        self::assertSame(2, (new ConfigurationKeyMigrator())->migrate());

        self::assertSame('subtotal_incl_tax', \Configuration::get(Config::PAYMENT_FEE_MODE));
        self::assertSame('Service fee', \Configuration::get(Config::PAYMENT_FEE_FRONTEND_LABEL));
        self::assertFalse(\Configuration::get('PAYMENT_FEE_MODE'));
        self::assertFalse(\Configuration::get('PAYMENT_FEE_FRONTEND_LABEL'));
    }

    public function testKeepsAValueAlreadyStoredUnderTheNewKey(): void
    {
        \Configuration::$values['PAYMENT_FEE_FRONTEND_LABEL'] = 'Old label';
        \Configuration::$values[Config::PAYMENT_FEE_FRONTEND_LABEL] = 'New label';

        self::assertSame(0, (new ConfigurationKeyMigrator())->migrate());

        self::assertSame('New label', \Configuration::get(Config::PAYMENT_FEE_FRONTEND_LABEL));
        self::assertFalse(\Configuration::get('PAYMENT_FEE_FRONTEND_LABEL'), 'the old key is removed either way');
    }

    public function testDoesNothingOnAFreshInstall(): void
    {
        self::assertSame(0, (new ConfigurationKeyMigrator())->migrate());
        self::assertSame([], \Configuration::$values);
    }

    public function testAcceptsAnExplicitRenameMap(): void
    {
        \Configuration::$values['OLD'] = '1';

        self::assertSame(1, (new ConfigurationKeyMigrator(['OLD' => 'BUCKAROO_NEW']))->migrate());
        self::assertSame(['BUCKAROO_NEW' => '1'], \Configuration::$values);
    }
}
