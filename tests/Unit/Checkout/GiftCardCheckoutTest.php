<?php

namespace BuckarooTest\Unit\Checkout;

use BuckarooTest\Stubs;

require_once __DIR__ . '/CheckoutTestCase.php';

class GiftCardCheckoutTest extends CheckoutTestCase
{
    private function vars(): array
    {
        $checkout = $this->makeCheckout('giftcard');
        $checkout->setCheckout();

        return $this->customVars($checkout);
    }

    public function testServicesCombineGiftcardsAllowedMethodsAndCardBrands(): void
    {
        $this->configService->method('getAllowedGiftcardCodes')->willReturn(['fashioncheque', ' VVVGiftcard ', '']);
        $this->configService->method('getConfigArrayForMethod')->with('giftcard')
            ->willReturn(['allowed_payments' => 'ideal, creditcard,giftcard,idin,IDEAL,,fashioncheque']);
        $this->configService->method('getActiveCreditCards')->willReturn(['visa' => [], 'MasterCard' => [], ' ' => []]);

        $vars = $this->vars();

        self::assertSame('fashioncheque,vvvgiftcard,ideal,visa,mastercard', $vars['servicesSelectableByClient']);
        self::assertSame('1', $vars['continueOnIncomplete']);
        self::assertSame(['email' => 'jan@example.com'], $vars['additionalParameters']);
    }

    public function testAllowedPaymentsAsList(): void
    {
        $this->configService->method('getAllowedGiftcardCodes')->willReturn([]);
        $this->configService->method('getConfigArrayForMethod')->willReturn([
            'allowed_payments' => [['name' => 'PayPal'], ['code' => 'bancontactmrcash'], 'eps', ['other' => 'x']],
        ]);

        self::assertSame('paypal,bancontactmrcash,eps', $this->vars()['servicesSelectableByClient']);
    }

    public function unusableConfigs(): array
    {
        return [
            'no config' => [null],
            'allowed payments not a list' => [['allowed_payments' => 5]],
            'allowed payments missing' => [[]],
        ];
    }

    /**
     * @dataProvider unusableConfigs
     */
    public function testUnusableConfigOnlySendsGiftcards($config): void
    {
        $this->configService->method('getAllowedGiftcardCodes')->willReturn(['boekenbon']);
        $this->configService->method('getConfigArrayForMethod')->willReturn($config);

        self::assertSame('boekenbon', $this->vars()['servicesSelectableByClient']);
    }

    public function testFailuresInTheConfigServiceAreIgnored(): void
    {
        $this->configService->method('getAllowedGiftcardCodes')->willThrowException(new \RuntimeException('db'));
        $this->configService->method('getConfigArrayForMethod')->willThrowException(new \RuntimeException('db'));

        self::assertSame('', $this->vars()['servicesSelectableByClient']);
    }

    public function creditCardFailures(): array
    {
        return [
            'throws' => [true, null],
            'not an array' => [false, 'visa'],
        ];
    }

    /**
     * @dataProvider creditCardFailures
     */
    public function testCreditCardBrandsAreSkippedWhenUnavailable(bool $throws, $cards): void
    {
        $this->configService->method('getAllowedGiftcardCodes')->willReturn([]);
        $this->configService->method('getConfigArrayForMethod')->willReturn(['allowed_payments' => 'creditcard,paypal']);
        if ($throws) {
            $this->configService->method('getActiveCreditCards')->willThrowException(new \RuntimeException('db'));
        } else {
            $this->configService->method('getActiveCreditCards')->willReturn($cards);
        }

        self::assertSame('paypal', $this->vars()['servicesSelectableByClient']);
    }

    public function testWithoutConfigServiceNothingIsSelectable(): void
    {
        $checkout = $this->makeCheckout('giftcard');
        self::setProperty($checkout, 'buckarooConfigService', null);
        $checkout->setCheckout();

        self::assertSame('', $this->customVars($checkout)['servicesSelectableByClient']);
        self::assertSame([], self::invoke($checkout, 'getActiveCreditCardServices'));
    }

    public function testInvalidEmailIsNotSent(): void
    {
        Stubs::object('Customer', self::CUSTOMER_ID, ['email' => 'not-an-email']);
        $this->configService->method('getAllowedGiftcardCodes')->willReturn([]);

        self::assertArrayNotHasKey('additionalParameters', $this->vars());
    }
}
