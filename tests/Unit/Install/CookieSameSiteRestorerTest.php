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

use Buckaroo\PrestaShop\Src\Install\CookieSameSiteRestorer;
use PHPUnit\Framework\TestCase;

/**
 * The module must not change PS_COOKIE_SAMESITE; it only undoes the None that
 * versions before 5.4.0 set, once, on upgrade or uninstall.
 */
final class CookieSameSiteRestorerTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    /** @var array<int, array{id_configuration: int, id_shop_group: int|null, id_shop: int|null, value: string}> */
    private $rows = [];

    /** @var string[] */
    private $logged = [];

    protected function setUp(): void
    {
        \Configuration::$values = [];
        $this->rows = [];
        $this->logged = [];
    }

    public function testInstallMarksTheSettingAsHandledWithoutChangingIt(): void
    {
        \Configuration::$values[CookieSameSiteRestorer::SETTING] = 'Strict';
        $this->rows = $this->keyed([$this->row(1, null, null, 'None')]);

        $this->restorer()->markHandled();

        self::assertSame('Strict', \Configuration::get(CookieSameSiteRestorer::SETTING));
        self::assertSame('None', $this->rows[1]['value']);
        self::assertSame(1, \Configuration::get(CookieSameSiteRestorer::MARKER));
    }

    public function testUpgradeRestoresLaxOnEveryRowTheModuleSetToNone(): void
    {
        $this->rows = $this->keyed([
            $this->row(1, null, null, 'None'),
            $this->row(2, 1, null, 'None'),
            $this->row(3, 1, 2, 'None'),
            $this->row(4, 1, 3, 'Strict'),
        ]);

        self::assertSame(3, $this->restorer()->restore());

        self::assertSame('Lax', $this->rows[1]['value']);
        self::assertSame('Lax', $this->rows[2]['value']);
        self::assertSame('Lax', $this->rows[3]['value']);
        self::assertSame('Strict', $this->rows[4]['value']);
        self::assertCount(3, $this->logged);
        self::assertStringContainsString('from None to Lax (shop group 1, shop 2)', $this->logged[2]);
        self::assertSame(1, \Configuration::get(CookieSameSiteRestorer::MARKER));
    }

    public function testRestoreRunsOnlyOnceSoALaterMerchantChoiceIsKept(): void
    {
        $this->rows = $this->keyed([$this->row(1, null, null, 'None')]);
        $this->restorer()->restore();

        // The merchant sets None again after the upgrade.
        $this->rows[1]['value'] = 'None';

        self::assertSame(0, $this->restorer()->restore());
        self::assertSame('None', $this->rows[1]['value']);
    }

    public function testNothingIsChangedOnAShopThatHasTheDefault(): void
    {
        $this->rows = $this->keyed([$this->row(1, null, null, 'Lax')]);

        self::assertSame(0, $this->restorer()->restore());
        self::assertSame('Lax', $this->rows[1]['value']);
        self::assertSame([], $this->logged);
    }

    public function testForgetRemovesTheMarker(): void
    {
        $restorer = $this->restorer();
        $restorer->markHandled();
        $restorer->forget();

        self::assertFalse(\Configuration::get(CookieSameSiteRestorer::MARKER));
    }

    public function testNoShippedCodeWritesTheCookieSameSiteSetting(): void
    {
        $offenders = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator(self::ROOT, \FilesystemIterator::SKIP_DOTS),
            static function (\SplFileInfo $file): bool {
                return !in_array($file->getFilename(), ['vendor', 'tests', 'node_modules', '.git', 'dev', 'build'], true);
            }
        ));

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php' || $file->getFilename() === 'CookieSameSiteRestorer.php') {
                continue;
            }
            if (strpos((string) file_get_contents($file->getPathname()), 'PS_COOKIE_SAMESITE') !== false) {
                $offenders[] = $file->getPathname();
            }
        }

        self::assertSame([], $offenders, "Only CookieSameSiteRestorer may touch PS_COOKIE_SAMESITE:\n" . implode("\n", $offenders));
    }

    /**
     * @param array<int, array{id_configuration: int, id_shop_group: int|null, id_shop: int|null, value: string}> $rows
     *
     * @return array<int, array{id_configuration: int, id_shop_group: int|null, id_shop: int|null, value: string}>
     */
    private function keyed(array $rows): array
    {
        return array_column($rows, null, 'id_configuration');
    }

    private function restorer(): CookieSameSiteRestorer
    {
        return new CookieSameSiteRestorer(
            function (): array {
                return array_values(array_filter($this->rows, static function (array $row): bool {
                    return $row['value'] === 'None';
                }));
            },
            function (int $idConfiguration): void {
                $this->rows[$idConfiguration]['value'] = 'Lax';
            },
            function (string $message): void {
                $this->logged[] = $message;
            }
        );
    }

    /**
     * @return array{id_configuration: int, id_shop_group: int|null, id_shop: int|null, value: string}
     */
    private function row(int $id, ?int $idShopGroup, ?int $idShop, string $value): array
    {
        return ['id_configuration' => $id, 'id_shop_group' => $idShopGroup, 'id_shop' => $idShop, 'value' => $value];
    }
}
