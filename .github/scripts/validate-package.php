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

/*
 * Local equivalent of the checks the PrestaShop Validator runs on a module ZIP.
 *
 *   php .github/scripts/validate-package.php zip build/buckaroo3.zip   # inspect a release ZIP
 *   php .github/scripts/validate-package.php source [module-dir]        # inspect the working tree
 *
 * Exit code 0 when every check passes, 1 otherwise. Keep this file free of
 * PrestaShop dependencies: it runs on a plain PHP CLI in CI and locally.
 */

declare(strict_types=1);

const MODULE_NAME = 'buckaroo3';

/**
 * Paths (relative to the module root) that must never ship. Prefix matches
 * directories; exact matches cover files. Keep in sync with .gitattributes.
 * vendor/ is not tracked in git: build-release.sh installs the production
 * packages from composer.lock, so the dev-package entries below guard the ZIP.
 */
const FORBIDDEN_PATHS = [
    'tests/', 'dev/', '.github/', '.git/', '.idea/', 'node_modules/', 'build/',
    '.gitignore', '.gitattributes', '.phpunit.result.cache', '.php-cs-fixer.dist.php',
    '.php-cs-fixer.cache', 'phpunit.xml', 'phpunit.xml.dist', 'phpstan.neon', 'phpstan.neon.dist',
    'phpstan-baseline.neon', 'sonar-project.properties', 'CONTRIBUTING.md', 'RELEASE.md',
    'composer.lock', '.DS_Store', 'Thumbs.db', '.env',
    // pre-5.4.0 log location; logs live in var/logs/buckaroo3/ under the shop root now
    'api/log/',
    // development-only Composer packages that must not leak into the shipped vendor/
    'vendor/phpunit/', 'vendor/phpstan/', 'vendor/prestashop/php-dev-tools/', 'vendor/bin/',
    'vendor/sebastian/', 'vendor/phpspec/', 'vendor/myclabs/', 'vendor/nikic/php-parser/',
];

/** Files that must exist for the module to install and run. */
const REQUIRED_FILES = [
    'buckaroo3.php', 'config.xml', 'index.php', 'logo.png', 'logo.gif', 'composer.json',
    'vendor/autoload.php', 'vendor/composer/autoload_real.php', 'vendor/buckaroo/sdk/composer.json',
    'config/services.yml', 'config/front/services.yml', 'config/routes.yml', 'config/admin/routes.yml',
    'controllers/front/request.php', 'controllers/front/return.php', 'controllers/front/userreturn.php',
    'controllers/admin/AdminRefundController.php', 'api/paymentmethods/paymentmethod.php',
    'library/checkout/checkout.php', 'library/logger.php', 'api/corelogger.php',
    'src/Install/Installer.php', 'src/Service/BuckarooPaymentService.php', 'src/Service/BuckarooFeeService.php',
    'views/js/buckaroo.vue.js', 'views/css/buckaroo3.vue.css', 'views/templates/admin/app.tpl',
    'views/templates/hook/payment-fee-table.tpl', 'mails/en/order_conf.html', 'mails/en/order_conf.txt',
    'translations/en.php', 'upgrade/index.php',
];

/** Directories that are not expected to carry index.php files. */
const INDEX_EXEMPT_PREFIXES = ['vendor/'];

/** PHP files that legitimately have no _PS_VERSION_ guard. */
const GUARD_EXEMPT_PATTERNS = ['#(^|/)index\.php$#', '#^translations/[a-z]{2}\.php$#', '#^vendor/#'];

/** Files that must carry the Buckaroo license header. */
const LICENSE_EXTENSIONS = ['php', 'js', 'tpl'];
const LICENSE_EXEMPT_PATTERNS = ['#^vendor/#', '#^translations/[a-z]{2}\.php$#'];

/** Smarty modifiers the validator accepts as output sanitisation. */
const SMARTY_SAFE_MODIFIERS = ['escape', 'intval', 'floatval', 'boolval', 'json_encode'];

final class ModuleFiles
{
    /** @var array<string, string|null> path => absolute file on disk */
    private $files = [];
    /** @var string */
    private $baseDir;

    public function __construct(string $baseDir, array $relativePaths)
    {
        $this->baseDir = rtrim($baseDir, '/\\');
        foreach ($relativePaths as $path) {
            $this->files[str_replace('\\', '/', $path)] = null;
        }
        ksort($this->files);
    }

    /** @return string[] */
    public function paths(): array
    {
        return array_keys($this->files);
    }

    public function has(string $path): bool
    {
        return isset($this->files[$path]) || array_key_exists($path, $this->files);
    }

    public function read(string $path): string
    {
        $content = @file_get_contents($this->baseDir . '/' . $path);

        return $content === false ? '' : $content;
    }

    public function absolute(string $path): string
    {
        return $this->baseDir . '/' . $path;
    }

    /** @return string[] every directory that contains at least one file */
    public function directories(): array
    {
        $dirs = [];
        foreach ($this->paths() as $path) {
            $dir = dirname($path);
            while ($dir !== '.' && $dir !== '' && $dir !== '/') {
                $dirs[$dir] = true;
                $dir = dirname($dir);
            }
        }
        ksort($dirs);

        return array_keys($dirs);
    }
}

final class Report
{
    /** @var string[] */
    private $failures = [];
    /** @var string[] */
    private $passed = [];

    public function pass(string $check): void
    {
        $this->passed[] = $check;
    }

    public function fail(string $check, array $details = []): void
    {
        $this->failures[] = $check . ($details ? ":\n    - " . implode("\n    - ", $details) : '');
    }

    public function check(string $name, array $problems): void
    {
        if ($problems) {
            $this->fail($name, $problems);
        } else {
            $this->pass($name);
        }
    }

    public function render(): int
    {
        foreach ($this->passed as $check) {
            echo "  [OK]   {$check}\n";
        }
        foreach ($this->failures as $failure) {
            echo "  [FAIL] {$failure}\n";
        }
        $failed = count($this->failures);
        echo "\n" . ($failed ? "{$failed} check(s) failed." : 'All checks passed.') . "\n";

        return $failed ? 1 : 0;
    }
}

function usage(): void
{
    fwrite(STDERR, "Usage:\n  php validate-package.php zip <buckaroo3.zip>\n  php validate-package.php source [module-dir]\n");
    exit(2);
}

function listDirectory(string $dir): array
{
    $paths = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            static function (SplFileInfo $file) {
                // never descend into VCS/tooling folders when inspecting a working tree
                return !in_array($file->getFilename(), ['.git', 'node_modules'], true);
            }
        )
    );
    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if ($file->isFile()) {
            $paths[] = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
        }
    }

    return $paths;
}

function loadZip(string $zipPath, Report $report): ?ModuleFiles
{
    if (!is_file($zipPath)) {
        $report->fail('ZIP exists', ["{$zipPath} not found"]);

        return null;
    }
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        $report->fail('ZIP opens', ["unable to open {$zipPath}"]);

        return null;
    }

    $entries = [];
    $roots = [];
    for ($i = 0; $i < $zip->numFiles; ++$i) {
        $name = $zip->getNameIndex($i);
        $root = explode('/', $name, 2)[0];
        $roots[$root] = true;
        if (substr($name, -1) !== '/') {
            $entries[] = $name;
        }
    }

    $problems = [];
    if (array_keys($roots) !== [MODULE_NAME]) {
        $problems[] = 'expected a single top-level directory "' . MODULE_NAME . '/", found: ' . implode(', ', array_keys($roots));
    }
    foreach ($entries as $entry) {
        if (preg_match('#^' . MODULE_NAME . '/(' . MODULE_NAME . '|PrestaShop-[^/]+)/#', $entry)) {
            $problems[] = 'nested module root: ' . $entry;
            break;
        }
    }
    $report->check('ZIP root is exactly ' . MODULE_NAME . '/', $problems);

    $extractDir = sys_get_temp_dir() . '/' . MODULE_NAME . '-validate-' . bin2hex(random_bytes(4));
    if (!mkdir($extractDir, 0777, true) || !$zip->extractTo($extractDir)) {
        $report->fail('ZIP extracts', ["could not extract to {$extractDir}"]);

        return null;
    }
    $zip->close();
    register_shutdown_function(static function () use ($extractDir) {
        removeDirectory($extractDir);
    });

    $relative = [];
    foreach ($entries as $entry) {
        if (strpos($entry, MODULE_NAME . '/') === 0) {
            $relative[] = substr($entry, strlen(MODULE_NAME) + 1);
        }
    }

    return new ModuleFiles($extractDir . '/' . MODULE_NAME, $relative);
}

function removeDirectory(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
}

function matchesAny(string $path, array $patterns): bool
{
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $path)) {
            return true;
        }
    }

    return false;
}

function checkForbiddenPaths(ModuleFiles $files, Report $report): void
{
    $problems = [];
    foreach ($files->paths() as $path) {
        foreach (FORBIDDEN_PATHS as $forbidden) {
            $isDir = substr($forbidden, -1) === '/';
            if (($isDir && strpos($path, $forbidden) === 0) || (!$isDir && $path === $forbidden)) {
                $problems[] = $path;
                break;
            }
        }
        if (preg_match('#(^|/)(\.DS_Store|Thumbs\.db)$#', $path)) {
            $problems[] = $path;
        }
    }
    $report->check('No development-only files', array_slice(array_unique($problems), 0, 50));
}

function checkRequiredFiles(ModuleFiles $files, Report $report, bool $includeVendor): void
{
    $problems = [];
    foreach (REQUIRED_FILES as $required) {
        // vendor/ is not tracked in git; build-release.sh installs it from composer.lock.
        if (!$includeVendor && strpos($required, 'vendor/') === 0) {
            continue;
        }
        if (!$files->has($required)) {
            $problems[] = $required;
        }
    }
    $report->check('Required runtime files present', $problems);
}

function checkVendorIsProductionOnly(ModuleFiles $files, Report $report): void
{
    $problems = [];
    if ($files->has('vendor/composer/installed.json')) {
        $installed = json_decode($files->read('vendor/composer/installed.json'), true);
        if (is_array($installed) && !empty($installed['dev-package-names'])) {
            $problems[] = 'vendor/ was generated with dev dependencies (run composer install --no-dev): '
                . implode(', ', array_slice($installed['dev-package-names'], 0, 5));
        }
        $packages = is_array($installed) && isset($installed['packages']) ? $installed['packages'] : (array) $installed;
        foreach ($packages as $package) {
            if (isset($package['name']) && preg_match('#^(phpunit|phpstan|prestashop/php-dev-tools)#', $package['name'])) {
                $problems[] = 'dev package installed: ' . $package['name'];
            }
        }
    }
    $report->check('vendor/ contains production dependencies only', $problems);
}

function checkFrontendChunks(ModuleFiles $files, Report $report): void
{
    $problems = [];
    $entry = $files->read('views/js/buckaroo.vue.js');
    if ($entry === '') {
        $problems[] = 'views/js/buckaroo.vue.js is empty or missing';
    } elseif (preg_match_all('#from"\./((?:vendor|i18n)-[A-Za-z0-9_-]+\.js)"#', $entry, $matches)) {
        foreach (array_unique($matches[1]) as $chunk) {
            if (!$files->has('views/js/' . $chunk)) {
                $problems[] = "buckaroo.vue.js imports views/js/{$chunk} which is not shipped";
            }
        }
    }
    foreach ($files->paths() as $path) {
        if (preg_match('#^views/js/(vendor|i18n)-[A-Za-z0-9_-]+\.js$#', $path) && strpos($entry, basename($path)) === false) {
            $problems[] = "stale chunk not referenced by buckaroo.vue.js: {$path}";
        }
    }
    $report->check('Compiled admin assets are complete and consistent', $problems);
}

function checkIndexFiles(ModuleFiles $files, Report $report): void
{
    $problems = [];
    foreach ($files->directories() as $dir) {
        $exempt = false;
        foreach (INDEX_EXEMPT_PREFIXES as $prefix) {
            if (strpos($dir . '/', $prefix) === 0) {
                $exempt = true;
            }
        }
        if (!$exempt && !$files->has($dir . '/index.php')) {
            $problems[] = $dir;
        }
    }
    if (!$files->has('index.php')) {
        $problems[] = '(module root)';
    }
    $report->check('Every shipped directory has an index.php', $problems);
}

function checkPhpGuards(ModuleFiles $files, Report $report): void
{
    $problems = [];
    foreach ($files->paths() as $path) {
        if (substr($path, -4) !== '.php' || matchesAny($path, GUARD_EXEMPT_PATTERNS)) {
            continue;
        }
        if (strpos($files->read($path), "defined('_PS_VERSION_')") === false) {
            $problems[] = $path;
        }
    }
    $report->check('Direct-access guard (_PS_VERSION_) in every PHP file', $problems);
}

function checkLicenseHeaders(ModuleFiles $files, Report $report): void
{
    $problems = [];
    foreach ($files->paths() as $path) {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extension, LICENSE_EXTENSIONS, true) || matchesAny($path, LICENSE_EXEMPT_PATTERNS)) {
            continue;
        }
        $head = substr($files->read($path), 0, 2000);
        if (strpos($head, 'NOTICE OF LICENSE') === false || strpos($head, '@license') === false) {
            $problems[] = $path;
        }
    }
    $report->check('License header on every PHP/JS/TPL file', $problems);
}

function checkVersionConsistency(ModuleFiles $files, Report $report): void
{
    $problems = [];
    $module = $files->read('buckaroo3.php');
    $versions = [];

    if (preg_match("#const MODULE_VERSION = '([^']+)';#", $module, $m)) {
        $versions['buckaroo3.php MODULE_VERSION'] = $m[1];
    } else {
        $problems[] = 'MODULE_VERSION constant not found';
    }
    if (preg_match("#\\\$this->version = '([^']+)';#", $module, $m)) {
        $versions['buckaroo3.php $this->version'] = $m[1];
    } else {
        $problems[] = '$this->version must be assigned a string literal (validator requirement)';
    }
    if (preg_match('#<version><!\[CDATA\[([^\]]+)\]\]></version>#', $files->read('config.xml'), $m)) {
        $versions['config.xml'] = $m[1];
    } else {
        $problems[] = 'config.xml version not found';
    }
    $composer = json_decode($files->read('composer.json'), true);
    if (is_array($composer) && isset($composer['version'])) {
        $versions['composer.json'] = (string) $composer['version'];
    } else {
        $problems[] = 'composer.json version not found';
    }
    if (count(array_unique($versions)) > 1) {
        foreach ($versions as $source => $version) {
            $problems[] = "{$source}: {$version}";
        }
    }
    $version = reset($versions);
    if ($version && !preg_match('#^\d+\.\d+\.\d+$#', (string) $version)) {
        $problems[] = "version '{$version}' is not MAJOR.MINOR.PATCH";
    }
    if ($version && $files->has('CHANGELOG.md') && strpos($files->read('CHANGELOG.md'), '## v' . $version) === false) {
        $problems[] = "CHANGELOG.md has no '## v{$version}' section";
    }
    $report->check('Module version is a literal and identical everywhere' . ($version ? " ({$version})" : ''), $problems);
}

function checkModuleMetadata(ModuleFiles $files, Report $report): void
{
    $problems = [];
    $module = $files->read('buckaroo3.php');
    $config = $files->read('config.xml');

    if (strpos($module, "\$this->displayName = \$this->l('Buckaroo Payments');") === false) {
        $problems[] = "displayName must be exactly \$this->l('Buckaroo Payments')";
    }
    if (preg_match_all('#\$this->displayName\s*=#', $module) > 1) {
        $problems[] = 'displayName is assigned more than once (it must stay stable at runtime)';
    }
    if (strpos($config, '<displayName><![CDATA[Buckaroo Payments]]></displayName>') === false) {
        $problems[] = 'config.xml displayName must be "Buckaroo Payments"';
    }
    if (strpos($module, "\$this->author = 'Buckaroo';") === false || strpos($config, '<author><![CDATA[Buckaroo]]></author>') === false) {
        $problems[] = 'author must be "Buckaroo" in buckaroo3.php and config.xml';
    }
    if (strpos($module, "\$this->name = '" . MODULE_NAME . "';") === false || strpos($config, '<name>' . MODULE_NAME . '</name>') === false) {
        $problems[] = 'technical name mismatch';
    }
    $report->check('Module metadata (name, display name, author)', $problems);
}

function checkNoStaticContext(ModuleFiles $files, Report $report): void
{
    $problems = [];
    foreach ($files->paths() as $path) {
        if (substr($path, -4) !== '.php' || strpos($path, 'vendor/') === 0) {
            continue;
        }
        $lines = preg_split('/\R/', $files->read($path));
        foreach ($lines as $number => $line) {
            if (preg_match('#\bContext::getContext\s*\(#', $line)) {
                $problems[] = $path . ':' . ($number + 1);
            }
        }
    }
    $report->check('No static Context::getContext() calls', $problems);
}

function checkSmartyEscaping(ModuleFiles $files, Report $report): void
{
    $problems = [];
    foreach ($files->paths() as $path) {
        if (substr($path, -4) !== '.tpl') {
            continue;
        }
        $problems = array_merge($problems, smartyProblems($path, $files->read($path)));
    }
    $report->check('Smarty output is escaped (no number_format/string_format/raw variables)', $problems);
}

/** @return string[] */
function smartyProblems(string $path, string $template): array
{
    $problems = [];
    // Drop Smarty comments and literal blocks before scanning output tags.
    $template = preg_replace('/\{\*.*?\*\}/s', '', $template);
    $template = preg_replace('/\{literal\}.*?\{\/literal\}/s', '', $template);
    $lines = preg_split('/\R/', (string) $template);
    foreach ($lines as $number => $line) {
        if (!preg_match_all('/\{\$[^}]*\}/', $line, $matches)) {
            continue;
        }
        foreach ($matches[0] as $tag) {
            if (preg_match('/\|\s*(number_format|string_format)/', $tag)) {
                $problems[] = sprintf('%s:%d unsupported modifier in %s', $path, $number + 1, $tag);
                continue;
            }
            $safe = false;
            foreach (SMARTY_SAFE_MODIFIERS as $modifier) {
                if (preg_match('/\|\s*' . preg_quote($modifier, '/') . '\b/', $tag)) {
                    $safe = true;
                }
            }
            if (!$safe) {
                $problems[] = sprintf('%s:%d unescaped output %s', $path, $number + 1, $tag);
            }
        }
    }

    return $problems;
}

function checkPhpSyntax(ModuleFiles $files, Report $report): void
{
    $problems = [];
    $php = PHP_BINARY ?: 'php';
    foreach ($files->paths() as $path) {
        if (substr($path, -4) !== '.php' || strpos($path, 'vendor/') === 0) {
            continue;
        }
        $output = [];
        $code = 0;
        exec(escapeshellarg($php) . ' -l ' . escapeshellarg($files->absolute($path)) . ' 2>&1', $output, $code);
        if ($code !== 0) {
            $problems[] = $path . ': ' . trim(implode(' ', $output));
        }
    }
    $report->check('PHP syntax (php -l) of first-party files', $problems);
}

// ---------------------------------------------------------------------------

$mode = $argv[1] ?? '';
$report = new Report();

if ($mode === 'zip') {
    $target = $argv[2] ?? '';
    if ($target === '') {
        usage();
    }
    echo "Validating release ZIP {$target}\n\n";
    $files = loadZip($target, $report);
    if ($files === null) {
        exit($report->render());
    }
} elseif ($mode === 'source') {
    $target = $argv[2] ?? dirname(__DIR__, 2);
    $target = realpath($target) ?: $target;
    echo "Validating module source {$target} (development-only paths are ignored, as git archive would;\n"
        . "vendor/ is skipped because build-release.sh installs it from composer.lock at package time)\n\n";
    $shipped = array_filter(listDirectory($target), static function (string $path): bool {
        if (strpos($path, 'vendor/') === 0) {
            return false;
        }
        foreach (FORBIDDEN_PATHS as $forbidden) {
            $isDir = substr($forbidden, -1) === '/';
            if (($isDir && strpos($path, $forbidden) === 0) || (!$isDir && $path === $forbidden)) {
                return false;
            }
        }

        return true;
    });
    $files = new ModuleFiles($target, array_values($shipped));
} else {
    usage();
}

checkRequiredFiles($files, $report, $mode === 'zip');
if ($mode === 'zip') {
    checkForbiddenPaths($files, $report);
    checkVendorIsProductionOnly($files, $report);
}
checkFrontendChunks($files, $report);
checkIndexFiles($files, $report);
checkPhpGuards($files, $report);
checkLicenseHeaders($files, $report);
checkVersionConsistency($files, $report);
checkModuleMetadata($files, $report);
checkNoStaticContext($files, $report);
checkSmartyEscaping($files, $report);
checkPhpSyntax($files, $report);

exit($report->render());
