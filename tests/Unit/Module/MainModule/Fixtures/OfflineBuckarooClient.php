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
 * Offline replacement for the SDK's Buckaroo\BuckarooClient.
 *
 * Buckaroo3::checkKeys() verifies the credentials with a request to Buckaroo.
 * Only tests that run in a separate process load this file, before the
 * autoloader has loaded the real client, so no other test is affected.
 */

namespace Buckaroo;

if (class_exists(BuckarooClient::class, false)) {
    throw new \LogicException('The real Buckaroo\\BuckarooClient is already loaded; run the test in a separate process.');
} else {
    class BuckarooClient
    {
        /** @var bool answer of confirmCredential() */
        public static $credentialsValid = true;

        /** @var array<int, array{0: mixed, 1: mixed}> constructor arguments */
        public static $created = [];

        public function __construct($websiteKey, ?string $secretKey = null, ?string $mode = null)
        {
            self::$created[] = [$websiteKey, $secretKey];
        }

        public function confirmCredential(): bool
        {
            return self::$credentialsValid;
        }
    }
}
