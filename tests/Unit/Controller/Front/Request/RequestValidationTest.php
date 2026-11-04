<?php

namespace BuckarooTest\Unit\Controller\Front\Request;

use Buckaroo\PrestaShop\Src\ApplePay\ExpressCheckoutService;
use BuckarooTest\Stubs;

require_once __DIR__ . '/RequestTestCase.php';

/**
 * Buckaroo3RequestModuleFrontController::postProcess(): every check that must
 * stop a payment before an order is created.
 *
 * Tools::redirect() throws in the tests and postProcess() catches every
 * Exception, so the tests read the redirect target from the recorded call;
 * in a shop the redirect ends the request there.
 */
class RequestValidationTest extends RequestTestCase
{
    public function testMissingCartSendsTheShopperBackToTheFirstStep(): void
    {
        $this->context()->cart = null;
        $controller = $this->controller();

        $targets = redirectTargets(function () use ($controller) {
            $controller->postProcess();
        });

        $redirect = self::checkoutRedirect($targets[0]);
        self::assertSame(1, $redirect['step']);
        self::assertSame(1, $redirect['error']);
        self::assertSame('Your shopping session expired. Please start the checkout again.', $redirect['message']);
        self::assertContains('Context or cart is not properly initialized.', $this->logger->messages('error'));
        self::assertSame([], self::validateOrderCalls());
    }

    public function testCartThatCannotBeLoadedIsRejected(): void
    {
        $this->cart->id = null;
        $controller = $this->controller();

        $targets = redirectTargets(function () use ($controller) {
            $controller->postProcess();
        });

        $redirect = self::checkoutRedirect($targets[0]);
        self::assertSame(1, $redirect['step']);
        self::assertSame('Your shopping cart is no longer available. Please restart the checkout.', $redirect['message']);
        self::assertStringContainsString('Reason: Cart object could not be loaded.', (string) $this->logger->descriptionOf('Validation Error'));
    }

    /**
     * @dataProvider incompleteCartProvider
     */
    public function testCartWithMissingIdentifiersOrInactiveModuleIsRejected(string $property, $value, $target): void
    {
        if ($target === 'module') {
            $this->module->{$property} = $value;
        } else {
            $this->cart->{$property} = $value;
        }
        $controller = $this->controller();

        $targets = redirectTargets(function () use ($controller) {
            $controller->postProcess();
        });

        self::assertSame(1, self::checkoutRedirect($targets[0])['step']);
        $debug = (string) $this->logger->descriptionOf('Validation Error');
        self::assertStringContainsString('Reason: Cart identifiers missing or module inactive.', $debug);
        self::assertStringContainsString('Customer Id: ', $debug);
        self::assertSame([], self::validateOrderCalls());
    }

    public function incompleteCartProvider(): array
    {
        return [
            'no customer' => ['id_customer', 0, 'cart'],
            'no delivery address' => ['id_address_delivery', 0, 'cart'],
            'no invoice address' => ['id_address_invoice', 0, 'cart'],
            'module inactive' => ['active', false, 'module'],
        ];
    }

    public function testEmptyCartIsRejected(): void
    {
        $this->cart->products = [];
        $controller = $this->controller();

        $targets = redirectTargets(function () use ($controller) {
            $controller->postProcess();
        });

        self::assertSame(1, self::checkoutRedirect($targets[0])['step']);
        self::assertStringContainsString('Reason: Cart is empty.', (string) $this->logger->descriptionOf('Validation Error'));
    }

    /**
     * @dataProvider invalidAddressProvider
     */
    public function testCartWithAnInvalidAddressIsRejected(string $property, $addressId, ?array $addressProps, string $expectedLog): void
    {
        $this->cart->{$property} = $addressId;
        if ($addressProps !== null) {
            Stubs::object('Address', (int) $addressId, $addressProps);
        }
        $controller = $this->controller();

        $targets = redirectTargets(function () use ($controller) {
            $controller->postProcess();
        });

        self::assertSame(1, self::checkoutRedirect($targets[0])['step']);
        self::assertContains($expectedLog, $this->logger->messages('error'));
        self::assertStringContainsString('Reason: Cart addresses are invalid.', (string) $this->logger->descriptionOf('Validation Error'));
    }

    public function invalidAddressProvider(): array
    {
        return [
            'negative delivery id' => ['id_address_delivery', -3, null, 'Invalid delivery address ID supplied: -3'],
            'unknown invoice address' => ['id_address_invoice', 99, null, 'Unable to load invoice address with ID 99'],
            'deleted delivery address' => ['id_address_delivery', 31, ['deleted' => 1], 'Unable to load delivery address with ID 31'],
        ];
    }

    public function testCartWithAnOrderIsDuplicatedAndThePaymentRetriesTheExistingOrder(): void
    {
        $existingOrderId = 50;
        Stubs::returns('Order::getIdByCartId', $existingOrderId);
        Stubs::object('Order', $existingOrderId, ['reference' => 'RETRY']);
        $newCart = new \Cart();
        $newCart->id = 6;
        Stubs::returns('Cart->duplicate', ['cart' => $newCart, 'success' => true]);
        $controller = $this->controller();
        $fake = $this->useFakeCheckout($controller);
        $fake->requestSucceeded = false;

        redirectTargets(function () use ($controller) {
            $controller->postProcess();
        });

        self::assertSame($newCart, $this->context()->cart);
        self::assertSame(6, $this->context()->cookie->id_cart);
        self::assertSame([], self::validateOrderCalls(), 'the existing order is reused');
        self::assertSame($existingOrderId, $this->module->currentOrder);
        self::assertSame('RETRY', $fake->reference);
        self::assertSame(['setCheckout', 'startPayment'], $fake->calls);
    }

    public function testFailedCartDuplicationStopsTheRetry(): void
    {
        Stubs::returns('Order::getIdByCartId', 50);
        Stubs::returns('Cart->duplicate', ['cart' => new \Cart(), 'success' => false]);
        $controller = $this->controller();

        $targets = redirectTargets(function () use ($controller) {
            $controller->postProcess();
        });

        self::assertSame(1, self::checkoutRedirect($targets[0])['step']);
        self::assertStringContainsString('Reason: Failed to duplicate cart for retry.', (string) $this->logger->descriptionOf('Validation Error'));
        self::assertSame($this->cart, $this->context()->cart);
    }

    /**
     * @dataProvider missingCredentialsProvider
     */
    public function testUnconfiguredModuleRedirectsToTheErrorPage(string $emptyKey): void
    {
        \Configuration::updateValue($emptyKey, '');
        $controller = $this->controller();

        $targets = redirectTargets(function () use ($controller) {
            $controller->postProcess();
        });

        self::assertSame(
            'index.php?fc=module&module=buckaroo3&controller=error&error=<b>Please contact merchant:</b><br/><br/> Buckaroo Plug-in is not properly configured.',
            $targets[0]
        );
        self::assertSame([], self::validateOrderCalls());
    }

    public function missingCredentialsProvider(): array
    {
        return [
            'merchant key' => ['BUCKAROO_MERCHANT_KEY'],
            'secret key' => ['BUCKAROO_SECRET_KEY'],
        ];
    }

    public function testCustomerThatCannotBeLoadedIsRejected(): void
    {
        Stubs::$objects['Customer'] = [];
        $controller = $this->controller();

        $targets = redirectTargets(function () use ($controller) {
            $controller->postProcess();
        });

        $redirect = self::checkoutRedirect($targets[0]);
        self::assertSame(1, $redirect['step']);
        self::assertSame('We could not verify your customer information. Please sign in again.', $redirect['message']);
        self::assertSame('Failed to load the customer with ID: 7', $this->logger->descriptionOf('Load a customer'));
    }

    public function testCustomerOfAnotherCartIsRejected(): void
    {
        $customer = new \Customer();
        $customer->id = 8;
        $controller = $this->controller();

        $targets = redirectTargets(function () use ($controller, $customer) {
            self::assertFalse(self::invoke($controller, 'isValidCustomer', [$customer, $this->cart]));
        });

        self::assertSame(1, self::checkoutRedirect($targets[0])['step']);
        self::assertContains('Customer mismatch detected for cart: 5', $this->logger->messages('error'));
    }

    /**
     * @dataProvider blockedCustomerProvider
     */
    public function testInactiveOrDeletedCustomerIsRejected(array $props, string $message, string $log): void
    {
        Stubs::object('Customer', self::CUSTOMER_ID, $props + ['secure_key' => 'sk-7']);
        $controller = $this->controller();

        $targets = redirectTargets(function () use ($controller) {
            $controller->postProcess();
        });

        $redirect = self::checkoutRedirect($targets[0]);
        self::assertSame(1, $redirect['step']);
        self::assertSame($message, $redirect['message']);
        self::assertContains($log, $this->logger->messages('error'));
    }

    public function blockedCustomerProvider(): array
    {
        return [
            'inactive' => [['active' => 0, 'deleted' => 0], 'Your account is inactive. Please contact the merchant.', 'Inactive customer tried to checkout: 7'],
            'deleted' => [['active' => 1, 'deleted' => 1], 'We could not verify your customer information. Please sign in again.', 'Deleted customer tried to checkout: 7'],
        ];
    }

    public function testCustomerWithoutActiveOrDeletedFlagsIsAccepted(): void
    {
        $customer = new \Customer();
        $customer->id = self::CUSTOMER_ID;

        self::assertTrue(self::invoke($this->controller(), 'isValidCustomer', [$customer, $this->cart]));
    }

    public function testUnknownCurrencyIsRejected(): void
    {
        $this->context()->currency->id = null;
        $controller = $this->controller();

        $targets = redirectTargets(function () use ($controller) {
            $controller->postProcess();
        });

        $redirect = self::checkoutRedirect($targets[0]);
        self::assertSame(1, $redirect['step']);
        self::assertSame('The selected currency is no longer available. Please refresh the checkout.', $redirect['message']);
        self::assertContains('Currency is not set in context or invalid.', $this->logger->messages('error'));
    }

    /**
     * @dataProvider unavailableMethodProvider
     */
    public function testUnavailablePaymentMethodSendsTheShopperBackToThePaymentStep(string $method, callable $arrange, string $message, string $log): void
    {
        $this->request([], ['method' => $method]);
        $arrange($this);
        $controller = $this->controller();

        $targets = redirectTargets(function () use ($controller) {
            $controller->postProcess();
        });

        $redirect = self::checkoutRedirect($targets[0]);
        self::assertSame(3, $redirect['step']);
        self::assertSame($message, $redirect['message']);
        self::assertContains($log, $this->logger->messages('error'));
        self::assertSame([], self::validateOrderCalls());
    }

    public function unavailableMethodProvider(): array
    {
        return [
            'no method' => ['  ', function () {
            }, 'Please select a payment method and try again.', 'Load a method'],
            'unknown method' => ['bitcoin', function () {
                Stubs::$dbResponders = [];
            }, 'The selected payment method is not available.', 'Invalid payment method requested: bitcoin'],
            'disabled method' => ['ideal', function (RequestValidationTest $test) {
                $test->module->paymentModeActive = false;
            }, 'The selected payment method is currently disabled.', 'Inactive payment method requested: ideal'],
            'not offered for this cart' => [' IDEAL ', function (RequestValidationTest $test) {
                $test->paymentService->methodAvailable = false;
            }, 'The selected payment method is not available for this order.', 'Payment method not available for cart 5: ideal'],
        ];
    }

    public function testAvailabilityIsCheckedForTheCartAndTheNormalisedMethod(): void
    {
        $this->request([], ['method' => ' IDEAL ']);
        $this->paymentService->methodAvailable = false;
        $controller = $this->controller();

        redirectTargets(function () use ($controller) {
            $controller->postProcess();
        });

        self::assertSame([['isMethodAvailableForCart', [$this->cart, 'ideal']]], $this->paymentService->calls);
    }

    public function testCreditCardWithoutBrandIsRejected(): void
    {
        $this->request([], ['method' => 'creditcard', 'BPE_CreditCard' => ' ']);
        $controller = $this->controller();

        $targets = redirectTargets(function () use ($controller) {
            $controller->postProcess();
        });

        $redirect = self::checkoutRedirect($targets[0]);
        self::assertSame(3, $redirect['step']);
        self::assertSame('Please select a credit or debit card before continuing with payment.', $redirect['message']);
        self::assertContains('Credit card payment started without a selected card brand/issuer.', $this->logger->messages('error'));
    }

    public function testCreditCardBrandTheMerchantDisabledIsRejected(): void
    {
        $this->request([], ['method' => 'creditcard', 'cardCode' => 'amex']);
        $this->paymentService->brandAllowed = false;
        $controller = $this->controller();

        $targets = redirectTargets(function () use ($controller) {
            $controller->postProcess();
        });

        $redirect = self::checkoutRedirect($targets[0]);
        self::assertSame('The selected card is not available. Please choose another card.', $redirect['message']);
        self::assertContains(['isCreditCardBrandAllowed', ['amex']], $this->paymentService->calls);
        self::assertContains('Credit card brand not enabled by the merchant for cart 5', $this->logger->messages('error'));
    }

    public function testValidatedCreditCardBrandIsHandedToTheCheckout(): void
    {
        $this->request([], ['method' => 'creditcard', 'BPE_CreditCard' => 'visa']);
        $controller = $this->controller();
        $fake = $this->useFakeCheckout($controller);
        $fake->requestSucceeded = false;

        redirectTargets(function () use ($controller) {
            $controller->postProcess();
        });

        self::assertInstanceOf(\CreditCardCheckout::class, $this->realCheckout);
        $issuer = new \ReflectionProperty(\CreditCardCheckout::class, 'issuer');
        if (PHP_VERSION_ID < 80100) {
            $issuer->setAccessible(true);
        }
        self::assertSame('visa', $issuer->getValue($this->realCheckout));
        self::assertCount(1, self::validateOrderCalls());
    }

    /**
     * @dataProvider invalidBankProvider
     */
    public function testPayByBankWithoutAnAllowedBankIsRejected(array $post, bool $allowed): void
    {
        $this->request([], $post + ['method' => 'paybybank']);
        $this->paymentService->issuerAllowed = $allowed;
        $controller = $this->controller();

        $targets = redirectTargets(function () use ($controller) {
            $controller->postProcess();
        });

        $redirect = self::checkoutRedirect($targets[0]);
        self::assertSame(3, $redirect['step']);
        self::assertSame('Please choose your bank.', $redirect['message']);
        self::assertContains('PayByBank payment started without a valid bank.', $this->logger->messages('error'));
    }

    public function invalidBankProvider(): array
    {
        return [
            'no bank' => [[], true],
            'bank not offered' => [['BPE_Issuer' => 'FAKEBANK'], false],
        ];
    }

    public function testPayByBankWithAnAllowedBankStartsThePayment(): void
    {
        $this->request([], ['method' => 'paybybank', 'BPE_Issuer' => 'INGBNL2A']);
        $this->checkoutAsIdeal('paybybank');
        $controller = $this->controller();
        $fake = $this->useFakeCheckout($controller);
        $fake->requestSucceeded = false;

        redirectTargets(function () use ($controller) {
            $controller->postProcess();
        });

        self::assertContains(['isPayByBankIssuerAllowed', ['INGBNL2A']], $this->paymentService->calls);
        self::assertSame(['setCheckout', 'startPayment'], $fake->calls);
    }

    /**
     * @dataProvider incompleteClickToPayProvider
     */
    public function testClickToPayWithoutDropInTokenIsRejected(array $post): void
    {
        $this->request([], $post + ['method' => 'clicktopay']);
        $controller = $this->controller();

        $targets = redirectTargets(function () use ($controller) {
            $controller->postProcess();
        });

        $redirect = self::checkoutRedirect($targets[0]);
        self::assertSame(3, $redirect['step']);
        self::assertSame('Please complete the Click to Pay checkout before continuing with payment.', $redirect['message']);
        self::assertContains('Click to Pay payment started without a transient token.', $this->logger->messages('error'));
    }

    public function incompleteClickToPayProvider(): array
    {
        return [
            'nothing posted' => [[]],
            'no transient token' => [['clicktopay_identifier' => 'id-1', 'clicktopay_transient_token' => ' ']],
            'no identifier' => [['clicktopay_transient_token' => 'token']],
        ];
    }

    public function testClickToPayWithDropInTokenStartsThePayment(): void
    {
        $this->request([], ['method' => 'clicktopay', 'clicktopay_identifier' => 'id-1', 'clicktopay_transient_token' => 'tok']);
        $this->checkoutAsIdeal('clicktopay');
        $controller = $this->controller();
        $fake = $this->useFakeCheckout($controller);
        $fake->requestSucceeded = false;

        redirectTargets(function () use ($controller) {
            $controller->postProcess();
        });

        self::assertCount(1, self::validateOrderCalls());
        self::assertSame(['setCheckout', 'startPayment'], $fake->calls);
    }

    public function testApplePayCheckoutPaymentIsAcceptedWhileListedInCheckout(): void
    {
        $this->request([], ['method' => 'applepay']);
        $this->checkoutAsIdeal('applepay');
        $controller = $this->controller();
        $fake = $this->useFakeCheckout($controller);
        $fake->requestSucceeded = false;

        redirectTargets(function () use ($controller) {
            $controller->postProcess();
        });

        self::assertCount(1, self::validateOrderCalls());
    }

    public function testApplePayCheckoutPaymentIsRejectedWhenOnlyOfferedAsExpressButton(): void
    {
        $this->request([], ['method' => 'applepay']);
        $this->configService->configs['applepay'] = ['checkout_method' => false];
        $controller = $this->controller();

        $targets = redirectTargets(function () use ($controller) {
            $controller->postProcess();
        });

        $redirect = self::checkoutRedirect($targets[0]);
        self::assertSame(3, $redirect['step']);
        self::assertSame('The selected payment method is not available.', $redirect['message']);
        self::assertContains('Apple Pay checkout payment requested while it is only offered as express button.', $this->logger->messages('error'));
    }

    /**
     * @dataProvider unauthorizedExpressProvider
     */
    public function testApplePayExpressPaymentNeedsTheAuthorizedCartAndAToken(?int $readyCart, string $token): void
    {
        $this->request([], ['method' => 'applepay', 'buckaroo_express' => 'applepay', 'applepay_token' => $token]);
        if ($readyCart !== null) {
            $this->context()->cookie->{ExpressCheckoutService::COOKIE_READY} = $readyCart;
        }
        $controller = $this->controller();

        $targets = redirectTargets(function () use ($controller) {
            $controller->postProcess();
        });

        $redirect = self::checkoutRedirect($targets[0]);
        self::assertSame(3, $redirect['step']);
        self::assertSame('Your Apple Pay payment could not be started. Please try again.', $redirect['message']);
        self::assertContains('Apple Pay express payment without an authorized cart or token for cart 5', $this->logger->messages('error'));
        self::assertFalse(isset($this->context()->cookie->{ExpressCheckoutService::COOKIE_READY}), 'the authorization is used once');
    }

    public function unauthorizedExpressProvider(): array
    {
        $token = json_encode(['paymentData' => ['data' => 'x']]);

        return [
            'not authorized' => [null, $token],
            'authorized for another cart' => [9, $token],
            'authorized without token' => [self::CART_ID, ''],
            'token without payment data' => [self::CART_ID, json_encode(['paymentData' => []])],
        ];
    }

    public function testAuthorizedApplePayExpressPaymentStartsThePayment(): void
    {
        $this->request([], [
            'method' => 'applepay',
            'buckaroo_express' => 'applepay',
            'applepay_token' => json_encode(['paymentData' => ['data' => 'x']]),
        ]);
        $this->context()->cookie->{ExpressCheckoutService::COOKIE_READY} = self::CART_ID;
        $this->checkoutAsIdeal('applepay');
        $controller = $this->controller();
        $fake = $this->useFakeCheckout($controller);
        $fake->requestSucceeded = false;

        redirectTargets(function () use ($controller) {
            $controller->postProcess();
        });

        self::assertCount(1, self::validateOrderCalls());
        self::assertSame(['setCheckout', 'startPayment'], $fake->calls);
    }

    /**
     * @dataProvider serviceProvider
     */
    public function testServiceParameter(string $service, bool $accepted): void
    {
        $this->request([], ['method' => 'ideal', 'service' => $service]);
        $controller = $this->controller();
        $fake = $this->useFakeCheckout($controller);
        $fake->requestSucceeded = false;

        $targets = redirectTargets(function () use ($controller) {
            $controller->postProcess();
        });

        if ($accepted) {
            self::assertCount(1, self::validateOrderCalls());
        } else {
            self::assertSame(['index.php?controller=order&step=1'], $targets);
            self::assertSame('Failed to load the method', $this->logger->descriptionOf('Load a method'));
            self::assertSame([], self::validateOrderCalls());
        }
    }

    public function serviceProvider(): array
    {
        return [
            'digi' => ['digi', true],
            'sepa' => ['sepa', true],
            'unknown' => ['other', false],
        ];
    }

    public function testBuckarooFeeIsAddedToTheOrderTotal(): void
    {
        $this->module->fee = ['buckaroo_fee_tax_incl' => 1.504, 'buckaroo_fee_tax_excl' => 1.0];
        $controller = $this->controller();
        $fake = $this->useFakeCheckout($controller);
        $fake->requestSucceeded = false;

        redirectTargets(function () use ($controller) {
            $controller->postProcess();
        });

        self::assertSame([['ideal', 10.0]], Stubs::callsTo('RequestModuleDouble->getBuckarooFee'));
        self::assertSame(11.5, self::validateOrderCalls()[0][2]);
        self::assertStringContainsString('Total Amount: 11.5', (string) $this->logger->descriptionOf('Checkout info'));
    }

    public function testUnexpectedExceptionShowsTheErrorPage(): void
    {
        $this->paymentService = new class extends PaymentServiceDouble {
            public function isMethodAvailableForCart($cart, string $method): bool
            {
                throw new \RuntimeException('database gone');
            }
        };
        $this->module->services['buckaroo.config.api.payment.service'] = $this->paymentService;
        $controller = $this->controller();

        $controller->postProcess();

        self::assertContains('Unexpected checkout exception: database gone', $this->logger->messages('error'));
        $description = $this->logger->descriptionOf('Unexpected checkout exception: database gone');
        self::assertSame('ideal', $description['payment_method']);
        self::assertInstanceOf(\RuntimeException::class, $description['exception']);
        self::assertSame('module:buckaroo3/views/templates/front/error.tpl', $controller->template);
        self::assertSame(
            'We were unable to start your payment. Please try again or contact the merchant.',
            $this->context()->smarty->getTemplateVars('error_message')
        );
        self::assertNull($this->context()->smarty->getTemplateVars('order_id'));
    }
}
