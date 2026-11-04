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

namespace Buckaroo\PrestaShop\Src\Service;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Signed, short-lived state for the customer return.
 *
 * Buckaroo sends the customer back with a cross-site POST. Browsers do not send
 * the SameSite=Lax shop cookie with that request, so the return controller
 * handles the reply without the session and passes the outcome to a same-site
 * GET request in this signed value. The GET request carries the session cookie
 * and applies the cart and message changes there.
 */
final class ReturnStateSigner
{
    public const OUTCOME_SUCCESS = 'success';
    public const OUTCOME_FAILURE = 'failure';

    private const DEFAULT_TTL = 600;

    /** @var string */
    private $key;

    /** @var int */
    private $ttl;

    /** @var callable */
    private $clock;

    public function __construct(string $secret, int $ttl = self::DEFAULT_TTL, ?callable $clock = null)
    {
        if ($secret === '') {
            throw new \InvalidArgumentException('A signing secret is required.');
        }

        // Derive a dedicated key so the value cannot be reused for anything signed with the raw secret.
        $this->key = hash_hmac('sha256', 'buckaroo3-return-state', $secret, true);
        $this->ttl = $ttl;
        $this->clock = $clock ?: 'time';
    }

    public function sign(int $cartId, string $outcome, string $message = ''): string
    {
        if (!in_array($outcome, [self::OUTCOME_SUCCESS, self::OUTCOME_FAILURE], true)) {
            throw new \InvalidArgumentException('Unknown return outcome: ' . $outcome);
        }

        $payload = self::base64UrlEncode((string) json_encode([
            'c' => $cartId,
            'o' => $outcome,
            'm' => $message,
            'e' => (int) call_user_func($this->clock) + $this->ttl,
        ]));

        return $payload . '.' . self::base64UrlEncode(hash_hmac('sha256', $payload, $this->key, true));
    }

    /**
     * @return array{cartId: int, outcome: string, message: string}|null null when the value is forged, malformed or expired
     */
    public function verify(string $state): ?array
    {
        $parts = explode('.', $state);
        if (count($parts) !== 2) {
            return null;
        }

        $expected = self::base64UrlEncode(hash_hmac('sha256', $parts[0], $this->key, true));
        if (!hash_equals($expected, $parts[1])) {
            return null;
        }

        $data = json_decode((string) self::base64UrlDecode($parts[0]), true);
        if (!is_array($data)
            || !isset($data['c'], $data['o'], $data['e'])
            || !is_int($data['c'])
            || !is_int($data['e'])
            || !in_array($data['o'], [self::OUTCOME_SUCCESS, self::OUTCOME_FAILURE], true)
        ) {
            return null;
        }

        if ($data['e'] < (int) call_user_func($this->clock)) {
            return null;
        }

        return [
            'cartId' => $data['c'],
            'outcome' => $data['o'],
            'message' => is_string($data['m'] ?? null) ? $data['m'] : '',
        ];
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * @return string|false
     */
    private static function base64UrlDecode(string $value)
    {
        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}
