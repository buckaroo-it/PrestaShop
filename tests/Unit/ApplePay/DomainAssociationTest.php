<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Tests\Unit\ApplePay;

use Buckaroo\PrestaShop\Src\ApplePay\DomainAssociation;
use PHPUnit\Framework\TestCase;

final class DomainAssociationTest extends TestCase
{
    /** @var string */
    private $root;

    /** @var string */
    private $source;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/buckaroo3-applepay-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/module', 0777, true);
        $this->source = $this->root . '/module/' . DomainAssociation::FILE_NAME;
        file_put_contents($this->source, 'buckaroo-association');
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function testInstallsTheFileInWellKnown(): void
    {
        $association = new DomainAssociation($this->root . '/shop', $this->source);

        self::assertSame(DomainAssociation::STATUS_INSTALLED, $association->install());
        self::assertSame($this->root . '/shop/.well-known/' . DomainAssociation::FILE_NAME, $association->getDestinationFile());
        self::assertSame('buckaroo-association', file_get_contents($association->getDestinationFile()));
    }

    public function testReportsAFileThatIsAlreadyInPlace(): void
    {
        $association = new DomainAssociation($this->root . '/shop', $this->source);
        $association->install();

        self::assertSame(DomainAssociation::STATUS_PRESENT, $association->install());
    }

    public function testNeverReplacesTheFileOfAnotherProvider(): void
    {
        mkdir($this->root . '/shop/.well-known', 0777, true);
        $destination = $this->root . '/shop/.well-known/' . DomainAssociation::FILE_NAME;
        file_put_contents($destination, 'other-provider');

        $association = new DomainAssociation($this->root . '/shop', $this->source);

        self::assertSame(DomainAssociation::STATUS_OTHER_FILE, $association->install());
        self::assertSame('other-provider', file_get_contents($destination));
    }

    public function testFailsWithoutSourceFile(): void
    {
        $association = new DomainAssociation($this->root . '/shop', $this->root . '/missing');

        self::assertSame(DomainAssociation::STATUS_FAILED, $association->install());
        self::assertFileDoesNotExist($association->getDestinationFile());
    }

    public function testTheShippedFileIsBuckaroosAssociationFile(): void
    {
        $content = (string) file_get_contents(__DIR__ . '/../../../views/apple/' . DomainAssociation::FILE_NAME);

        // Apple compares the bytes: hex text without a line break, as Buckaroo supplies it.
        self::assertMatchesRegularExpression('/^[0-9A-F]+$/', $content);
        self::assertStringStartsWith('{"pspId":"', (string) hex2bin(substr($content, 0, 20)));
    }

    private function remove(string $path): void
    {
        if (is_dir($path)) {
            foreach (scandir($path) as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    $this->remove($path . '/' . $entry);
                }
            }
            rmdir($path);
        } elseif (file_exists($path)) {
            unlink($path);
        }
    }
}
