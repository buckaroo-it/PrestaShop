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

namespace Buckaroo\PrestaShop\Src\Express;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * A wallet that offers an express button: the Buckaroo payment method and the
 * brand name shown to the shopper.
 */
final class Wallet
{
    public const APPLE_PAY = 'applepay';
    public const GOOGLE_PAY = 'googlepay';
    public const PAYPAL = 'paypal';

    private const LABELS = [
        self::APPLE_PAY => 'Apple Pay',
        self::GOOGLE_PAY => 'Google Pay',
        self::PAYPAL => 'PayPal',
    ];

    /** @var string */
    private $method;

    private function __construct(string $method)
    {
        $this->method = $method;
    }

    public static function applePay(): self
    {
        return new self(self::APPLE_PAY);
    }

    public static function googlePay(): self
    {
        return new self(self::GOOGLE_PAY);
    }

    public static function payPal(): self
    {
        return new self(self::PAYPAL);
    }

    /**
     * @throws \InvalidArgumentException for a payment method without express button
     */
    public static function fromMethod(string $method): self
    {
        if (!isset(self::LABELS[$method])) {
            throw new \InvalidArgumentException('No express wallet for payment method ' . $method);
        }

        return new self($method);
    }

    public static function isExpressMethod(string $method): bool
    {
        return isset(self::LABELS[$method]);
    }

    /**
     * @return string[]
     */
    public static function methods(): array
    {
        return array_keys(self::LABELS);
    }

    /** Buckaroo payment method name, as stored in the module configuration. */
    public function getMethod(): string
    {
        return $this->method;
    }

    /** Brand name; not translated. */
    public function getLabel(): string
    {
        return self::LABELS[$this->method];
    }
}
