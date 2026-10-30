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
 * php-scoper configuration for the release build (.github/scripts/build-release.sh).
 *
 * PrestaShop ships its own Guzzle, Monolog, PSR and Symfony packages and loads
 * them before any module, so the versions bundled in vendor/ were never the
 * ones that ran: on PrestaShop 9 the core's Guzzle 7 and Monolog 3 replaced
 * the SDK's pinned versions, which can fail with signature or class errors.
 * The build prefixes every third-party namespace in the installed vendor/
 * tree with Buckaroo\PrestaShop\Vendor so the module always runs the
 * dependencies it ships.
 *
 * Kept as-is:
 * - the Buckaroo SDK (Buckaroo\...): the module code refers to it directly
 *   and PrestaShop does not ship it, so there is nothing to clash with. Its
 *   own references to Guzzle, Monolog and PSR are prefixed like everywhere else;
 * - the Symfony polyfills, symfony/deprecation-contracts and
 *   ralouphie/getallheaders: they only declare global functions and classes
 *   behind function_exists()/class_exists() guards, identical to the copies
 *   PrestaShop already loads, so prefixing them would only duplicate them;
 * - the Composer autoloader files: build-release.sh regenerates them with
 *   composer dump-autoload after scoping.
 *
 * This file is export-ignored and never ships in the ZIP.
 */

declare(strict_types=1);

$vendorDir = rtrim(getenv('BUCKAROO_SCOPER_VENDOR_DIR') ?: getcwd() . '/vendor', '/\\');

$excludedFiles = array_merge(
    glob($vendorDir . '/symfony/polyfill-*/*.php') ?: [],
    glob($vendorDir . '/symfony/polyfill-*/Resources/stubs/*.php') ?: [],
    glob($vendorDir . '/symfony/deprecation-contracts/*.php') ?: [],
    glob($vendorDir . '/ralouphie/getallheaders/src/*.php') ?: [],
    glob($vendorDir . '/composer/*.php') ?: [],
    [$vendorDir . '/autoload.php']
);

return [
    'prefix' => 'Buckaroo\\PrestaShop\\Vendor',

    // Parse with the host PHP, print PHP 7.2 compatible code (the module supports PHP 7.4).
    'php-version' => null,

    // The vendor directory is passed on the command line by build-release.sh.
    'finders' => [],

    'exclude-namespaces' => [
        'Buckaroo',
        'Symfony\\Polyfill',
    ],
    'exclude-files' => array_values(array_filter($excludedFiles, 'is_file')),
    'exclude-classes' => [],
    'exclude-functions' => [],
    'exclude-constants' => [],

    // Nothing is exposed: the module never refers to a third-party symbol by its
    // original name, so no scoper-autoload.php aliases are needed.
    'expose-global-constants' => false,
    'expose-global-classes' => false,
    'expose-global-functions' => false,
    'expose-namespaces' => [],
    'expose-classes' => [],
    'expose-functions' => [],
    'expose-constants' => [],

    'patchers' => [],
];
