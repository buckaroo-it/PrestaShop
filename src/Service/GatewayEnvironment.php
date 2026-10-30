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
 * Test and live replies are signed with the same secret key, so a valid
 * signature does not prove which gateway produced the reply. A reply only
 * counts when its signed environment matches the gateway the payment was
 * started on.
 */
class GatewayEnvironment
{
    public const LIVE = 'live';
    public const TEST = 'test';

    /**
     * Gateway environment of a push or return, read from its signed brq_test field.
     * Buckaroo sends "true" or "false"; anything else is not a test reply.
     *
     * @param mixed $brqTest
     */
    public static function fromReplyFlag($brqTest): string
    {
        if (is_bool($brqTest)) {
            return $brqTest ? self::TEST : self::LIVE;
        }

        return in_array(strtolower(trim((string) $brqTest)), ['true', '1'], true) ? self::TEST : self::LIVE;
    }

    /**
     * Gateway environment of a direct transaction response, read from IsTest.
     * Null when the response does not say, so the caller can skip the check.
     *
     * @param mixed $isTest
     */
    public static function fromResponseFlag($isTest): ?string
    {
        if (!is_bool($isTest)) {
            return null;
        }

        return $isTest ? self::TEST : self::LIVE;
    }

    /**
     * Whether a reply from $replyEnvironment may change an order whose payments
     * were started on $startedModes. No recorded mode means the payment was
     * started before this check existed; those replies keep the old behaviour.
     *
     * @param string[] $startedModes
     */
    public static function replyMatches(array $startedModes, string $replyEnvironment): bool
    {
        if (empty($startedModes)) {
            return true;
        }

        return in_array($replyEnvironment, $startedModes, true);
    }
}
