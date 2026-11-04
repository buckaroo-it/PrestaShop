<?php

namespace BuckarooTest\Unit\ExpressAndGiftcard;

use Buckaroo\PrestaShop\Src\ApplePay\DomainAssociation;
use Buckaroo\PrestaShop\Src\ApplePay\ExpressSettings;
use PHPUnit\Framework\TestCase;

/**
 * Cases of the Apple Pay settings and domain association file that
 * tests/Unit/ApplePay does not cover.
 */
class ApplePaySettingsTopUpTest extends TestCase
{
    /** @var string */
    private $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/buckaroo-apple-' . uniqid('', true);
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->dir);
    }

    public function testForModuleInstallsTheModuleFileInTheShopRoot(): void
    {
        $association = DomainAssociation::forModule('/modules/buckaroo3/');

        self::assertSame(_PS_ROOT_DIR_ . '/.well-known/' . DomainAssociation::FILE_NAME, $association->getDestinationFile());
    }

    public function testInstallFailsWhenTheWellKnownFolderCannotBeCreated(): void
    {
        file_put_contents($this->dir . '/source', 'apple-file');
        // A file where the shop root should be: .well-known cannot be created below it.
        file_put_contents($this->dir . '/root', 'not a directory');

        $association = new DomainAssociation($this->dir . '/root', $this->dir . '/source');

        self::assertSame(DomainAssociation::STATUS_FAILED, $association->install());
    }

    public function testSanitizeResetsAnUnknownButtonType(): void
    {
        $data = ExpressSettings::sanitize(['button_type' => 'donate', 'button_style' => 'white']);

        self::assertSame('plain', $data['button_type']);
        self::assertSame('white', $data['button_style']);
    }
}
