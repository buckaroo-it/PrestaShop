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

use Buckaroo\PrestaShop\Src\Install\GooglePayMerchantNameRemover;
use PHPUnit\Framework\TestCase;

/**
 * BTI-2009: the upgrade removes the Google Pay Merchant name stored by earlier versions.
 */
final class GooglePayMerchantNameRemoverTest extends TestCase
{
    public function testTheStoredMerchantNameIsRemovedAndTheOtherSettingsKept(): void
    {
        $db = $this->db([
            ['id' => 7, 'value' => json_encode(['mode' => 'live', 'merchantName' => 'My Shop', 'gatewayMerchantId' => 'ABC123'])],
        ]);

        self::assertSame(1, (new GooglePayMerchantNameRemover($db))->remove());
        self::assertCount(1, $db->updates);
        self::assertSame('bk_configuration', $db->updates[0]['table']);
        self::assertSame('`id` = 7', $db->updates[0]['where']);
        self::assertSame(['mode' => 'live', 'gatewayMerchantId' => 'ABC123'], json_decode(stripslashes($db->updates[0]['data']['value']), true));
        self::assertStringContainsString("m.`name` = 'googlepay'", $db->query);
    }

    public function testNothingIsWrittenWithoutAStoredMerchantName(): void
    {
        $db = $this->db([
            ['id' => 7, 'value' => json_encode(['mode' => 'test', 'gatewayMerchantId' => 'ABC123'])],
            ['id' => 8, 'value' => 'not json'],
        ]);

        self::assertSame(0, (new GooglePayMerchantNameRemover($db))->remove());
        self::assertSame([], $db->updates);
    }

    public function testAMissingGooglePayConfigurationIsFine(): void
    {
        self::assertSame(0, (new GooglePayMerchantNameRemover($this->db(false)))->remove());
    }

    /**
     * @param array<int, array<string, mixed>>|false $rows
     */
    private function db($rows)
    {
        return new class($rows) {
            /** @var array<int, array<string, mixed>>|false */
            private $rows;
            /** @var string */
            public $query = '';
            /** @var array<int, array<string, mixed>> */
            public $updates = [];

            public function __construct($rows)
            {
                $this->rows = $rows;
            }

            public function executeS($sql)
            {
                $this->query = (string) $sql;

                return $this->rows;
            }

            public function update($table, $data, $where)
            {
                $this->updates[] = ['table' => $table, 'data' => $data, 'where' => $where];

                return true;
            }
        };
    }
}
