<?php

namespace BuckarooTest\Unit\Checkout;

require_once __DIR__ . '/CheckoutTestCase.php';

/**
 * In3 (current API) and In3Old (legacy capayable) checkouts.
 */
class In3CheckoutTest extends CheckoutTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->request([
            'customerbirthdate_y_billing' => '1985',
            'customerbirthdate_m_billing' => '04',
            'customerbirthdate_d_billing' => '23',
        ]);
    }

    private function cart(float $total): \Cart
    {
        return $this->makeCart([
            \Cart::BOTH => $total,
            \Cart::ONLY_WRAPPING => 1.5,
            \Cart::ONLY_DISCOUNTS => 5.0,
            \Cart::ONLY_SHIPPING => 4.95,
        ], [
            ['id_product' => 1, 'quantity' => 2, 'price_wt' => 10.005, 'rate' => 21, 'name' => 'Shirt'],
        ]);
    }

    public function methods(): array
    {
        return ['in3' => ['in3'], 'in3Old' => ['in3Old']];
    }

    /**
     * @dataProvider methods
     */
    public function testArticlesIncludeWrappingDiscountShippingAndOtherFees(string $method): void
    {
        $checkout = $this->makeCheckout($method, $this->cart(25.0));
        $checkout->setCheckout();

        $articles = $checkout->getArticles();

        // 10.005 rounds half up to 10.01 (it used to be truncated to 10.00).
        $product = ['description' => 'Shirt', 'identifier' => 1, 'quantity' => 2, 'price' => '10.01'];
        if ($method === 'in3') {
            $product['vatPercentage'] = 21;
        }
        self::assertSame([
            $product,
            ['description' => 'Wrapping', 'identifier' => 'WRAP', 'quantity' => 1, 'price' => '1.50'],
            ['description' => 'Discounts', 'identifier' => 'DISC', 'quantity' => 1, 'price' => '-5.00'],
            ['description' => 'Shipping', 'identifier' => 'SHIP', 'quantity' => 1, 'price' => '4.95'],
            // 25.00 - (2 x 10.01 + 1.50 - 5.00 + 4.95): the difference uses the rounded line prices.
            ['description' => 'Other fee/discount', 'identifier' => 'OFees', 'quantity' => 1, 'price' => '3.53'],
        ], $articles);
        // Regression: the lines add up to the amount (they were a cent off with the unrounded difference).
        $sum = 0.0;
        foreach ($articles as $article) {
            $sum += (float) $article['price'] * (float) $article['quantity'];
        }
        self::assertEqualsWithDelta(25.0, $sum, 0.0001);
        self::assertSame($articles, $this->customVars($checkout)['articles']);
    }

    /**
     * @dataProvider methods
     */
    public function testNoOtherFeesLineWhenTheLinesMatchTheTotal(string $method): void
    {
        $checkout = $this->makeCheckout($method, $this->makeCart([\Cart::BOTH => 20.0], [
            ['id_product' => 1, 'quantity' => 2, 'price_wt' => 10.0, 'rate' => 21, 'name' => 'Shirt'],
        ]));
        $checkout->setCheckout();

        $articles = $checkout->getArticles();

        self::assertCount(1, $articles);
        self::assertSame('10.00', $articles[0]['price']);
    }

    /**
     * Regression: 2 x 10.005 used to become 2 x 10.00 plus a 0.01 fee line,
     * 20.01 in total for a 20.02 order.
     *
     * @dataProvider methods
     */
    public function testRoundedLinesMatchTheTotalWithoutAnOtherFeesLine(string $method): void
    {
        $checkout = $this->makeCheckout($method, $this->makeCart([\Cart::BOTH => 20.02], [
            ['id_product' => 1, 'quantity' => 2, 'price_wt' => 10.005, 'rate' => 21, 'name' => 'Shirt'],
        ]));
        $checkout->setCheckout();

        $articles = $checkout->getArticles();

        self::assertCount(1, $articles);
        self::assertSame('10.01', $articles[0]['price']);
        self::assertSame('20.02', $this->paymentRequest($checkout)->amountDebit);
    }

    /**
     * @dataProvider methods
     */
    public function testDescriptionDefaultsToTheInvoiceId(string $method): void
    {
        $checkout = $this->makeCheckout($method);
        $checkout->setCheckout();

        self::assertSame('REF_5', $this->paymentRequest($checkout)->description);
    }

    /**
     * @dataProvider methods
     */
    public function testConfiguredDescriptionIsKept(string $method): void
    {
        \Configuration::updateValue('BUCKAROO_TRANSACTION_LABEL', 'My shop order');
        $checkout = $this->makeCheckout($method);
        $checkout->setCheckout();

        self::assertSame('My shop order', $this->paymentRequest($checkout)->description);
    }

    public function phones(): array
    {
        return [
            'in3 posted' => ['in3', ['customer_phone' => '0611'], '06-1', '020-1', '0611'],
            'in3 mobile' => ['in3', [], '06-1', '020-1', '06-1'],
            'in3 landline' => ['in3', [], '', '020-1', '020-1'],
            'in3Old posted' => ['in3Old', ['customer_phone' => '0611'], '06-1', '020-1', '0611'],
            'in3Old mobile' => ['in3Old', [], '06-1', '020-1', '06-1'],
            'in3Old landline' => ['in3Old', [], '', '020-1', '020-1'],
        ];
    }

    /**
     * @dataProvider phones
     */
    public function testPhone(string $method, array $post, string $mobile, string $phone, string $expected): void
    {
        $_POST = $post;
        $checkout = $this->makeCheckout($method);
        $address = new \Address();
        $address->phone_mobile = $mobile;
        $address->phone = $phone;

        self::assertSame($expected, $checkout->getPhone($address));
    }

    public function testIn3BillingAndShipping(): void
    {
        $checkout = $this->makeCheckout('in3');
        $checkout->setCheckout();
        $vars = $this->customVars($checkout);

        self::assertSame(date('d-m-Y'), $vars['invoiceDate']);
        self::assertSame([
            'recipient' => [
                'category' => 'B2C',
                'initials' => 'J.',
                'firstName' => 'Jan',
                'lastName' => 'Jansen',
                'birthDate' => '1985-04-23',
                'customerNumber' => self::CUSTOMER_ID,
                'phone' => '06-12345678',
                'country' => 'NL',
            ],
            'phone' => ['phone' => '06-12345678'],
            'email' => 'jan@example.com',
            'address' => [
                'street' => 'Hoofdstraat',
                'houseNumber' => '12',
                'zipcode' => '1234AB',
                'city' => 'Amsterdam',
                'country' => 'NL',
                'houseNumberAdditional' => 'A',
            ],
        ], $vars['billing']);
        self::assertSame(['address' => [
            'street' => 'Kerkstraat',
            'houseNumber' => '22',
            'zipcode' => '5678CD',
            'city' => 'Utrecht',
            'country' => 'NL',
            'houseNumberAdditional' => 'b',
        ]], $vars['shipping']);
    }

    public function testIn3GuestWithoutHouseNumberAddition(): void
    {
        $this->registerAddress(self::INVOICE_ADDRESS_ID, ['address1' => 'Hoofdstraat', 'address2' => '4']);
        $cart = $this->makeCart();
        $cart->id_customer = 0;
        $checkout = $this->makeCheckout('in3', $cart);
        $checkout->setCheckout();
        $billing = $this->customVars($checkout)['billing'];

        self::assertSame('guest', $billing['recipient']['customerNumber']);
        self::assertSame(['street' => 'Hoofdstraat', 'houseNumber' => '4', 'zipcode' => '1234AB', 'city' => 'Amsterdam', 'country' => 'NL'], $billing['address']);
    }

    public function testIn3ShippingIsNullWithoutAddress(): void
    {
        $checkout = $this->makeCheckout('in3');
        self::setProperty($checkout, 'shipping_address', null);

        self::assertNull(self::invoke($checkout, 'getShippingAddress'));
    }

    public function testIn3OldPayload(): void
    {
        $checkout = $this->makeCheckout('in3Old');
        $checkout->setCheckout();
        $vars = $this->customVars($checkout);

        self::assertSame('REF_5', $vars['description']);
        self::assertSame(date('d-m-Y'), $vars['invoiceDate']);
        self::assertSame('Debtor', $vars['customerType']);
        self::assertSame('jan@example.com', $vars['email']);
        self::assertSame(['mobile' => '06-12345678'], $vars['phone']);
        self::assertSame([
            'lastName' => 'Jansen',
            'culture' => 'nl-NL',
            'initials' => 'J.',
            'phone' => '06-12345678',
            'email' => 'jan@example.com',
            'birthDate' => '1985-04-23',
        ], $vars['customer']);
        self::assertSame([
            'street' => 'Hoofdstraat',
            'houseNumber' => '12',
            'zipcode' => '1234AB',
            'city' => 'Amsterdam',
            'country' => 'NL',
            'houseNumberAdditional' => 'A',
        ], $vars['address']);
    }

    public function testIn3OldAddressWithoutAdditionUsesAddress2(): void
    {
        $this->registerAddress(self::INVOICE_ADDRESS_ID, ['address1' => 'Hoofdstraat', 'address2' => '4']);
        $checkout = $this->makeCheckout('in3Old');
        $checkout->setCheckout();

        self::assertSame(
            ['street' => 'Hoofdstraat', 'houseNumber' => '4', 'zipcode' => '1234AB', 'city' => 'Amsterdam', 'country' => 'NL'],
            $this->customVars($checkout)['address']
        );
    }
}
