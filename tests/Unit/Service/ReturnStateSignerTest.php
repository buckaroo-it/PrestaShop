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

namespace Buckaroo\PrestaShop\Tests\Unit\Service;

use Buckaroo\PrestaShop\Src\Service\ReturnStateSigner;
use PHPUnit\Framework\TestCase;

/**
 * The customer return hands its outcome from the cookieless cross-site POST to a
 * same-site GET in this value, so it must not be forgeable, editable or reusable later.
 */
final class ReturnStateSignerTest extends TestCase
{
    /** @var int */
    private $now = 1700000000;

    public function testRoundTripsTheOutcome(): void
    {
        $signer = $this->signer('secret');

        self::assertSame(
            ['cartId' => 42, 'outcome' => 'failure', 'message' => 'Payment cancelled'],
            $signer->verify($signer->sign(42, ReturnStateSigner::OUTCOME_FAILURE, 'Payment cancelled'))
        );
        self::assertSame(
            ['cartId' => 7, 'outcome' => 'success', 'message' => ''],
            $signer->verify($signer->sign(7, ReturnStateSigner::OUTCOME_SUCCESS))
        );
    }

    public function testTheValueIsSafeInAUrl(): void
    {
        $state = $this->signer('secret')->sign(42, ReturnStateSigner::OUTCOME_FAILURE, str_repeat("?&=/+\u{e9}", 20));

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $state);
    }

    public function testRejectsAChangedPayload(): void
    {
        $signer = $this->signer('secret');
        [, $signature] = explode('.', $signer->sign(42, ReturnStateSigner::OUTCOME_FAILURE));
        $forgedPayload = rtrim(strtr(base64_encode((string) json_encode([
            'c' => 43,
            'o' => 'success',
            'm' => '',
            'e' => $this->now + 600,
        ])), '+/', '-_'), '=');

        self::assertNull($signer->verify($forgedPayload . '.' . $signature));
    }

    public function testRejectsAValueSignedWithAnotherSecret(): void
    {
        $state = $this->signer('other shop')->sign(42, ReturnStateSigner::OUTCOME_SUCCESS);

        self::assertNull($this->signer('secret')->verify($state));
    }

    public function testRejectsAnExpiredValue(): void
    {
        $signer = $this->signer('secret');
        $state = $signer->sign(42, ReturnStateSigner::OUTCOME_SUCCESS);

        $this->now += 601;

        self::assertNull($signer->verify($state));
    }

    /**
     * @dataProvider malformedStates
     */
    public function testRejectsMalformedValues(string $state): void
    {
        self::assertNull($this->signer('secret')->verify($state));
    }

    /**
     * @return array<string, array{string}>
     */
    public function malformedStates(): array
    {
        return [
            'empty' => [''],
            'no signature' => ['eyJjIjo0Mn0'],
            'three parts' => ['a.b.c'],
            'garbage' => ['!!!.???'],
        ];
    }

    public function testRejectsAnUnknownOutcomeWhenSigning(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->signer('secret')->sign(42, 'paid');
    }

    private function signer(string $secret): ReturnStateSigner
    {
        return new ReturnStateSigner($secret, 600, function (): int {
            return $this->now;
        });
    }
}
