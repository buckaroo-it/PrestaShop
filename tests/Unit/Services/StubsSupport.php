<?php
/**
 * Test-only helpers for tests/Unit/Services.
 *
 * BuckarooClickToPayService talks to the Buckaroo auth endpoint with the curl
 * functions, called unqualified from the Buckaroo\PrestaShop\Src\Service
 * namespace. Defining same-named functions in that namespace makes PHP use
 * these fakes instead of ext-curl, so no test can reach the network. Unless a
 * test configures FakeCurl, curl_init() fails (returns false).
 */

namespace BuckarooTest\Unit\Services {
    if (!class_exists(FakeCurl::class)) {
        final class FakeCurl
        {
            /** @var bool whether curl_init() returns a handle */
            public static $enabled = false;

            /** @var mixed what curl_exec() returns */
            public static $body = false;

            /** @var string what curl_error() returns */
            public static $error = '';

            /** @var array<int, array{0: string, 1: array}> recorded calls */
            public static $calls = [];

            public static function reset(): void
            {
                self::$enabled = false;
                self::$body = false;
                self::$error = '';
                self::$calls = [];
            }

            /** @return array<int, array> argument lists of the calls to $function */
            public static function callsTo(string $function): array
            {
                $found = [];
                foreach (self::$calls as $call) {
                    if ($call[0] === $function) {
                        $found[] = $call[1];
                    }
                }

                return $found;
            }
        }
    }

    if (!class_exists(ThrowingArrayAccess::class)) {
        /**
         * Array stand-in whose every read throws; used to make a static
         * registry lookup (Module::$instances[...] ?? false) fail.
         */
        final class ThrowingArrayAccess implements \ArrayAccess
        {
            #[\ReturnTypeWillChange]
            public function offsetExists($offset)
            {
                throw new \RuntimeException('registry unavailable');
            }

            #[\ReturnTypeWillChange]
            public function offsetGet($offset)
            {
                throw new \RuntimeException('registry unavailable');
            }

            #[\ReturnTypeWillChange]
            public function offsetSet($offset, $value)
            {
            }

            #[\ReturnTypeWillChange]
            public function offsetUnset($offset)
            {
            }
        }
    }
}

namespace Buckaroo\PrestaShop\Src\Service {
    use BuckarooTest\Unit\Services\FakeCurl;

    if (!function_exists(__NAMESPACE__ . '\curl_init')) {
        function curl_init($url = null)
        {
            FakeCurl::$calls[] = ['curl_init', [$url]];

            return FakeCurl::$enabled ? 'fake-curl-handle' : false;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\curl_setopt_array')) {
        function curl_setopt_array($handle, array $options)
        {
            FakeCurl::$calls[] = ['curl_setopt_array', [$handle, $options]];

            return true;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\curl_exec')) {
        function curl_exec($handle)
        {
            FakeCurl::$calls[] = ['curl_exec', [$handle]];

            return FakeCurl::$body;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\curl_error')) {
        function curl_error($handle)
        {
            FakeCurl::$calls[] = ['curl_error', [$handle]];

            return FakeCurl::$error;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\curl_close')) {
        function curl_close($handle)
        {
            FakeCurl::$calls[] = ['curl_close', [$handle]];
        }
    }
}
