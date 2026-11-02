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

namespace Buckaroo\PrestaShop\Tests\Unit\Module;

use PHPUnit\Framework\TestCase;

/**
 * Guards the module metadata the PrestaShop Validator and Module Manager rely on.
 *
 * The module class cannot be instantiated without a PrestaShop runtime, so these
 * tests assert on the source: the validator inspects the source too.
 */
final class ModuleMetadataTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    public function testVersionIsAStringLiteral(): void
    {
        $source = $this->moduleSource();

        self::assertMatchesRegularExpression(
            "/^\s*\\\$this->version = '\d+\.\d+\.\d+';\s*$/m",
            $source,
            '$this->version must be assigned a MAJOR.MINOR.PATCH string literal (validator requirement)'
        );
        self::assertStringNotContainsString('$this->version = self::MODULE_VERSION', $source);
    }

    public function testVersionDeclarationsAgree(): void
    {
        $source = $this->moduleSource();

        self::assertSame(1, preg_match("/const MODULE_VERSION = '([^']+)';/", $source, $constant));
        self::assertSame(1, preg_match("/\\\$this->version = '([^']+)';/", $source, $property));

        $config = simplexml_load_file(self::ROOT . '/config.xml');
        self::assertNotFalse($config);
        $composer = json_decode((string) file_get_contents(self::ROOT . '/composer.json'), true);

        $version = $constant[1];
        self::assertSame($version, $property[1], '$this->version differs from MODULE_VERSION');
        self::assertSame($version, (string) $config->version, 'config.xml version differs from the module');
        self::assertSame($version, (string) $composer['version'], 'composer.json version differs from the module');
        self::assertStringContainsString(
            '## v' . $version,
            (string) file_get_contents(self::ROOT . '/CHANGELOG.md'),
            'CHANGELOG.md has no section for the current version'
        );
    }

    public function testDisplayNameIsStableAndStaticallyTranslatable(): void
    {
        $source = $this->moduleSource();

        self::assertSame(
            1,
            preg_match_all('/\$this->displayName\s*=/', $source),
            'displayName must be assigned exactly once (in initializeModuleInfo)'
        );
        self::assertStringContainsString("\$this->displayName = \$this->l('Buckaroo Payments');", $source);
        self::assertStringNotContainsString("l('Buckaroo Payments (v", $source);

        $config = simplexml_load_file(self::ROOT . '/config.xml');
        self::assertSame('Buckaroo Payments', (string) $config->displayName);
        self::assertSame('buckaroo3', (string) $config->name);
    }

    public function testNoRuntimeCodeMutatesTheDisplayName(): void
    {
        foreach ($this->firstPartyPhpFiles(['api', 'classes', 'controllers', 'library', 'src', 'upgrade']) as $file) {
            self::assertStringNotContainsString(
                '->displayName =',
                (string) file_get_contents($file),
                basename($file) . ' must not assign the module display name'
            );
        }
    }

    public function testAuthorAndTechnicalMetadata(): void
    {
        $source = $this->moduleSource();
        $config = simplexml_load_file(self::ROOT . '/config.xml');

        self::assertStringContainsString("\$this->author = 'Buckaroo';", $source);
        self::assertSame('Buckaroo', (string) $config->author);
        self::assertStringContainsString("\$this->name = 'buckaroo3';", $source);
        self::assertStringContainsString("\$this->tab = 'payments_gateways';", $source);
        self::assertSame('payments_gateways', (string) $config->tab);
        self::assertMatchesRegularExpression("/\\\$this->module_key = '[a-f0-9]{32}';/", $source);
        self::assertStringContainsString("\$this->ps_versions_compliancy = ['min' => '1.7.8.0', 'max' => '9.2.99'];", $source, '1.7.8 is the first PrestaShop release on PHP 7.4, the module minimum');
        self::assertStringContainsString('Compatible with PrestaShop 1.7.8 up to 9.2.x', (string) $config->description);
        self::assertStringContainsString("\$this->description = \$this->l('" . (string) $config->description . "');", $source, 'the validator requires $this->description to equal the config.xml description');
        self::assertSame('1', (string) $config->need_instance);
    }

    public function testTranslationsDoNotConcatenateDynamicContent(): void
    {
        self::assertDoesNotMatchRegularExpression(
            "/->l\('[^']*'\s*\.\s*[^)]/",
            $this->moduleSource(),
            'Dynamic content must not be concatenated inside $this->l() (breaks translation extraction)'
        );
    }

    public function testConstructorDoesNotInspectPaymentResponses(): void
    {
        $source = $this->moduleSource();

        self::assertSame(1, preg_match('/public function __construct\(\)\s*\{(.*?)\n    \}/s', $source, $constructor));
        self::assertStringNotContainsString('ResponseFactory', $constructor[1], 'the constructor must not parse push/return payloads');
        self::assertStringNotContainsString('displayName', $constructor[1]);
        self::assertStringNotContainsString('function initializeDisplayName', $source);
        self::assertStringContainsString('public function getPaymentLabel(', $source);
    }

    public function testConfigurationPageShowsTheVersionInsteadOfTheModuleName(): void
    {
        $template = (string) file_get_contents(self::ROOT . '/views/templates/admin/app.tpl');

        self::assertStringContainsString("{\$moduleVersion|escape:'htmlall':'UTF-8'}", $template);
        self::assertStringContainsString("'moduleVersion' => \$this->version", $this->moduleSource());
    }

    private function moduleSource(): string
    {
        return (string) file_get_contents(self::ROOT . '/buckaroo3.php');
    }

    /**
     * @param string[] $directories
     *
     * @return string[]
     */
    private function firstPartyPhpFiles(array $directories): array
    {
        $files = [];
        foreach ($directories as $directory) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT . '/' . $directory));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
