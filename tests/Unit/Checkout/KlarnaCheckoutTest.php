<?php

namespace BuckarooTest\Unit\Checkout;

require_once __DIR__ . '/CheckoutTestCase.php';

class KlarnaCheckoutTest extends CheckoutTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->request(['method' => 'klarna']);
    }

    public function testPayload(): void
    {
        $this->taxRate(21.0);
        $checkout = $this->makeCheckout('klarna', $this->makeCart([
            \Cart::BOTH => 60.0,
            \Cart::ONLY_WRAPPING => 2.0,
            \Cart::ONLY_SHIPPING => 5.0,
        ], [
            ['id_product' => 2, 'quantity' => 1, 'price_wt' => 50, 'rate' => 9, 'name' => 'Book', 'link_rewrite' => 'book'],
        ]));
        $checkout->setCheckout();
        $vars = $this->customVars($checkout);

        self::assertSame('NL', $vars['operatingCountry']);
        self::assertSame([
            'recipient' => ['firstName' => 'Jan', 'lastName' => 'Jansen'],
            'address' => [
                'street' => 'Hoofdstraat',
                'houseNumber' => '12',
                // Regression: address2 used to be sent as the addition, dropping the parsed 'A'.
                'houseNumberAdditional' => 'A',
                'zipcode' => '1234AB',
                'city' => 'Amsterdam',
                'country' => 'NL',
            ],
            'email' => 'jan@example.com',
            'phone' => ['mobile' => '06-12345678'],
        ], $vars['billing']);
        self::assertSame('Kerkstraat', $vars['shipping']['address']['street']);
        self::assertSame('Utrecht', $vars['shipping']['address']['city']);
        self::assertSame(
            // Regression: the positive adjustment line used to be typed 'physical'.
            ['physical', 'surcharge', 'shipping_fee', 'surcharge'],
            array_column(array_values($vars['articles']), 'type')
        );
        self::assertSame(['Book', 'Wrapping', 'Shipping Costs', 'Adjustment'], array_column(array_values($vars['articles']), 'description'));
    }

    public function testPhoneIsLeftOutWhenUnknownAndLandlineIsUsedAsFallback(): void
    {
        $this->registerAddress(self::INVOICE_ADDRESS_ID, ['phone' => '', 'phone_mobile' => '']);
        $this->registerAddress(self::DELIVERY_ADDRESS_ID, ['phone' => '030-555', 'phone_mobile' => '']);
        $checkout = $this->makeCheckout('klarna');
        $checkout->setCheckout();
        $vars = $this->customVars($checkout);

        self::assertArrayNotHasKey('phone', $vars['billing']);
        self::assertSame(['mobile' => '030-555'], $vars['shipping']['phone']);
    }

    public function testHouseNumberFallsBackToAddress2(): void
    {
        $this->registerAddress(self::INVOICE_ADDRESS_ID, ['address1' => 'Hoofdstraat', 'address2' => '12']);
        $checkout = $this->makeCheckout('klarna');
        $checkout->setCheckout();
        $address = $this->customVars($checkout)['billing']['address'];

        self::assertSame('Hoofdstraat', $address['street']);
        self::assertSame('12', $address['houseNumber']);
        self::assertSame('', $address['houseNumberAdditional']);
    }

    public function testAddress2IsTheAdditionWhenAddress1HasTheNumber(): void
    {
        $this->registerAddress(self::INVOICE_ADDRESS_ID, ['address1' => 'Hoofdstraat 12', 'address2' => 'A']);
        $checkout = $this->makeCheckout('klarna');
        $checkout->setCheckout();
        $address = $this->customVars($checkout)['billing']['address'];

        self::assertSame('12', $address['houseNumber']);
        self::assertSame('A', $address['houseNumberAdditional']);
    }

    public function testParsedAdditionWinsOverAddress2(): void
    {
        $this->registerAddress(self::INVOICE_ADDRESS_ID, ['address1' => 'Hoofdstraat 12 B', 'address2' => 'Flat 3']);
        $checkout = $this->makeCheckout('klarna');
        $checkout->setCheckout();
        $address = $this->customVars($checkout)['billing']['address'];

        self::assertSame('12', $address['houseNumber']);
        self::assertSame('B', $address['houseNumberAdditional']);
    }

    public function testSendCloudServicePointOnlyReplacesZipcodeAndCity(): void
    {
        $this->sendCloudServicePoint([
            'street' => 'Servicepunt',
            'house_number' => '3',
            'postal_code' => '1000',
            'city' => 'Brussel',
            'country' => 'BE',
        ]);
        $cart = $this->makeCart();
        $cart->id_carrier = 9;
        $checkout = $this->makeCheckout('klarna', $cart);
        $checkout->setCheckout();

        self::assertSame([
            // Street and number still come from the customer's address1.
            'street' => 'Kerkstraat',
            'houseNumber' => '22',
            'houseNumberAdditional' => 'b',
            'zipcode' => '1000',
            'city' => 'Brussel',
            'country' => 'NL',
        ], $this->customVars($checkout)['shipping']['address']);
    }

    public function articleTypes(): array
    {
        return [
            'product' => [['identifier' => 7, 'price' => '10.00', 'description' => 'Shirt'], 'physical'],
            'shipping identifier' => [['identifier' => 'shipping', 'price' => '5.00', 'description' => 'x'], 'shipping_fee'],
            'shipping description' => [['identifier' => 'x', 'price' => '5.00', 'description' => 'Shipping Costs'], 'shipping_fee'],
            'negative price' => [['identifier' => 'adjustment', 'price' => '-0.02', 'description' => 'Adjustment'], 'discount'],
            'positive adjustment' => [['identifier' => 'adjustment', 'price' => '0.02', 'description' => 'Adjustment'], 'surcharge'],
            'discount description' => [['identifier' => 'd', 'price' => '1.00', 'description' => 'Discount'], 'discount'],
            'wrapping' => [['identifier' => '0', 'price' => '2.00', 'description' => 'Wrapping'], 'surcharge'],
            'fee identifier' => [['identifier' => 'buckaroo_fee', 'price' => '2.00', 'description' => 'Fee'], 'surcharge'],
            'fee description' => [['identifier' => 'f', 'price' => '2.00', 'description' => 'buckaroo_fee'], 'surcharge'],
            'empty article' => [[], 'physical'],
        ];
    }

    /**
     * @dataProvider articleTypes
     */
    public function testArticleType(array $article, string $type): void
    {
        $checkout = $this->makeCheckout('klarna');

        self::assertSame($type, self::invoke($checkout, 'resolveKlarnaArticleType', [$article]));
    }

    public function testExistingArticleTypeIsKept(): void
    {
        $checkout = $this->makeCheckout('klarna');

        $articles = self::invoke($checkout, 'prepareKlarnaArticles', [[
            ['identifier' => 'shipping', 'type' => 'digital'],
            ['identifier' => 'shipping'],
        ]]);

        self::assertSame('digital', $articles[0]['type']);
        self::assertSame('shipping_fee', $articles[1]['type']);
    }
}
