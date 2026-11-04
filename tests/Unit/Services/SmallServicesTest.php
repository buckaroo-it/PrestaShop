<?php

declare(strict_types=1);

namespace BuckarooTest\Unit\Services;

require_once __DIR__ . '/StubsSupport.php';

use Buckaroo\BuckarooClient;
use Buckaroo\PrestaShop\Src\AddressComponents;
use Buckaroo\PrestaShop\Src\Context\ContextResolver;
use Buckaroo\PrestaShop\Src\Exceptions\PaymentMethodNotFoundException;
use Buckaroo\PrestaShop\Src\Service\BuckarooClientFactory;
use Buckaroo\PrestaShop\Src\Service\BuckarooPaymentStatus;
use Buckaroo\PrestaShop\Src\Service\GatewayEnvironment;
use Buckaroo\PrestaShop\Src\Service\SessionFactory;
use BuckarooTest\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;

final class SmallServicesTest extends TestCase
{
    /**
     * @dataProvider addressProvider
     */
    public function testAddressComponents(string $address, array $expected): void
    {
        self::assertSame($expected, AddressComponents::getAddressComponents($address));
    }

    public function addressProvider(): array
    {
        return [
            'street and number' => ['Hoofdstraat 12', ['house_number' => '12', 'number_addition' => '', 'street' => 'Hoofdstraat']],
            'number with addition' => ['Kerkweg 7 bis', ['house_number' => '7', 'number_addition' => 'bis', 'street' => 'Kerkweg']],
            'letter addition' => ['Dorpsplein 3a', ['house_number' => '3', 'number_addition' => 'a', 'street' => 'Dorpsplein']],
            'street starting with a number' => ['1e Jan Steenstraat 25-II', ['house_number' => '25', 'number_addition' => '-II', 'street' => '1e Jan Steenstraat']],
            'punctuation is removed' => ['Main [street]*, 4!', ['house_number' => '4', 'number_addition' => '', 'street' => 'Main street']],
            'no number' => ['Postbus', ['house_number' => '', 'number_addition' => '', 'street' => 'Postbus']],
            'number only' => ['12', ['house_number' => '', 'number_addition' => '', 'street' => '12']],
        ];
    }

    public function testPaymentMethodNotFoundException(): void
    {
        $previous = new \LogicException('cause');
        $exception = new PaymentMethodNotFoundException('ideal', $previous);

        self::assertSame('Payment method "ideal" not found', $exception->getMessage());
        self::assertSame(1, $exception->getCode());
        self::assertSame('ideal', $exception->getPaymentMethod());
        self::assertSame($previous, $exception->getPrevious());
        self::assertInstanceOf(\RuntimeException::class, $exception);
        self::assertNull((new PaymentMethodNotFoundException('x'))->getPrevious());
    }

    public function testContextProviderThatThrowsResolvesToNothing(): void
    {
        $provider = new class {
            public function getContext()
            {
                throw new \RuntimeException('container not booted');
            }
        };

        self::assertNull(ContextResolver::fromCandidate($provider));
    }

    public function testProviderReturningSomethingElseIsIgnored(): void
    {
        $provider = new class {
            public function getContext()
            {
                return new \stdClass();
            }
        };

        self::assertNull(ContextResolver::fromCandidate($provider));
        self::assertFalse(ContextResolver::looksLikeContext('context'));
    }

    public function testModuleLookupFailureResolvesToNothing(): void
    {
        \Module::$instances = new ThrowingArrayAccess();

        self::assertNull(ContextResolver::fromModule());
    }

    public function testModuleWithoutAContextResolvesToNothing(): void
    {
        \Module::$instances['buckaroo3'] = new \stdClass();
        self::assertNull(ContextResolver::fromModule());

        $module = new \Module();
        $module->context = new \stdClass(); // not a usable context yet
        \Module::$instances['buckaroo3'] = $module;
        self::assertNull(ContextResolver::fromModule());
    }

    /**
     * @dataProvider replyFlagProvider
     */
    public function testGatewayEnvironmentFromReplyFlag($flag, string $expected): void
    {
        self::assertSame($expected, GatewayEnvironment::fromReplyFlag($flag));
    }

    public function replyFlagProvider(): array
    {
        return [
            [true, 'test'], [false, 'live'], ['true', 'test'], [' TRUE ', 'test'], ['1', 'test'], [1, 'test'],
            ['false', 'live'], ['0', 'live'], ['', 'live'], [null, 'live'], ['yes', 'live'],
        ];
    }

    public function testGatewayEnvironmentFromResponseFlag(): void
    {
        self::assertSame('test', GatewayEnvironment::fromResponseFlag(true));
        self::assertSame('live', GatewayEnvironment::fromResponseFlag(false));
        self::assertNull(GatewayEnvironment::fromResponseFlag('true'));
        self::assertNull(GatewayEnvironment::fromResponseFlag(null));
    }

    public function testGatewayEnvironmentReplyMatches(): void
    {
        self::assertTrue(GatewayEnvironment::replyMatches([], 'live'), 'payments without a recorded mode keep the old behaviour');
        self::assertTrue(GatewayEnvironment::replyMatches(['test', 'live'], 'live'));
        self::assertFalse(GatewayEnvironment::replyMatches(['live'], 'test'));
    }

    /**
     * @dataProvider statusProvider
     */
    public function testPaymentStatusPaidAndPending($status, bool $paid, bool $pending): void
    {
        self::assertSame($paid, BuckarooPaymentStatus::isPaid($status));
        self::assertSame($pending, BuckarooPaymentStatus::isPending($status));
    }

    public function statusProvider(): array
    {
        return [
            [190, true, false], ['190', true, false], [790, false, true], ['793', false, true],
            [794, false, false], [490, false, false], ['abc', false, false], [null, false, false],
        ];
    }

    /**
     * @dataProvider giftcardOutcomeProvider
     */
    public function testGiftcardOutcome($status, float $remainder, float $charged, string $group, string $expected): void
    {
        self::assertSame($expected, BuckarooPaymentStatus::giftcardOutcome($status, $remainder, $charged, $group));
    }

    public function giftcardOutcomeProvider(): array
    {
        return [
            'paid in full' => [190, 0.0, 10.0, '', 'paid'],
            'paid with remainder' => [190, 2.5, 10.0, 'G1', 'partial'],
            'failed' => [490, 0.0, 10.0, '', 'failed'],
            'pending with remainder' => [791, 4.0, 10.0, 'G1', 'partial'],
            'pending without group' => [791, 4.0, 10.0, '', 'pending'],
            'pending remainder not below charge' => [791, 10.0, 10.0, 'G1', 'pending'],
            'pending without remainder' => [792, 0.0, 10.0, 'G1', 'pending'],
        ];
    }

    public function testGiftcardInvoiceRoundTrip(): void
    {
        $invoice = BuckarooPaymentStatus::giftcardInvoice(15, 1700000000);

        self::assertSame('gc_1700000000_15', $invoice);
        self::assertTrue(BuckarooPaymentStatus::isGiftcardReference($invoice));
        self::assertFalse(BuckarooPaymentStatus::isGiftcardReference('ABC_15'));
        self::assertSame([15, 'gc_1700000000'], BuckarooPaymentStatus::parseInvoice($invoice));
        self::assertSame([7, 'ORDER_REF'], BuckarooPaymentStatus::parseInvoice('ORDER_REF_7'));
        self::assertSame([0, 'NOCART'], BuckarooPaymentStatus::parseInvoice('NOCART'));
        self::assertSame([0, ''], BuckarooPaymentStatus::parseInvoice(''));
    }

    public function testSessionFactoryReturnsTheSessionOfTheCurrentRequest(): void
    {
        $session = new Session();
        $request = new Request();
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        self::assertSame($session, SessionFactory::create($stack));
    }

    public function testSessionFactoryNeedsARequest(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Request is not available');

        SessionFactory::create(new RequestStack());
    }

    public function testClientFactoryBuildsAClientWithoutContactingTheGateway(): void
    {
        $client = (new BuckarooClientFactory())->create('website-key', 'secret-key');

        self::assertInstanceOf(BuckarooClient::class, $client);
        self::assertSame('website-key', $client->client()->config()->websiteKey());
        self::assertSame('secret-key', $client->client()->config()->secretKey());
    }

    public function testClientFactoryRejectsMissingCredentials(): void
    {
        $this->expectException(\Buckaroo\Exceptions\BuckarooException::class);

        (new BuckarooClientFactory())->create('', '');
    }
}
