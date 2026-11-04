<?php

declare(strict_types=1);

use Buckaroo\PrestaShop\Src\ErrorReporting\ShopMetadataProvider;
use Buckaroo\PrestaShop\Src\ErrorReporting\TransportInterface;

/**
 * Records every request instead of sending it.
 */
class RecordingTransport implements TransportInterface
{
    /** @var array<int,array{url:string,headers:array,body:string}> */
    public $requests = [];

    public function send(string $url, array $headers, string $body): void
    {
        $this->requests[] = ['url' => $url, 'headers' => $headers, 'body' => $body];
    }

    public function lastPayload(): array
    {
        $last = end($this->requests);

        return json_decode($last['body'], true);
    }
}

class FailingTransport implements TransportInterface
{
    /** @var int */
    public $calls = 0;

    public function send(string $url, array $headers, string $body): void
    {
        ++$this->calls;
        throw new RuntimeException('Connection timed out');
    }
}

class FixedMetadataProvider extends ShopMetadataProvider
{
    /** @var string|null */
    public $requestedPaymentMethod;

    public function getMetadata(?string $paymentMethod = null): array
    {
        $this->requestedPaymentMethod = $paymentMethod;

        return [
            'plugin_version' => '5.3.0',
            'platform_version' => '9.0.1.0',
            'php_version' => '8.2.12',
            'environment' => 'live',
            'shop_url' => 'https://shop.example.com/',
            'website_key' => 'ABC123',
        ];
    }

    public function getPluginVersion(): string
    {
        return '5.3.0';
    }
}

class ThrowingMetadataProvider extends ShopMetadataProvider
{
    public function getMetadata(?string $paymentMethod = null): array
    {
        throw new LogicException('Db not available');
    }

    public function getPluginVersion(): string
    {
        return '5.3.0';
    }
}

class InMemorySuppressionStore implements Buckaroo\PrestaShop\Src\ErrorReporting\SuppressionStoreInterface
{
    /** @var array<string,int> */
    public $entries = [];

    public function wasSentSince(string $fingerprint, int $notBefore): bool
    {
        return isset($this->entries[$fingerprint]) && $this->entries[$fingerprint] >= $notBefore;
    }

    public function recordSent(array $fingerprints, int $now, int $notBefore): void
    {
        foreach ($fingerprints as $fingerprint) {
            $this->entries[$fingerprint] = $now;
        }
    }
}

class FakeClock
{
    /** @var int */
    public $now = 1700000000;

    public function __invoke(): int
    {
        return $this->now;
    }
}
