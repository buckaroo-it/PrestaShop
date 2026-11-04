<?php

namespace BuckarooTest\Unit\ExpressAndGiftcard;

use Buckaroo\PrestaShop\Src\ApplePay\ExpressCheckoutService;
use Buckaroo\PrestaShop\Src\ApplePay\ExpressException;
use Buckaroo\PrestaShop\Src\Service\BuckarooPaymentService;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;

require_once __DIR__ . '/ExpressTestSupport.php';

/**
 * Server side of the Apple Pay express button: express carts, shipping
 * estimates, totals and the authorization that turns the express cart into
 * the shopper's cart.
 */
class ExpressCheckoutServiceTest extends TestCase
{
    private const NL = 150;
    private const US = 21;

    /** @var ExpressTestModule */
    private $module;

    /** @var BuckarooPaymentService&\PHPUnit\Framework\MockObject\MockObject */
    private $paymentService;

    /** @var bool */
    private $applePayAvailable = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->module = new ExpressTestModule();
        $this->paymentService = $this->createMock(BuckarooPaymentService::class);
        $this->paymentService->method('isMethodAvailableForCart')->willReturnCallback(function () {
            return $this->applePayAvailable;
        });

        \Configuration::updateValue('PS_SHOP_NAME', 'Test Shop');
        \Configuration::updateValue('PS_GUEST_CHECKOUT_ENABLED', 1);

        $this->country(self::NL, 'NL', ['contains_states' => false]);
        $this->country(self::US, 'US', ['contains_states' => true, 'need_zip_code' => false]);
        $this->context()->country = new \Country(self::NL);
        $this->context()->customer = new \Customer();
        $this->context()->cookie->id_guest = 31;

        Stubs::returns('Customer->getAddresses', []);
        Stubs::returns('Country->checkZipCode', true);
    }

    private function service(): ExpressCheckoutService
    {
        return new ExpressCheckoutService($this->module, $this->context(), $this->paymentService);
    }

    private function country(int $id, string $iso, array $props = []): void
    {
        \Country::$isoById[$id] = $iso;
        Stubs::object('Country', $id, $props + ['active' => true, 'contains_states' => false, 'need_zip_code' => true, 'iso_code' => $iso]);
    }

    /**
     * An express cart with one product, carriers per delivery address and totals.
     *
     * @param array<string, array> $options delivery option key => option (for whatever address the cart has)
     */
    private function cart(array $options = [], array $totals = [], int $id = 500): \Cart
    {
        $cart = new \Cart();
        $cart->id = $id;
        $cart->products = [
            ['name' => 'T-shirt', 'attributes_small' => 'M, Blue', 'cart_quantity' => 2, 'total_wt' => 30.0],
            ['name' => '<b>Mug</b>', 'attributes_small' => '', 'cart_quantity' => 1, 'total_wt' => 9.5],
        ];
        $cart->totals = $totals + [
            \Cart::BOTH => 44.45,
            \Cart::ONLY_SHIPPING => 4.95,
            \Cart::ONLY_DISCOUNTS => 0.0,
            \Cart::ONLY_WRAPPING => 0.0,
        ];
        $cart->stub('getDeliveryOptionList', function () use ($cart, $options) {
            return [(int) $cart->id_address_delivery => $options];
        });

        return $cart;
    }

    private function carrier(string $name, $delay = ''): \Carrier
    {
        $carrier = new \Carrier();
        $carrier->name = $name;
        $carrier->delay = $delay;

        return $carrier;
    }

    private function options(): array
    {
        return [
            '5,' => ['carrier_list' => [['instance' => $this->carrier('PostNL', [1 => '1-2 days'])]], 'total_price_with_tax' => 4.95],
            '7,' => ['carrier_list' => [['instance' => $this->carrier('DHL', 'Next day')]], 'total_price_with_tax' => 6.5],
        ];
    }

    /** @return string[] keys passed to Cart::setDeliveryOption() */
    private static function selectedOptions(): array
    {
        $selected = [];
        foreach (Stubs::callsTo('Cart->setDeliveryOption') as $args) {
            $selected[] = is_array($args[0]) ? (string) current($args[0]) : 'null';
        }

        return $selected;
    }

    private function expectExpressError(callable $callback, string $message, string $field = ''): void
    {
        try {
            $callback();
        } catch (ExpressException $exception) {
            self::assertSame($message, $exception->getMessage());
            self::assertSame($field, $exception->getContactField());

            return;
        }

        self::fail('Expected an ExpressException: ' . $message);
    }

    // ------------------------------------------------------------------
    // startFromProduct()
    // ------------------------------------------------------------------

    private function sellableProduct(int $id = 10, array $props = []): void
    {
        Stubs::object('Product', $id, $props + ['active' => 1, 'available_for_order' => 1]);
        Stubs::returns('Product->checkAccess', true);
        Stubs::returns('Product->hasAttributes', 0);
        Stubs::returns('Product::getRequiredCustomizableFieldsStatic', []);
        Stubs::returns('Cart->updateQty', true);
    }

    public function testStartFromProductCreatesASeparateExpressCartAndRemembersTheShoppersCart(): void
    {
        $this->sellableProduct();
        $shopperCart = new \Cart();
        $shopperCart->id = 5;
        $this->context()->cart = $shopperCart;

        $cart = $this->service()->startFromProduct(10, 0, [], 3);

        self::assertSame(1000, $cart->id);
        self::assertSame(1, $cart->id_shop);
        self::assertSame(1, $cart->id_shop_group);
        self::assertSame(1, $cart->id_lang);
        self::assertSame(1, $cart->id_currency);
        self::assertSame(31, $cart->id_guest);
        self::assertSame(0, $cart->id_customer, 'a visitor cart has no customer');
        self::assertSame([[3, 10, 0, false, 'up', 0, null, true]], Stubs::callsTo('Cart->updateQty'));
        self::assertSame($shopperCart, $this->context()->cart, 'the shopper cart stays in the context');

        $cookie = $this->context()->cookie;
        self::assertSame(1000, $cookie->{ExpressCheckoutService::COOKIE_CART});
        self::assertSame(5, $cookie->{ExpressCheckoutService::COOKIE_RESTORE_CART});
        self::assertGreaterThan(0, $cookie->writes);
    }

    public function testStartFromProductAddsAtLeastOneUnitForALoggedInCustomer(): void
    {
        $this->sellableProduct();
        $customer = new \Customer();
        $customer->id = 4;
        $customer->secure_key = 'abc123';
        $customer->stub('isLogged', true);
        $this->context()->customer = $customer;
        $this->context()->cookie->{ExpressCheckoutService::COOKIE_READY} = 99;

        $cart = $this->service()->startFromProduct(10, 0, [], 0);

        self::assertSame(4, $cart->id_customer);
        self::assertSame('abc123', $cart->secure_key);
        self::assertSame(1, Stubs::callsTo('Cart->updateQty')[0][0]);
        self::assertSame(0, $this->context()->cookie->{ExpressCheckoutService::COOKIE_RESTORE_CART}, 'no shopper cart to restore');
        self::assertFalse(isset($this->context()->cookie->{ExpressCheckoutService::COOKIE_READY}), 'an old authorization is dropped');
    }

    public function unsellableProducts(): array
    {
        return [
            'unknown product' => [null, true, []],
            'inactive' => [['active' => 0, 'available_for_order' => 1], true, []],
            'not for sale' => [['active' => 1, 'available_for_order' => 0], true, []],
            'no access' => [['active' => 1, 'available_for_order' => 1], false, []],
            'required customization' => [['active' => 1, 'available_for_order' => 1], true, [['id_customization_field' => 1]]],
        ];
    }

    /**
     * @dataProvider unsellableProducts
     */
    public function testStartFromProductRejectsProductsThatCannotBeBought($props, bool $access, array $customizationFields): void
    {
        if ($props !== null) {
            Stubs::object('Product', 10, $props);
        }
        Stubs::returns('Product->checkAccess', $access);
        Stubs::returns('Product::getRequiredCustomizableFieldsStatic', $customizationFields);

        $this->expectExpressError(function () {
            $this->service()->startFromProduct(10, 0, [], 1);
        }, 'This product cannot be bought with Apple Pay.');

        self::assertSame([], Stubs::persisted('Cart'), 'no cart is created');
    }

    public function quantityFailures(): array
    {
        return [
            'below minimal quantity' => [-1, 'Please select the minimum quantity for this product.'],
            'out of stock' => [false, 'There are not enough products in stock.'],
        ];
    }

    /**
     * @dataProvider quantityFailures
     */
    public function testStartFromProductDeletesTheCartWhenTheQuantityCannotBeAdded($result, string $message): void
    {
        $this->sellableProduct();
        Stubs::returns('Cart->updateQty', $result);

        $this->expectExpressError(function () {
            $this->service()->startFromProduct(10, 0, [], 1);
        }, $message);

        $deleted = Stubs::persisted('Cart', 'delete');
        self::assertCount(1, $deleted);
        self::assertSame(1000, $deleted[0]->id);
        self::assertFalse(isset($this->context()->cookie->{ExpressCheckoutService::COOKIE_CART}));
    }

    public function testStartFromProductFailsWhenTheCartCannotBeSaved(): void
    {
        $this->sellableProduct();
        Stubs::returns('Cart->add', false);

        $this->expectExpressError(function () {
            $this->service()->startFromProduct(10, 0, [], 1);
        }, 'Your shopping cart could not be prepared for Apple Pay. Please try again.');

        self::assertSame(0, Stubs::callCount('Cart->updateQty'));
    }

    public function testStartFromProductResolvesTheCombinationFromThePostedAttributes(): void
    {
        $this->sellableProduct();
        Stubs::returns('Product->hasAttributes', 2);
        Stubs::returns('Product::getIdProductAttributesByIdAttributes', 33);
        Stubs::object('Combination', 33, ['id_product' => 10]);

        $this->service()->startFromProduct(10, 12, ['1' => '5', '2' => '0', '3' => 'x9'], 1);

        self::assertSame([[10, [5], true]], Stubs::callsTo('Product::getIdProductAttributesByIdAttributes'));
        self::assertSame(33, Stubs::callsTo('Cart->updateQty')[0][2]);
    }

    public function testStartFromProductUsesThePostedCombinationWithoutAttributes(): void
    {
        $this->sellableProduct();
        Stubs::returns('Product->hasAttributes', 1);
        Stubs::object('Combination', 12, ['id_product' => 10]);

        $this->service()->startFromProduct(10, 12, [], 1);

        self::assertSame(0, Stubs::callCount('Product::getDefaultAttribute'));
        self::assertSame(12, Stubs::callsTo('Cart->updateQty')[0][2]);
    }

    public function testStartFromProductFallsBackToTheDefaultCombination(): void
    {
        $this->sellableProduct();
        Stubs::returns('Product->hasAttributes', 1);
        Stubs::returns('Product::getIdProductAttributesByIdAttributes', 0);
        Stubs::returns('Product::getDefaultAttribute', 44);
        Stubs::object('Combination', 44, ['id_product' => 10]);

        $this->service()->startFromProduct(10, 0, [7], 1);

        self::assertSame(44, Stubs::callsTo('Cart->updateQty')[0][2]);
    }

    public function testStartFromProductRejectsACombinationOfAnotherProduct(): void
    {
        $this->sellableProduct();
        Stubs::returns('Product->hasAttributes', 1);
        Stubs::object('Combination', 12, ['id_product' => 11]);

        $this->expectExpressError(function () {
            $this->service()->startFromProduct(10, 12, [], 1);
        }, 'This product cannot be bought with Apple Pay.');
    }

    public function testStartingAgainDeletesTheUnpaidPreviousExpressCart(): void
    {
        $this->sellableProduct();
        Stubs::object('Cart', 900, ['id_shop' => 1]);
        $this->context()->cookie->{ExpressCheckoutService::COOKIE_CART} = 900;

        $this->service()->startFromProduct(10, 0, [], 1);

        $deleted = Stubs::persisted('Cart', 'delete');
        self::assertCount(1, $deleted);
        self::assertSame(900, $deleted[0]->id);
        self::assertSame(1000, $this->context()->cookie->{ExpressCheckoutService::COOKIE_CART});
    }

    public function testThePreviousExpressCartIsKeptWhenItBecameTheShoppersCart(): void
    {
        $this->sellableProduct();
        Stubs::object('Cart', 900, ['id_shop' => 1]);
        $this->context()->cookie->{ExpressCheckoutService::COOKIE_CART} = 900;
        $this->context()->cookie->id_cart = 900;

        $this->service()->startFromProduct(10, 0, [], 1);

        self::assertSame([], Stubs::persisted('Cart', 'delete'));
    }

    // ------------------------------------------------------------------
    // startFromCart()
    // ------------------------------------------------------------------

    private function shopperCart(int $products = 1): \Cart
    {
        $cart = new \Cart();
        $cart->id = 5;
        $cart->products = array_fill(0, $products, ['id_product' => 1]);
        $this->context()->cart = $cart;

        return $cart;
    }

    public function testStartFromCartCopiesTheCartWithItsVouchers(): void
    {
        $source = $this->shopperCart();
        $copy = new \Cart();
        $copy->id = 600;
        $source->stub('duplicate', ['success' => true, 'cart' => $copy]);
        $source->stub('getCartRules', [['id_cart_rule' => '3'], ['id_cart_rule' => 4]]);

        $cart = $this->service()->startFromCart();

        self::assertSame($copy, $cart);
        self::assertSame([[3], [4]], Stubs::callsTo('Cart->addCartRule'));
        self::assertSame(600, $this->context()->cookie->{ExpressCheckoutService::COOKIE_CART});
        self::assertSame(0, $this->context()->cookie->{ExpressCheckoutService::COOKIE_RESTORE_CART});
        self::assertSame($source, $this->context()->cart, 'the shopper cart is not touched');
    }

    public function testStartFromCartRejectsAMissingCart(): void
    {
        $this->context()->cart = null;

        $this->expectExpressError(function () {
            $this->service()->startFromCart();
        }, 'Your shopping cart is empty.');
    }

    public function testStartFromCartRejectsAnEmptyCart(): void
    {
        $this->shopperCart(0);

        $this->expectExpressError(function () {
            $this->service()->startFromCart();
        }, 'Your shopping cart is empty.');
    }

    public function testStartFromCartRejectsACartThatIsAlreadyAnOrder(): void
    {
        $this->shopperCart();
        Stubs::returns('Order::getIdByCartId', 77);

        $this->expectExpressError(function () {
            $this->service()->startFromCart();
        }, 'Your shopping cart is empty.');
    }

    public function failedDuplications(): array
    {
        return [
            'no result' => [false],
            'not successful' => [['success' => false, 'cart' => null]],
            'cart not saved' => [['success' => true, 'cart' => new \Cart()]],
        ];
    }

    /**
     * @dataProvider failedDuplications
     */
    public function testStartFromCartFailsWhenTheCartCannotBeCopied($duplication): void
    {
        $this->shopperCart()->stub('duplicate', $duplication);

        $this->expectExpressError(function () {
            $this->service()->startFromCart();
        }, 'Your shopping cart could not be prepared for Apple Pay. Please try again.');

        self::assertFalse(isset($this->context()->cookie->{ExpressCheckoutService::COOKIE_CART}));
    }

    // ------------------------------------------------------------------
    // getExpressCart() / isAvailable()
    // ------------------------------------------------------------------

    public function testGetExpressCartReturnsTheCartOfTheSession(): void
    {
        Stubs::object('Cart', 600, ['id_shop' => 1]);
        $this->context()->cookie->{ExpressCheckoutService::COOKIE_CART} = '600';

        $cart = $this->service()->getExpressCart();

        self::assertInstanceOf(\Cart::class, $cart);
        self::assertSame(600, $cart->id);
    }

    public function testGetExpressCartIsEmptyWithoutCookie(): void
    {
        self::assertNull($this->service()->getExpressCart());
    }

    public function testGetExpressCartIgnoresACartThatBecameAnOrder(): void
    {
        Stubs::object('Cart', 600, ['id_shop' => 1]);
        $this->context()->cookie->{ExpressCheckoutService::COOKIE_CART} = 600;
        Stubs::returns('Order::getIdByCartId', 12);

        self::assertNull($this->service()->getExpressCart());
    }

    public function testGetExpressCartIgnoresADeletedCart(): void
    {
        $this->context()->cookie->{ExpressCheckoutService::COOKIE_CART} = 600;

        self::assertNull($this->service()->getExpressCart());
    }

    public function testGetExpressCartIgnoresACartOfAnotherShop(): void
    {
        Stubs::object('Cart', 600, ['id_shop' => 2]);
        $this->context()->cookie->{ExpressCheckoutService::COOKIE_CART} = 600;

        self::assertNull($this->service()->getExpressCart());
    }

    public function testIsAvailableChecksApplePayWithTheExpressCartInTheContext(): void
    {
        $cart = $this->cart();
        $shopperCart = new \Cart();
        $this->context()->cart = $shopperCart;
        $seen = [];
        $paymentService = $this->createMock(BuckarooPaymentService::class);
        $paymentService->expects(self::exactly(2))->method('isMethodAvailableForCart')
            ->with($cart, 'applepay')
            ->willReturnCallback(function () use (&$seen) {
                $seen[] = \Context::getContext()->cart;

                return count($seen) === 1;
            });
        $service = new ExpressCheckoutService($this->module, $this->context(), $paymentService);

        self::assertTrue($service->isAvailable($cart));
        self::assertFalse($service->isAvailable($cart));
        self::assertSame([$cart, $cart], $seen);
        self::assertSame($shopperCart, $this->context()->cart);
    }

    // ------------------------------------------------------------------
    // estimate()
    // ------------------------------------------------------------------

    public function testEstimateReturnsShippingMethodsLineItemsAndTheTotalWithFee(): void
    {
        $cart = $this->cart($this->options(), [\Cart::ONLY_DISCOUNTS => 5.0, \Cart::ONLY_WRAPPING => 2.0]);
        $this->module->fee = ['buckaroo_fee_tax_incl' => 1.5];
        \Configuration::updateValue('BUCKAROO_PAYMENT_FEE_FRONTEND_LABEL', 'Apple Pay fee');

        $summary = $this->service()->estimate($cart, ['countryCode' => 'nl', 'postalCode' => ' 1234ab ', 'locality' => 'Amsterdam'], null);

        self::assertSame([
            ['identifier' => '5,', 'label' => 'PostNL', 'detail' => '1-2 days', 'amount' => '4.95'],
            ['identifier' => '7,', 'label' => 'DHL', 'detail' => 'Next day', 'amount' => '6.50'],
        ], $summary['shippingMethods']);
        self::assertSame('5,', $summary['selectedShipping']);
        self::assertSame(['label' => 'Test Shop', 'amount' => '45.95', 'type' => 'final'], $summary['total']);

        $labels = array_column($summary['lineItems'], 'amount', 'label');
        self::assertSame('30.00', $labels['2 x T-shirt - M, Blue']);
        self::assertSame('9.50', $labels['1 x Mug'], 'markup is stripped');
        self::assertSame('-5.00', $labels['Discount']);
        self::assertSame('2.00', $labels['Gift wrapping']);
        self::assertSame('4.95', $labels['PostNL']);
        self::assertSame('1.50', $labels['Apple Pay fee']);
        self::assertSame(['applepay', 44.45], $this->module->feeCalls[0]);
    }

    public function testEstimateStoresAHiddenAddressAndPointsTheCartLinesAtIt(): void
    {
        $cart = $this->cart($this->options());

        $this->service()->estimate($cart, ['countryCode' => 'NL', 'postalCode' => '1234ab', 'locality' => 'Utrecht'], null);

        $saved = Stubs::persisted('Address', 'save');
        self::assertCount(1, $saved);
        $address = $saved[0];
        self::assertSame(0, $address->id_customer);
        self::assertSame('Apple Pay', $address->alias);
        self::assertTrue($address->deleted);
        self::assertSame(self::NL, $address->id_country);
        self::assertSame('1234AB', $address->postcode);
        self::assertSame('Utrecht', $address->city);
        self::assertSame($address->id, $cart->id_address_delivery);
        self::assertSame($address->id, $cart->id_address_invoice);
        self::assertSame($address->id, $this->context()->cookie->{ExpressCheckoutService::COOKIE_ADDRESS});

        $updates = Stubs::sql('update');
        self::assertStringContainsString('UPDATE `ps_cart_product` {"id_address_delivery":' . $address->id . '} WHERE `id_cart` = 500', $updates[0]);
        self::assertStringContainsString('UPDATE `ps_customization`', $updates[1]);
        self::assertSame(['5,'], self::selectedOptions());
        self::assertSame(self::NL, $this->context()->country->id, 'the context country is restored');
    }

    public function testEstimateReusesTheHiddenAddressOfTheSession(): void
    {
        Stubs::object('Address', 321, ['id_customer' => 0, 'deleted' => 1]);
        $this->context()->cookie->{ExpressCheckoutService::COOKIE_ADDRESS} = 321;

        $this->service()->estimate($this->cart($this->options()), ['countryCode' => 'NL'], null);

        self::assertSame(321, Stubs::persisted('Address', 'save')[0]->id);
    }

    public function testEstimateDoesNotOverwriteARealCustomerAddress(): void
    {
        Stubs::object('Address', 321, ['id_customer' => 9, 'deleted' => 0]);
        $this->context()->cookie->{ExpressCheckoutService::COOKIE_ADDRESS} = 321;

        $this->service()->estimate($this->cart($this->options()), ['countryCode' => 'NL'], null);

        self::assertSame(1000, Stubs::persisted('Address', 'save')[0]->id);
    }

    public function testEstimateBlanksInvalidPostcodeAndCity(): void
    {
        $this->service()->estimate($this->cart($this->options()), ['countryCode' => 'NL', 'postalCode' => '12$4', 'locality' => 'Am<ster>dam'], null);

        $address = Stubs::persisted('Address', 'save')[0];
        self::assertSame('', $address->postcode);
        self::assertSame('-', $address->city);
    }

    public function testEstimateUsesTheVisitorCountryWithoutContact(): void
    {
        $summary = $this->service()->estimate($this->cart($this->options()), [], null);

        self::assertSame(self::NL, Stubs::persisted('Address', 'save')[0]->id_country);
        self::assertSame('-', Stubs::persisted('Address', 'save')[0]->city);
        self::assertSame('44.45', $summary['total']['amount']);
        self::assertCount(3, $summary['lineItems'], 'two products and shipping');
    }

    public function testEstimateRejectsUnknownAndInactiveCountries(): void
    {
        $this->country(33, 'FR', ['active' => false]);

        $this->expectExpressError(function () {
            $this->service()->estimate($this->cart($this->options()), ['countryCode' => 'XX'], null);
        }, 'We do not deliver to this country. Please choose another address.', 'country');
        $this->expectExpressError(function () {
            $this->service()->estimate($this->cart($this->options()), ['countryCode' => 'FR'], null);
        }, 'We do not deliver to this country. Please choose another address.', 'country');
    }

    public function testEstimateFailsWhenTheHiddenAddressCannotBeSaved(): void
    {
        Stubs::returns('Address->save', false);

        $this->expectExpressError(function () {
            $this->service()->estimate($this->cart($this->options()), ['countryCode' => 'NL'], null);
        }, 'We could not calculate the shipping costs. Please try again.');
    }

    public function testEstimateRejectsAnAddressWithoutCarrier(): void
    {
        $cart = $this->cart([]);
        $shopperCart = new \Cart();
        $this->context()->cart = $shopperCart;

        $this->expectExpressError(function () use ($cart) {
            $this->service()->estimate($cart, ['countryCode' => 'NL'], null);
        }, 'We do not deliver to this address. Please choose another address.', 'postalAddress');

        self::assertSame(['null'], self::selectedOptions(), 'the carrier is cleared');
        self::assertSame($shopperCart, $this->context()->cart, 'the context is restored after an error');
    }

    public function testEstimateOfAVirtualCartHasNoShipping(): void
    {
        $cart = $this->cart([]);
        $cart->stub('isVirtualCart', true);
        $cart->totals[\Cart::BOTH] = 39.5;

        $summary = $this->service()->estimate($cart, ['countryCode' => 'NL'], null);

        self::assertSame([], $summary['shippingMethods']);
        self::assertNull($summary['selectedShipping']);
        self::assertSame('39.50', $summary['total']['amount']);
        self::assertSame(['2 x T-shirt - M, Blue', '1 x Mug'], array_column($summary['lineItems'], 'label'));
        self::assertSame(0, Stubs::callCount('Cart->getDeliveryOptionList'));
    }

    public function testEstimateKeepsTheCurrentCarrierOfTheCart(): void
    {
        $cart = $this->cart($this->options());
        $cart->stub('getDeliveryOption', function () use ($cart) {
            return [(int) $cart->id_address_delivery => '7,'];
        });

        $summary = $this->service()->estimate($cart, ['countryCode' => 'NL'], null);

        self::assertSame(['7,', '5,'], array_column($summary['shippingMethods'], 'identifier'), 'the selected method comes first');
        self::assertSame('7,', $summary['selectedShipping']);
    }

    public function testEstimateUsesTheDefaultLabelWhenTheFeeLabelIsEmpty(): void
    {
        $this->module->fee = ['buckaroo_fee_tax_incl' => '0.99'];

        $summary = $this->service()->estimate($this->cart($this->options()), ['countryCode' => 'NL'], null);

        $items = array_column($summary['lineItems'], 'amount', 'label');
        self::assertSame('0.99', $items['Payment fee']);
        self::assertSame('45.44', $summary['total']['amount']);
    }

    public function testShippingMethodLabels(): void
    {
        $long = str_repeat('Very long carrier name ', 4);
        $options = [
            '1,' => ['carrier_list' => [['instance' => $this->carrier('0', [2 => 'other language'])]], 'total_price_with_tax' => 0],
            '2,' => ['carrier_list' => [['instance' => new \stdClass()]]],
            '3,' => ['carrier_list' => [['instance' => $this->carrier($long)], ['instance' => $this->carrier('B', 'Tomorrow')]], 'total_price_with_tax' => '3.333'],
        ];

        $methods = $this->service()->estimate($this->cart($options), ['countryCode' => 'NL'], null)['shippingMethods'];

        self::assertSame(['identifier' => '1,', 'label' => 'Test Shop', 'detail' => '', 'amount' => '0.00'], $methods[0]);
        self::assertSame(['identifier' => '2,', 'label' => 'Shipping', 'detail' => '', 'amount' => '0.00'], $methods[1]);
        self::assertSame(60, strlen($methods[2]['label']));
        self::assertStringEndsWith('...', $methods[2]['label']);
        self::assertSame('Tomorrow', $methods[2]['detail']);
        self::assertSame('3.33', $methods[2]['amount']);
    }

    public function stateCases(): array
    {
        return [
            'iso code' => ['ca', ['CA' => 5], [], 5],
            'country prefixed code' => ['NY', ['US-NY' => 6], [], 6],
            'state name' => ['New Hampshire', [], [['id_state' => 8, 'name' => 'new hampshire']], 8],
            'unknown' => ['Atlantis', [], [['id_state' => 8, 'name' => 'Ohio']], 0],
            'missing' => ['  ', [], [], 0],
        ];
    }

    /**
     * @dataProvider stateCases
     */
    public function testEstimateResolvesTheStateOfTheAdministrativeArea(string $area, array $isoMap, array $states, int $expected): void
    {
        Stubs::returns('State::getIdByIso', function ($iso, $idCountry) use ($isoMap) {
            return $idCountry === self::US ? ($isoMap[$iso] ?? false) : false;
        });
        Stubs::returns('State::getStatesByIdCountry', $states);

        $this->service()->estimate($this->cart($this->options()), ['countryCode' => 'US', 'administrativeArea' => $area], null);

        self::assertSame($expected, Stubs::persisted('Address', 'save')[0]->id_state);
    }

    public function testCountriesWithoutStatesIgnoreTheArea(): void
    {
        $this->service()->estimate($this->cart($this->options()), ['countryCode' => 'NL', 'administrativeArea' => 'NH'], null);

        self::assertSame(0, Stubs::persisted('Address', 'save')[0]->id_state);
        self::assertSame(0, Stubs::callCount('State::getIdByIso'));
    }

    // ------------------------------------------------------------------
    // changeShipping()
    // ------------------------------------------------------------------

    public function testChangeShippingSelectsTheRequestedMethod(): void
    {
        $cart = $this->cart($this->options());
        $cart->id_address_delivery = 1234;
        Stubs::returns('Address::getCountryAndState', ['id_country' => self::US]);
        $countries = [];
        $cart->stub('getDeliveryOptionList', function () use (&$countries) {
            $countries[] = \Context::getContext()->country->id;

            return [1234 => $this->options()];
        });

        $summary = $this->service()->changeShipping($cart, '7,');

        self::assertSame('7,', $summary['selectedShipping']);
        self::assertSame([self::US], $countries, 'carriers are calculated for the address country');
        self::assertSame(self::NL, $this->context()->country->id);
        self::assertSame(['7,'], self::selectedOptions());
        self::assertSame([[1234]], Stubs::callsTo('Address::getCountryAndState'));
    }

    public function testChangeShippingRejectsAMethodThatIsNotAvailable(): void
    {
        $cart = $this->cart($this->options());
        Stubs::returns('Address::getCountryAndState', ['id_country' => 0]);

        $this->expectExpressError(function () use ($cart) {
            $this->service()->changeShipping($cart, '9,');
        }, 'The selected shipping method is not available for this address.', 'postalAddress');
    }

    public function testChangeShippingOfAVirtualCart(): void
    {
        $cart = $this->cart([]);
        $cart->stub('isVirtualCart', true);
        Stubs::returns('Address::getCountryAndState', ['id_country' => 0]);

        $summary = $this->service()->changeShipping($cart, 'anything');

        self::assertSame([], $summary['shippingMethods']);
        self::assertSame('44.45', $summary['total']['amount']);
    }

    // ------------------------------------------------------------------
    // authorize()
    // ------------------------------------------------------------------

    private function contact(array $overrides = []): array
    {
        return $overrides + [
            'givenName' => 'Anna',
            'familyName' => 'de Vries',
            'emailAddress' => 'anna@example.com',
            'phoneNumber' => '+31 6 12345678',
            'addressLines' => ['Damrak 1', ' ', 'Floor 2', ['x']],
            'locality' => 'Amsterdam',
            'postalCode' => '1012lg',
            'countryCode' => 'NL',
        ];
    }

    private function payment(array $shipping = [], array $billing = []): array
    {
        return [
            'shippingContact' => $this->contact($shipping),
            'billingContact' => $this->contact($billing + ['emailAddress' => null, 'phoneNumber' => null]),
        ];
    }

    public function testAuthorizeCreatesAGuestWithAddressAndMakesTheExpressCartTheShoppersCart(): void
    {
        $cart = $this->cart($this->options());
        $shopperCart = new \Cart();
        $shopperCart->id = 5;
        $this->context()->cart = $shopperCart;

        $this->service()->authorize($cart, $this->payment(), '5,', 44.45);

        $customers = Stubs::persisted('Customer', 'add');
        self::assertCount(1, $customers);
        $customer = $customers[0];
        self::assertSame('Anna', $customer->firstname);
        self::assertSame('de Vries', $customer->lastname);
        self::assertSame('anna@example.com', $customer->email);
        self::assertTrue($customer->is_guest);
        self::assertTrue(password_verify(\Tools::passwdGen(32), $customer->passwd));
        self::assertSame(1, $customer->id_shop);
        self::assertSame($customer->id, $this->context()->cookie->{ExpressCheckoutService::COOKIE_GUEST});

        $addresses = Stubs::persisted('Address', 'add');
        self::assertCount(1, $addresses, 'identical delivery and invoice address is stored once');
        $address = $addresses[0];
        self::assertSame($customer->id, $address->id_customer);
        self::assertSame('Damrak 1', $address->address1);
        self::assertSame('Floor 2', $address->address2);
        self::assertSame('1012LG', $address->postcode);
        self::assertSame('Amsterdam', $address->city);
        self::assertSame(self::NL, $address->id_country);
        self::assertSame('+31 6 12345678', $address->phone);
        self::assertSame('Apple Pay', $address->alias);

        self::assertSame($customer->id, $cart->id_customer);
        self::assertSame($address->id, $cart->id_address_delivery);
        self::assertSame($address->id, $cart->id_address_invoice);
        self::assertSame($cart, $this->context()->cart);
        self::assertSame(500, $this->context()->cookie->id_cart);
        self::assertSame([[$customer]], Stubs::callsTo('Context->updateCustomer'));
        self::assertSame(500, $this->context()->cookie->{ExpressCheckoutService::COOKIE_READY});
        self::assertSame(self::NL, $this->context()->country->id);
        self::assertSame(['5,', '5,', '5,'], self::selectedOptions(), 'the carrier is verified three times');
    }

    public function testAuthorizeStoresADifferentInvoiceAddressSeparately(): void
    {
        $cart = $this->cart($this->options());

        $this->service()->authorize($cart, $this->payment([], ['addressLines' => ['Coolsingel 40'], 'locality' => 'Rotterdam', 'postalCode' => '3011AD']), '5,', 44.45);

        $addresses = Stubs::persisted('Address', 'add');
        self::assertCount(2, $addresses);
        self::assertSame('Damrak 1', $addresses[0]->address1);
        self::assertSame('Coolsingel 40', $addresses[1]->address1);
        self::assertSame('', $addresses[1]->address2);
        self::assertSame($addresses[0]->id, $cart->id_address_delivery);
        self::assertSame($addresses[1]->id, $cart->id_address_invoice);
    }

    public function testAuthorizeUsesTheLoggedInCustomerAndReusesAMatchingAddress(): void
    {
        $customer = new \Customer();
        $customer->id = 4;
        $customer->stub('isLogged', true);
        $this->context()->customer = $customer;
        Stubs::object('Customer', 4, ['email' => 'known@example.com', 'secure_key' => 'k4']);
        Stubs::returns('Customer->getAddresses', [
            ['id_address' => 70, 'firstname' => 'Anna', 'lastname' => 'de Vries', 'address1' => 'Other 1', 'address2' => 'Floor 2', 'postcode' => '1012LG', 'city' => 'Amsterdam', 'id_country' => self::NL, 'id_state' => 0],
            ['id_address' => 71, 'firstname' => 'ANNA', 'lastname' => 'de vries', 'address1' => ' damrak 1 ', 'address2' => 'floor 2', 'postcode' => '1012lg', 'city' => 'AMSTERDAM', 'id_country' => (string) self::NL, 'id_state' => '0'],
        ]);
        $cart = $this->cart($this->options());

        $this->service()->authorize($cart, $this->payment(['phoneNumber' => 'call me']), '5,', 44.45);

        self::assertSame([], Stubs::persisted('Customer', 'add'));
        self::assertSame([], Stubs::persisted('Address', 'add'));
        self::assertSame(4, $cart->id_customer);
        self::assertSame('k4', $cart->secure_key);
        self::assertSame(71, $cart->id_address_delivery);
        self::assertSame(71, $cart->id_address_invoice);
        self::assertSame(0, Stubs::callCount('Context->updateCustomer'), 'a logged in customer stays signed in');
        self::assertSame($customer, $this->context()->customer);
    }

    public function testAuthorizeStoresAnInvalidPhoneNumberAsEmpty(): void
    {
        $this->service()->authorize($this->cart($this->options()), $this->payment(['phoneNumber' => 'call me']), '5,', 44.45);

        self::assertSame('', Stubs::persisted('Address', 'add')[0]->phone);
    }

    public function testAuthorizeReusesTheGuestOfAnEarlierAttempt(): void
    {
        Stubs::object('Customer', 77, ['is_guest' => 1, 'deleted' => 0, 'email' => 'ANNA@example.com']);
        $this->context()->cookie->{ExpressCheckoutService::COOKIE_GUEST} = 77;
        $cart = $this->cart($this->options());

        $this->service()->authorize($cart, $this->payment(), '5,', 44.45);

        self::assertSame([], Stubs::persisted('Customer', 'add'));
        self::assertSame(77, $cart->id_customer);
    }

    public function testAuthorizeCreatesANewGuestWhenTheEmailChanged(): void
    {
        Stubs::object('Customer', 77, ['is_guest' => 1, 'deleted' => 0, 'email' => 'other@example.com']);
        $this->context()->cookie->{ExpressCheckoutService::COOKIE_GUEST} = 77;

        $this->service()->authorize($this->cart($this->options()), $this->payment(), '5,', 44.45);

        self::assertCount(1, Stubs::persisted('Customer', 'add'));
    }

    public function testAuthorizeOfAVirtualCartUsesTheBillingContact(): void
    {
        $cart = $this->cart([]);
        $cart->stub('isVirtualCart', true);
        $payment = ['billingContact' => $this->contact(), 'shippingContact' => ['emailAddress' => 'ship@example.com']];

        $this->service()->authorize($cart, $payment, '', 44.45);

        self::assertSame('ship@example.com', Stubs::persisted('Customer', 'add')[0]->email);
        self::assertSame('Damrak 1', Stubs::persisted('Address', 'add')[0]->address1);
        self::assertSame([], self::selectedOptions());
    }

    public function testAuthorizeOfAVirtualCartWithoutShippingContact(): void
    {
        $cart = $this->cart([]);
        $cart->stub('isVirtualCart', true);

        $this->service()->authorize($cart, ['billingContact' => $this->contact()], '', 44.45);

        self::assertSame('anna@example.com', Stubs::persisted('Customer', 'add')[0]->email);
    }

    public function testAuthorizeRequiresABillingContact(): void
    {
        $this->expectExpressError(function () {
            $this->service()->authorize($this->cart($this->options()), ['shippingContact' => $this->contact(), 'billingContact' => 'x'], '5,', 44.45);
        }, 'Apple Pay did not share a billing address. Please try again.', 'postalAddress');
    }

    public function testAuthorizeRequiresAShippingContactForPhysicalProducts(): void
    {
        $this->expectExpressError(function () {
            $this->service()->authorize($this->cart($this->options()), ['billingContact' => $this->contact(), 'shippingContact' => ['countryCode' => '']], '5,', 44.45);
        }, 'Apple Pay did not share a shipping address. Please try again.', 'postalAddress');
    }

    public function invalidContacts(): array
    {
        return [
            'no first name' => [['givenName' => ''], 'Please enter a valid first and last name in Apple Pay.', 'name'],
            'invalid last name' => [['familyName' => 'R2D2'], 'Please enter a valid first and last name in Apple Pay.', 'name'],
            'unknown country' => [['countryCode' => 'ZZ'], 'We do not deliver to this country. Please choose another address.', 'country'],
            'no street' => [['addressLines' => []], 'Please enter a valid street address in Apple Pay.', 'addressLines'],
            'invalid street' => [['addressLines' => ['Main <st>']], 'Please enter a valid street address in Apple Pay.', 'addressLines'],
            'invalid second line' => [['addressLines' => ['Main 1', '{x}']], 'Please enter a valid street address in Apple Pay.', 'addressLines'],
            'no city' => [['locality' => ' '], 'Please enter a valid city in Apple Pay.', 'locality'],
            'invalid city' => [['locality' => 'C#ty'], 'Please enter a valid city in Apple Pay.', 'locality'],
            'invalid postcode' => [['postalCode' => '12_34'], 'Please enter a valid postal code in Apple Pay.', 'postalCode'],
        ];
    }

    /**
     * @dataProvider invalidContacts
     */
    public function testAuthorizeRejectsInvalidContactsBeforeCreatingAnything(array $shipping, string $message, string $field): void
    {
        $this->expectExpressError(function () use ($shipping) {
            $this->service()->authorize($this->cart($this->options()), $this->payment($shipping), '5,', 44.45);
        }, $message, $field);

        self::assertSame([], Stubs::persisted('Customer'));
        self::assertSame([], Stubs::persisted('Address', 'add'));
    }

    public function testAuthorizeChecksThePostcodeFormatOfTheCountry(): void
    {
        $checked = [];
        Stubs::returns('Country->checkZipCode', function ($postcode) use (&$checked) {
            $checked[] = $postcode;

            return false;
        });

        $this->expectExpressError(function () {
            $this->service()->authorize($this->cart($this->options()), $this->payment(), '5,', 44.45);
        }, 'Please enter a valid postal code in Apple Pay.', 'postalCode');
        self::assertSame(['1012LG'], $checked);
    }

    public function testAuthorizeRejectsAChangedTotalBeforeCreatingTheCustomer(): void
    {
        $cart = $this->cart($this->options());
        $this->module->fee = ['buckaroo_fee_tax_incl' => 0.5];

        $this->expectExpressError(function () use ($cart) {
            $this->service()->authorize($cart, $this->payment(), '5,', 44.45);
        }, 'The order total has changed. Please try again.');

        self::assertSame([], Stubs::persisted('Customer'));
        self::assertSame(0, $cart->id_customer);
    }

    public function testAuthorizeAcceptsARoundingDifferenceOfOneCent(): void
    {
        $cart = $this->cart($this->options());

        $this->service()->authorize($cart, $this->payment(), '5,', 44.46);

        self::assertSame(500, $this->context()->cookie->{ExpressCheckoutService::COOKIE_READY});
    }

    public function testAuthorizeRejectsTheTotalWhenTaxesOfTheRealAddressChangeIt(): void
    {
        $cart = $this->cart($this->options());
        $cart->stub('getOrderTotal', function ($withTaxes, $type) use ($cart) {
            if ($type === \Cart::BOTH) {
                return $cart->id_customer > 0 ? 50.0 : 44.45;
            }

            return $cart->totals[$type] ?? 0.0;
        });

        $this->expectExpressError(function () use ($cart) {
            $this->service()->authorize($cart, $this->payment(), '5,', 44.45);
        }, 'The order total has changed. Please try again.');

        self::assertCount(1, Stubs::persisted('Customer', 'add'));
        self::assertFalse(isset($this->context()->cookie->{ExpressCheckoutService::COOKIE_READY}));
    }

    public function testAuthorizeRejectsAShippingMethodThatIsNoLongerAvailable(): void
    {
        $this->expectExpressError(function () {
            $this->service()->authorize($this->cart($this->options()), $this->payment(), '9,', 44.45);
        }, 'The selected shipping method is not available for this address.', 'postalAddress');
    }

    public function testAuthorizeRejectsWhenApplePayIsNotAllowedForTheCart(): void
    {
        $this->applePayAvailable = false;

        $this->expectExpressError(function () {
            $this->service()->authorize($this->cart($this->options()), $this->payment(), '5,', 44.45);
        }, 'Apple Pay is not available for this order. Please choose another payment method.');
    }

    public function testAuthorizeRequiresSignInWithoutGuestCheckout(): void
    {
        \Configuration::updateValue('PS_GUEST_CHECKOUT_ENABLED', 0);

        $this->expectExpressError(function () {
            $this->service()->authorize($this->cart($this->options()), $this->payment(), '5,', 44.45);
        }, 'Please sign in to pay with Apple Pay.');
    }

    public function testAuthorizeRequiresAValidEmailForAGuest(): void
    {
        $this->expectExpressError(function () {
            $this->service()->authorize($this->cart($this->options()), $this->payment(['emailAddress' => 'not-an-email']), '5,', 44.45);
        }, 'Please enter a valid email address in Apple Pay.', 'emailAddress');
    }

    public function testAuthorizeFailsWhenTheGuestCannotBeSaved(): void
    {
        Stubs::returns('Customer->add', false);

        $this->expectExpressError(function () {
            $this->service()->authorize($this->cart($this->options()), $this->payment(), '5,', 44.45);
        }, 'We could not create your customer account. Please try again.');

        self::assertFalse(isset($this->context()->cookie->{ExpressCheckoutService::COOKIE_GUEST}));
    }

    public function testAuthorizeFailsWhenTheAddressCannotBeSaved(): void
    {
        Stubs::returns('Address->add', false);

        $this->expectExpressError(function () {
            $this->service()->authorize($this->cart($this->options()), $this->payment(), '5,', 44.45);
        }, 'We could not save your address. Please try again.', 'postalAddress');
    }

    // ------------------------------------------------------------------
    // consumeAuthorization() / restoreShopperCart()
    // ------------------------------------------------------------------

    public function testConsumeAuthorizationIsValidOnceForTheAuthorizedCart(): void
    {
        $cart = new \Cart();
        $cart->id = 500;
        $this->context()->cookie->{ExpressCheckoutService::COOKIE_READY} = '500';

        self::assertTrue($this->service()->consumeAuthorization($cart));
        self::assertFalse($this->service()->consumeAuthorization($cart));
        self::assertSame(2, $this->context()->cookie->writes);
    }

    public function testConsumeAuthorizationRejectsOtherCarts(): void
    {
        $this->context()->cookie->{ExpressCheckoutService::COOKIE_READY} = 500;
        $other = new \Cart();
        $other->id = 501;

        self::assertFalse($this->service()->consumeAuthorization($other));
        self::assertFalse(isset($this->context()->cookie->{ExpressCheckoutService::COOKIE_READY}));

        $this->context()->cookie->{ExpressCheckoutService::COOKIE_READY} = 0;
        self::assertFalse($this->service()->consumeAuthorization(new \Cart()));
    }

    private function expressOrder(int $restoreCartId): \Order
    {
        $cookie = $this->context()->cookie;
        $cookie->{ExpressCheckoutService::COOKIE_CART} = 500;
        $cookie->{ExpressCheckoutService::COOKIE_RESTORE_CART} = $restoreCartId;
        $cookie->{ExpressCheckoutService::COOKIE_ADDRESS} = 321;
        $cookie->{ExpressCheckoutService::COOKIE_READY} = 500;
        $cookie->{ExpressCheckoutService::COOKIE_GUEST} = 77;
        $cookie->id_cart = 500;
        $order = new \Order();
        $order->id_cart = 500;

        return $order;
    }

    public function testRestoreShopperCartGivesBackTheCartOfBeforeTheExpressOrder(): void
    {
        $order = $this->expressOrder(5);
        Stubs::object('Cart', 5, ['id_customer' => 0, 'products' => [['id_product' => 1]]]);

        $this->service()->restoreShopperCart($order);

        $cookie = $this->context()->cookie;
        self::assertSame(5, $cookie->id_cart);
        foreach ([ExpressCheckoutService::COOKIE_CART, ExpressCheckoutService::COOKIE_RESTORE_CART, ExpressCheckoutService::COOKIE_ADDRESS, ExpressCheckoutService::COOKIE_READY, ExpressCheckoutService::COOKIE_GUEST] as $name) {
            self::assertFalse(isset($cookie->{$name}), $name . ' is cleared');
        }
        self::assertSame(1, $cookie->writes);
    }

    public function testRestoreShopperCartAcceptsTheCartOfTheSameCustomer(): void
    {
        $customer = new \Customer();
        $customer->id = 4;
        $this->context()->customer = $customer;
        $order = $this->expressOrder(5);
        Stubs::object('Cart', 5, ['id_customer' => 4, 'products' => [['id_product' => 1]]]);

        $this->service()->restoreShopperCart($order);

        self::assertSame(5, $this->context()->cookie->id_cart);
    }

    public function cartsNotToRestore(): array
    {
        return [
            'no shopper cart' => [0, null, null],
            'deleted cart' => [5, null, null],
            'empty cart' => [5, ['id_customer' => 0, 'products' => []], null],
            'cart of someone else' => [5, ['id_customer' => 9, 'products' => [['id_product' => 1]]], null],
            'cart already ordered' => [5, ['id_customer' => 0, 'products' => [['id_product' => 1]]], 88],
        ];
    }

    /**
     * @dataProvider cartsNotToRestore
     */
    public function testRestoreShopperCartKeepsTheCurrentCart(int $restoreId, $cartProps, $orderId): void
    {
        $order = $this->expressOrder($restoreId);
        if ($cartProps !== null) {
            Stubs::object('Cart', 5, $cartProps);
        }
        Stubs::returns('Order::getIdByCartId', $orderId);

        $this->service()->restoreShopperCart($order);

        self::assertSame(500, $this->context()->cookie->id_cart);
        self::assertFalse(isset($this->context()->cookie->{ExpressCheckoutService::COOKIE_CART}));
    }

    public function testRestoreShopperCartIgnoresOrdersOfOtherCarts(): void
    {
        $order = $this->expressOrder(5);
        $order->id_cart = 499;

        $this->service()->restoreShopperCart($order);

        self::assertSame(500, $this->context()->cookie->{ExpressCheckoutService::COOKIE_CART});
        self::assertSame(0, $this->context()->cookie->writes);
    }

    public function testExpressExceptionHasNoContactFieldByDefault(): void
    {
        $exception = new ExpressException('Something');

        self::assertSame('Something', $exception->getMessage());
        self::assertSame(ExpressException::FIELD_NONE, $exception->getContactField());
        self::assertSame('postalCode', (new ExpressException('x', 'postalCode'))->getContactField());
    }
}
