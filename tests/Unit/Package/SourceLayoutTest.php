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

namespace Buckaroo\PrestaShop\Tests\Unit\Package;

use PHPUnit\Framework\TestCase;

/**
 * Structure and licence rules the PrestaShop Validator enforces on the shipped
 * files, checked against the working tree. The release ZIP itself is checked by
 * .github/scripts/validate-package.php.
 */
final class SourceLayoutTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    /** Directories that are not part of the release package. */
    private const NOT_SHIPPED = ['vendor', 'dev', 'tests', 'build', '.git', '.github', '.idea', 'node_modules'];

    /** Files that are export-ignored (see .gitattributes). */
    private const NOT_SHIPPED_FILES = ['.php-cs-fixer.dist.php', '.php-cs-fixer.cache', 'phpunit.xml', '.gitattributes', '.gitignore', '.phpunit.result.cache', 'sonar-project.properties', 'README.md', 'RELEASE.md', 'CONTRIBUTING.md', 'composer.lock', 'scoper.inc.php', 'views/img/buckaroo/README.md'];

    public function testEveryShippedDirectoryHasAnIndexFile(): void
    {
        $missing = [];
        foreach ($this->shippedDirectories() as $directory) {
            if (!is_file($directory . '/index.php')) {
                $missing[] = $this->relative($directory);
            }
        }

        self::assertSame([], $missing, "Directories without index.php:\n" . implode("\n", $missing));
    }

    public function testEveryFirstPartyPhpFileHasTheExecutionGuard(): void
    {
        $missing = [];
        foreach ($this->shippedFiles(['php']) as $file) {
            $relative = $this->relative($file);
            if (basename($file) === 'index.php') {
                continue;
            }
            if (strpos((string) file_get_contents($file), "defined('_PS_VERSION_')") === false) {
                $missing[] = $relative;
            }
        }

        self::assertSame([], $missing, "PHP files without the _PS_VERSION_ guard:\n" . implode("\n", $missing));
    }

    public function testEveryFirstPartyFileHasTheLicenseHeader(): void
    {
        $missing = [];
        foreach ($this->shippedFiles(['php', 'js', 'tpl']) as $file) {
            $relative = $this->relative($file);
            if (preg_match('#^translations/[a-z]{2}\.php$#', $relative)) {
                continue;
            }
            $head = (string) file_get_contents($file, false, null, 0, 2000);
            if (strpos($head, 'NOTICE OF LICENSE') === false || strpos($head, '@license') === false) {
                $missing[] = $relative;
            }
        }

        self::assertSame([], $missing, "Files without the Buckaroo license header:\n" . implode("\n", $missing));
    }

    public function testGeneratedBundlesKeepTheLicenseHeaderAsALegalComment(): void
    {
        $bundles = glob(self::ROOT . '/views/js/*.js') ?: [];
        self::assertNotEmpty($bundles);

        foreach ($bundles as $bundle) {
            $head = (string) file_get_contents($bundle, false, null, 0, 600);
            self::assertStringContainsString('NOTICE OF LICENSE', $head, basename($bundle));
            if (preg_match('#(vendor|i18n)-[A-Za-z0-9_-]+\.js$#', $bundle) || basename($bundle) === 'buckaroo.vue.js') {
                self::assertStringStartsWith('/*!', $head, basename($bundle) . ' must start with a legal comment so minifiers keep it');
            }
        }

        $viteConfig = (string) file_get_contents(self::ROOT . '/dev/vite.config.js');
        self::assertStringContainsString('buckaroo-license-banner', $viteConfig, 'the Vite build must stamp the header on every bundle');
    }

    public function testNoStaticContextLookups(): void
    {
        $offenders = [];
        foreach ($this->shippedFiles(['php']) as $file) {
            if (preg_match('/\bContext::getContext\s*\(/', (string) file_get_contents($file))) {
                $offenders[] = $this->relative($file);
            }
        }

        self::assertSame([], $offenders, "Static Context lookups:\n" . implode("\n", $offenders));
    }

    public function testDevelopmentFilesAreExcludedFromTheReleaseArchive(): void
    {
        $attributes = (string) file_get_contents(self::ROOT . '/.gitattributes');

        foreach (['/tests', '/dev', '/.github', '/phpunit.xml', '/.gitignore', '/.gitattributes', '/sonar-project.properties', '/.php-cs-fixer.dist.php', '/.phpunit.result.cache', '/README.md', '/RELEASE.md', '/CONTRIBUTING.md', '/scoper.inc.php', '/views/img/buckaroo/README.md'] as $path) {
            self::assertMatchesRegularExpression(
                '#^' . preg_quote($path, '#') . '\s+export-ignore#m',
                $attributes,
                $path . ' must be export-ignored'
            );
        }
    }

    public function testVendorIsBuiltIntoTheReleaseInsteadOfBeingCommitted(): void
    {
        self::assertMatchesRegularExpression('#^/vendor\s*$#m', (string) file_get_contents(self::ROOT . '/.gitignore'), 'vendor/ must be git-ignored');
        self::assertFileExists(self::ROOT . '/composer.lock', 'composer.lock pins the shipped dependency versions');

        $composer = json_decode((string) file_get_contents(self::ROOT . '/composer.json'), true);
        self::assertSame('7.4', $composer['config']['platform']['php'] ?? null, 'dependencies must resolve for PHP 7.4 regardless of the PHP running Composer');

        $build = (string) file_get_contents(self::ROOT . '/.github/scripts/build-release.sh');
        self::assertMatchesRegularExpression('/composer install [^\n]*--no-dev/', $build, 'the release build must install production dependencies');
        self::assertStringContainsString('--classmap-authoritative', $build);
        self::assertStringContainsString('rm -rf "$EXPORT/vendor"', $build, 'a stale vendor/ in the export must never be reused');
        self::assertStringContainsString('rm -f "$EXPORT/composer.lock"', $build, 'composer.lock must not ship');
        self::assertMatchesRegularExpression('/find "\$EXPORT\/vendor" [^\n]*-name \'\.\*\' ! -name \'\.htaccess\'/', $build, 'dotfiles shipped by Composer packages must be stripped from vendor/');
        self::assertMatchesRegularExpression('/find "\$EXPORT\/vendor" -type d [^\n]*index\.php/', $build, 'every vendor/ directory must receive the index.php stub');
    }

    public function testThirdPartyDependenciesArePrefixedInTheRelease(): void
    {
        $build = (string) file_get_contents(self::ROOT . '/.github/scripts/build-release.sh');
        self::assertMatchesRegularExpression('/^PHP_SCOPER_VERSION=\d+\.\d+\.\d+\r?$/m', $build, 'php-scoper must be pinned to a release');
        self::assertMatchesRegularExpression('/^PHP_SCOPER_SHA256=[0-9a-f]{64}\r?$/m', $build, 'the php-scoper phar must be verified against a pinned checksum');
        self::assertStringContainsString('add-prefix vendor', $build);
        self::assertStringContainsString('composer dump-autoload --working-dir="$EXPORT" --no-dev --classmap-authoritative', $build, 'the autoloader must be rebuilt for the prefixed class names');

        $config = require self::ROOT . '/scoper.inc.php';
        self::assertSame('Buckaroo\\PrestaShop\\Vendor', $config['prefix']);
        self::assertContains('Buckaroo', $config['exclude-namespaces'], 'the Buckaroo SDK keeps its namespace; the module refers to it directly');
        self::assertContains('Symfony\\Polyfill', $config['exclude-namespaces']);
        self::assertFalse($config['expose-global-functions'], 'no scoper-autoload.php aliases: the module loads vendor/autoload.php');
    }

    public function testModuleRootDeniesDirectPhpAccessForApache22And24(): void
    {
        $htaccess = (string) file_get_contents(self::ROOT . '/.htaccess');

        self::assertStringContainsString('Options -Indexes', $htaccess);
        self::assertMatchesRegularExpression('/<IfModule mod_authz_core\.c>\s*<Files \*\.php>\s*Require all denied/', $htaccess, 'Apache 2.4 rule');
        self::assertMatchesRegularExpression('/<IfModule !mod_authz_core\.c>\s*<Files \*\.php>\s*Order allow,deny\s*Deny from all/', $htaccess, 'Apache 2.2 rule');
    }

    public function testApiDirectoryIsNotWebAccessibleOnApache22And24(): void
    {
        $htaccess = (string) file_get_contents(self::ROOT . '/api/.htaccess');

        self::assertMatchesRegularExpression('/<IfModule mod_authz_core\.c>\s*Require all denied/', $htaccess, 'Apache 2.4 rule');
        self::assertMatchesRegularExpression('/<IfModule !mod_authz_core\.c>\s*Order allow,deny\s*Deny from all/', $htaccess, 'Apache 2.2 rule');
    }

    public function testNoHiddenFilesShipOtherThanHtaccess(): void
    {
        $hidden = [];
        foreach ($this->iterator() as $file) {
            $name = $file->getFilename();
            if ($name[0] === '.' && $name !== '.htaccess') {
                $hidden[] = $this->relative($file->getPathname());
            }
        }

        self::assertSame([], $hidden, "Hidden files in the shipped tree:\n" . implode("\n", $hidden));
    }

    public function testLegacyRootLogoGifIsReplacedByTheOrderStateIcon(): void
    {
        self::assertFileDoesNotExist(self::ROOT . '/logo.gif', 'logo.gif is a PrestaShop 1.4 leftover; only logo.png is the module logo');
        self::assertFileExists(self::ROOT . '/views/img/order-state.gif', 'the icon copied to img/os/ for the Buckaroo order state');
        self::assertStringContainsString("'/views/img/order-state.gif'", (string) file_get_contents(self::ROOT . '/buckaroo3.php'));
    }

    public function testLogDirectoryIsNotWebAccessible(): void
    {
        self::assertFileExists(self::ROOT . '/api/log/index.php');
        self::assertFileExists(self::ROOT . '/api/log/.htaccess');
        self::assertMatchesRegularExpression('/deny/i', (string) file_get_contents(self::ROOT . '/api/log/.htaccess'));
    }

    /**
     * @return string[]
     */
    private function shippedDirectories(): array
    {
        $directories = [];
        foreach ($this->iterator() as $file) {
            if ($file->isDir()) {
                $directories[] = $file->getPathname();
            }
        }
        sort($directories);

        return $directories;
    }

    /**
     * @param string[] $extensions
     *
     * @return string[]
     */
    private function shippedFiles(array $extensions): array
    {
        $files = [];
        foreach ($this->iterator() as $file) {
            if ($file->isFile() && in_array(strtolower($file->getExtension()), $extensions, true)) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    private function iterator(): \RecursiveIteratorIterator
    {
        $root = realpath(self::ROOT);

        return new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                static function (\SplFileInfo $file) use ($root): bool {
                    $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');
                    if (!$file->isDir()) {
                        return !in_array($relative, self::NOT_SHIPPED_FILES, true);
                    }

                    return !in_array(explode('/', $relative)[0], self::NOT_SHIPPED, true);
                }
            ),
            \RecursiveIteratorIterator::SELF_FIRST
        );
    }

    private function relative(string $path): string
    {
        return ltrim(str_replace('\\', '/', substr($path, strlen((string) realpath(self::ROOT)))), '/');
    }
}
