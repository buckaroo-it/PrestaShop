<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Tests\Unit\Reply;

use Buckaroo\PrestaShop\Src\Service\GatewayEnvironment;
use PHPUnit\Framework\TestCase;

class GatewayEnvironmentTest extends TestCase
{
    /**
     * @dataProvider replyFlags
     *
     * @param mixed $brqTest
     */
    public function testReadsTheReplyEnvironmentFromBrqTest($brqTest, string $expected): void
    {
        $this->assertSame($expected, GatewayEnvironment::fromReplyFlag($brqTest));
    }

    /**
     * @return array<string,array{0:mixed,1:string}>
     */
    public function replyFlags(): array
    {
        return [
            'true' => ['true', GatewayEnvironment::TEST],
            'True' => ['True', GatewayEnvironment::TEST],
            '1' => ['1', GatewayEnvironment::TEST],
            'bool true' => [true, GatewayEnvironment::TEST],
            'false' => ['false', GatewayEnvironment::LIVE],
            'missing' => [false, GatewayEnvironment::LIVE],
            'empty' => ['', GatewayEnvironment::LIVE],
            'null' => [null, GatewayEnvironment::LIVE],
        ];
    }

    public function testReadsTheResponseEnvironmentFromIsTest(): void
    {
        $this->assertSame(GatewayEnvironment::TEST, GatewayEnvironment::fromResponseFlag(true));
        $this->assertSame(GatewayEnvironment::LIVE, GatewayEnvironment::fromResponseFlag(false));
        $this->assertNull(GatewayEnvironment::fromResponseFlag(null));
        $this->assertNull(GatewayEnvironment::fromResponseFlag('true'));
    }

    public function testRejectsATestReplyForALivePayment(): void
    {
        $this->assertFalse(GatewayEnvironment::replyMatches([GatewayEnvironment::LIVE], GatewayEnvironment::TEST));
        $this->assertFalse(GatewayEnvironment::replyMatches([GatewayEnvironment::TEST], GatewayEnvironment::LIVE));
    }

    public function testAcceptsAReplyFromTheGatewayThePaymentStartedOn(): void
    {
        $this->assertTrue(GatewayEnvironment::replyMatches([GatewayEnvironment::LIVE], GatewayEnvironment::LIVE));
        $this->assertTrue(GatewayEnvironment::replyMatches([GatewayEnvironment::TEST], GatewayEnvironment::TEST));
        $this->assertTrue(GatewayEnvironment::replyMatches(
            [GatewayEnvironment::LIVE, GatewayEnvironment::TEST],
            GatewayEnvironment::TEST
        ));
    }

    public function testAcceptsRepliesForPaymentsStartedBeforeModesWereRecorded(): void
    {
        $this->assertTrue(GatewayEnvironment::replyMatches([], GatewayEnvironment::TEST));
    }
}
