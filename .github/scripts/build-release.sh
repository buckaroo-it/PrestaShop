#!/usr/bin/env bash
#
# NOTICE OF LICENSE
#
# This source file is subject to the Academic Free License (AFL 3.0)
# It is available through the world-wide-web at this URL:
# http://opensource.org/licenses/afl-3.0.php
#
# @author    Buckaroo.nl <plugins@buckaroo.nl>
# @copyright Copyright (c) Buckaroo B.V.
# @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
#
# Builds the release ZIP (buckaroo3/… at the root) from a git tree and validates it.
#
#   .github/scripts/build-release.sh [<git-ref>|--worktree] [<output.zip>]
#
#   <git-ref>    tree to package (default: HEAD). Only committed content is used.
#   --worktree   package the current working tree, including uncommitted changes
#                (handy for a pre-commit validator run).
#   <output.zip> defaults to build/buckaroo3.zip inside the module directory.
#
# vendor/ is not tracked in git. The script exports the tree with `git archive`
# (which honours the export-ignore rules in .gitattributes, so tests, dev
# tooling, CI files and dotfiles never end up in the package), installs the
# production dependencies pinned in composer.lock into the export with Composer
# and zips the result. Requires git, php (with the zip extension) and composer.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
REF="${1:-HEAD}"
OUT="${2:-$ROOT/build/buckaroo3.zip}"
MODULE=buckaroo3

for tool in git php composer tar curl sha256sum; do
    command -v "$tool" >/dev/null 2>&1 || { echo "error: '$tool' is required to build the release" >&2; exit 1; }
done

# php-scoper release used to prefix the third-party namespaces (see scoper.inc.php).
# The phar is cached in build/ and verified against the checksum published with
# the GitHub release before it is run. php-scoper itself needs PHP 8.2 or newer;
# point PHP_SCOPER_PHP at such a binary when the default php is older (the
# ZIP content stays PHP 7.4 compatible whatever PHP runs the build).
PHP_SCOPER_VERSION=0.18.20
PHP_SCOPER_SHA256=5e35e4a89ea53b2c70a19bfa8a856e4f9080aa12eb13a5bce650bb69d08eb122
PHP_SCOPER="$ROOT/build/php-scoper-$PHP_SCOPER_VERSION.phar"
PHP_SCOPER_PHP="${PHP_SCOPER_PHP:-php}"
if ! "$PHP_SCOPER_PHP" -r 'exit(PHP_VERSION_ID >= 80200 ? 0 : 1);'; then
    echo "error: php-scoper $PHP_SCOPER_VERSION needs PHP 8.2 or newer, found $("$PHP_SCOPER_PHP" -r 'echo PHP_VERSION;')." >&2
    echo "       Set PHP_SCOPER_PHP to a PHP 8.2+ binary, e.g. PHP_SCOPER_PHP=/usr/bin/php8.3 $0 $*" >&2
    exit 1
fi

mkdir -p "$(dirname "$OUT")"
rm -f "$OUT"

WORK="$(mktemp -d)"
TMP_INDEX="$WORK/index"
trap 'rm -rf "$WORK"' EXIT

if [ "$REF" = "--worktree" ]; then
    cp "$(git -C "$ROOT" rev-parse --git-path index)" "$TMP_INDEX"
    GIT_INDEX_FILE="$TMP_INDEX" git -C "$ROOT" add -A
    TREE="$(GIT_INDEX_FILE="$TMP_INDEX" git -C "$ROOT" write-tree)"
    echo "Packaging working tree (tree $TREE)"
else
    TREE="$(git -C "$ROOT" rev-parse --verify "$REF^{tree}")"
    echo "Packaging $REF (tree $TREE)"
fi

EXPORT="$WORK/$MODULE"
mkdir -p "$EXPORT"
git -C "$ROOT" archive --format=tar "$TREE" | tar -x -C "$EXPORT"

# composer.lock is export-ignored (it must not ship) but it pins the exact
# dependency versions, so take it from the same tree and drop it again after
# the install. A stale vendor/ from an older tree is never reused.
rm -rf "$EXPORT/vendor"
git -C "$ROOT" cat-file blob "$TREE:composer.lock" > "$EXPORT/composer.lock"

echo "Installing production dependencies from composer.lock"
composer install --working-dir="$EXPORT" --no-dev --classmap-authoritative \
    --no-interaction --no-progress --prefer-dist --no-plugins --no-scripts 2>&1 \
    | tee "$WORK/composer.log"
if grep -q "lock file is not up to date" "$WORK/composer.log"; then
    echo "error: composer.lock is out of date with composer.json; run 'composer update --lock' and commit the result" >&2
    exit 1
fi
rm -f "$EXPORT/composer.lock"

# PrestaShop loads its own Guzzle, Monolog, PSR and Symfony packages before any
# module, so the copies in vendor/ only run when their namespaces differ from
# the core's. Prefix every third-party namespace with Buckaroo\PrestaShop\Vendor
# (the module's own code and the Buckaroo SDK keep their names), then rebuild
# the Composer autoloader for the prefixed class names.
if [ ! -f "$PHP_SCOPER" ] || ! echo "$PHP_SCOPER_SHA256  $PHP_SCOPER" | sha256sum -c --status -; then
    echo "Downloading php-scoper $PHP_SCOPER_VERSION"
    mkdir -p "$(dirname "$PHP_SCOPER")"
    curl -fsSL -o "$PHP_SCOPER" "https://github.com/humbug/php-scoper/releases/download/$PHP_SCOPER_VERSION/php-scoper.phar"
    if ! echo "$PHP_SCOPER_SHA256  $PHP_SCOPER" | sha256sum -c --status -; then
        rm -f "$PHP_SCOPER"
        echo "error: php-scoper.phar does not match the pinned checksum" >&2
        exit 1
    fi
fi

echo "Prefixing third-party namespaces with php-scoper $PHP_SCOPER_VERSION"
SCOPED="$WORK/scoped"
(cd "$EXPORT" && BUCKAROO_SCOPER_VENDOR_DIR="$EXPORT/vendor" "$PHP_SCOPER_PHP" "$PHP_SCOPER" add-prefix vendor \
    --config "$ROOT/scoper.inc.php" --output-dir "$SCOPED" --force --stop-on-failure --no-interaction --quiet)
rm -rf "$EXPORT/vendor"
if [ -d "$SCOPED/vendor" ]; then
    mv "$SCOPED/vendor" "$EXPORT/vendor"
else
    mv "$SCOPED" "$EXPORT/vendor"
fi
composer dump-autoload --working-dir="$EXPORT" --no-dev --classmap-authoritative \
    --no-interaction --no-plugins --no-scripts --quiet

# Composer packages ship their own dotfiles (.gitignore, .editorconfig,
# .env.example, …). The Marketplace package may not contain hidden files other
# than .htaccess, so strip them from the installed vendor/ tree.
find "$EXPORT/vendor" \( -name '.*' ! -name '.htaccess' \) -prune -exec rm -rf {} +

# Every directory in the package must carry an index.php (PrestaShop directory
# protection). First-party directories have one in git; the installed vendor/
# tree gets the module's redirect stub wherever a package does not ship its own.
STUB="$EXPORT/index.php"
find "$EXPORT/vendor" -type d -exec sh -c 'for dir; do [ -e "$dir/index.php" ] || cp "$0" "$dir/index.php"; done' "$STUB" {} +

# Zip with PHP so the archive is built the same way on Linux CI and on Windows,
# where the zip CLI is not always installed. Entries are sorted for reproducibility.
php -- "$WORK" "$MODULE" "$OUT" <<'PHP'
<?php
[$work, $module, $out] = array_slice($argv, 1);
$base = $work . '/' . $module;
$zip = new ZipArchive();
if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "unable to create {$out}\n");
    exit(1);
}
$paths = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
foreach ($iterator as $file) {
    $paths[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($work) + 1));
}
sort($paths);
foreach ($paths as $path) {
    if (is_dir($work . '/' . $path)) {
        $zip->addEmptyDir($path);
    } else {
        $zip->addFile($work . '/' . $path, $path);
    }
}
$zip->close();
PHP

echo "Wrote $OUT ($(wc -c < "$OUT") bytes)"
echo
php "$ROOT/.github/scripts/validate-package.php" zip "$OUT"
