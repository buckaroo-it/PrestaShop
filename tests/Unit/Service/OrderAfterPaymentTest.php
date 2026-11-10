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

use Buckaroo\PrestaShop\Src\Service\OrderAfterPayment;
use Buckaroo\PrestaShop\Src\Service\ReturnStateSigner;
use PHPUnit\Framework\TestCase;

/**
 * Which payments create the order only after a successful payment (#337).
 */
final class OrderAfterPaymentTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public function immediateMethods(): array
    {
        $methods = [];
        foreach (['ideal', 'wero', 'bancontactmrcash', 'creditcard', 'paypal', 'applepay', 'googlepay', 'eps', 'przelewy24', 'trustly', 'paybybank'] as $method) {
            $methods[$method] = [$method];
        }

        return $methods;
    }

    /**
     * @dataProvider immediateMethods
     */
    public function testImmediateMethodsWaitForThePayment(string $method): void
    {
        self::assertTrue(OrderAfterPayment::appliesTo($method));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function orderFirstMethods(): array
    {
        $methods = [];
        foreach (['transfer', 'sepadirectdebit', 'payperemail', 'multibanco', 'mbway', 'afterpay', 'klarna', 'in3', 'in3Old', 'billink', 'giftcard', 'idin', ''] as $method) {
            $methods[$method === '' ? 'empty' : $method] = [$method];
        }

        return $methods;
    }

    /**
     * Paid later or authorized on the order: these need the order before the payment.
     *
     * @dataProvider orderFirstMethods
     */
    public function testOrderFirstMethodsKeepTheirFlow(string $method): void
    {
        self::assertFalse(OrderAfterPayment::appliesTo($method));
    }

    public function testTheMethodCodeIsCaseInsensitive(): void
    {
        self::assertTrue(OrderAfterPayment::appliesTo('iDEAL'));
    }

    public function testARemainderAfterAGiftcardKeepsTheOrderFirstFlow(): void
    {
        self::assertFalse(OrderAfterPayment::appliesTo('ideal', true));
    }

    public function testAmountsMatchToTheCent(): void
    {
        self::assertTrue(OrderAfterPayment::amountMatches(24.95, 24.95));
        self::assertTrue(OrderAfterPayment::amountMatches(24.95, 24.949999));
        self::assertTrue(OrderAfterPayment::amountMatches(10.0, 10.004));
        self::assertFalse(OrderAfterPayment::amountMatches(24.95, 24.96));
        // The cart was changed during the payment.
        self::assertFalse(OrderAfterPayment::amountMatches(24.95, 39.90));
    }

    public function testThePendingReturnStateRoundTrips(): void
    {
        $signer = new ReturnStateSigner('secret');

        self::assertSame(
            ['cartId' => 34, 'outcome' => 'pending', 'message' => ''],
            $signer->verify($signer->sign(34, ReturnStateSigner::OUTCOME_PENDING))
        );
    }
}
