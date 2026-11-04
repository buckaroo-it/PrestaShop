<?php

namespace BuckarooTest\Unit\Checkout;

use BuckarooTest\Stubs;

require_once __DIR__ . '/CheckoutTestCase.php';

class BillinkCheckoutTest extends CheckoutTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->request(['method' => 'billink']);
    }

    private function checkout(string $customerType, array $invoice = [], array $delivery = []): \BillinkCheckout
    {
        $this->configValues['billink']['customer_type'] = $customerType;
        if ($invoice) {
            $this->registerAddress(self::INVOICE_ADDRESS_ID, $invoice);
        }
        if ($delivery) {
            $this->registerAddress(self::DELIVERY_ADDRESS_ID, array_merge([
                'firstname' => 'Piet',
                'lastname' => 'Pietersen',
                'address1' => 'Kerkstraat 22 b',
                'postcode' => '5678CD',
                'city' => 'Utrecht',
            ], $delivery));
        }
        $checkout = $this->makeCheckout('billink', $this->makeCart([\Cart::BOTH => 50.0], [
            ['id_product' => 2, 'quantity' => 1, 'price_wt' => 50, 'rate' => 9, 'name' => 'Book'],
        ]));
        $checkout->setCheckout();

        return $checkout;
    }

    public function testB2cPayload(): void
    {
        $checkout = $this->checkout(\BillinkCheckout::CUSTOMER_TYPE_B2C, ['company' => 'ACME BV'], ['id_country' => self::COUNTRY_DE]);
        $vars = $this->customVars($checkout);

        self::assertSame('NL123', $vars['vATNumber']);
        self::assertSame([
            'recipient' => [
                'category' => 'B2C',
                'careOf' => 'Jan Jansen',
                'firstName' => 'Jan',
                'lastName' => 'Jansen',
                'initials' => 'J.J.',
            ],
            'address' => [
                'street' => 'Hoofdstraat',
                'houseNumber' => '12',
                'houseNumberAdditional' => 'A',
                'zipcode' => '1234AB',
                'city' => 'Amsterdam',
                'country' => 'NL',
            ],
            'phone' => ['mobile' => '06-12345678'],
            'email' => 'jan@example.com',
        ], $vars['billing']);
        self::assertSame([
            'recipient' => [
                'category' => 'B2C',
                'careOf' => 'Piet Pietersen',
                'firstName' => 'Piet',
                'lastName' => 'Pietersen',
                'initials' => 'P.P.',
            ],
            'address' => [
                'street' => 'Kerkstraat',
                'houseNumber' => '22',
                'houseNumberAdditional' => 'b',
                'zipcode' => '5678CD',
                'city' => 'Utrecht',
                'country' => 'DE',
            ],
        ], $vars['shipping']);
        self::assertSame('50.00', array_values($vars['articles'])[0]['price']);
    }

    public function testB2bPayloadUsesTheCompany(): void
    {
        $_GET['customerbillink-coc'] = '87654321';
        $checkout = $this->checkout(\BillinkCheckout::CUSTOMER_TYPE_B2B, ['company' => 'ACME BV'], ['company' => 'ACME Logistics']);
        $vars = $this->customVars($checkout);

        self::assertSame('B2B', $vars['billing']['recipient']['category']);
        self::assertSame('ACME BV', $vars['billing']['recipient']['careOf']);
        self::assertSame('87654321', $vars['billing']['recipient']['chamberOfCommerce']);
        self::assertSame('B2B', $vars['shipping']['recipient']['category']);
        self::assertSame('ACME Logistics', $vars['shipping']['recipient']['careOf']);
    }

    public function categories(): array
    {
        return [
            'B2C ignores company' => ['B2C', 'ACME', 'B2C'],
            'B2B without company' => ['B2B', '', 'B2B'],
            'both with company' => ['both', 'ACME', 'B2B'],
            'both without company' => ['both', '', 'B2C'],
        ];
    }

    /**
     * @dataProvider categories
     */
    public function testRecipientCategory(string $customerType, string $company, string $category): void
    {
        $checkout = $this->checkout($customerType, ['company' => $company]);

        self::assertSame($category, $checkout->getRecipientCategory());
        self::assertSame($category, $this->customVars($checkout)['billing']['recipient']['category']);
    }

    public function testB2bWithoutCompanyKeepsThePersonAsCareOf(): void
    {
        $checkout = $this->checkout(\BillinkCheckout::CUSTOMER_TYPE_B2B);
        $billing = $this->customVars($checkout)['billing'];

        self::assertSame('Jan Jansen', $billing['recipient']['careOf']);
        self::assertArrayNotHasKey('chamberOfCommerce', $billing['recipient']);
    }

    public function testPhoneFallsBackToTheShippingAddress(): void
    {
        $checkout = $this->checkout(
            \BillinkCheckout::CUSTOMER_TYPE_B2C,
            ['phone' => '', 'phone_mobile' => ''],
            ['phone' => '030-555', 'phone_mobile' => '']
        );

        self::assertSame('030-555', $this->customVars($checkout)['billing']['phone']['mobile']);
    }

    public function testHouseNumberFallsBackToTheOwnAddress2(): void
    {
        $checkout = $this->checkout(
            \BillinkCheckout::CUSTOMER_TYPE_B2C,
            ['address1' => 'Hoofdstraat', 'address2' => '8'],
            ['address1' => 'Kerkstraat', 'address2' => '99']
        );
        $vars = $this->customVars($checkout);

        self::assertSame('8', $vars['billing']['address']['houseNumber']);
        // Regression: the shipping fallback used to read the invoice address2 ('8').
        self::assertSame('99', $vars['shipping']['address']['houseNumber']);
        self::assertSame('Kerkstraat', $vars['shipping']['address']['street']);
    }

    public function sendCloudCountries(): array
    {
        $country = new \Country();
        $country->iso_code = 'be';

        return [
            'Country object' => [$country],
            // Regression: an ISO string used to fail on ->iso_code.
            'ISO code' => ['be'],
        ];
    }

    /**
     * @dataProvider sendCloudCountries
     */
    public function testSendCloudServicePointReplacesTheShippingAddress($country): void
    {
        $this->sendCloudServicePoint([
            'street' => 'Servicepunt',
            'house_number' => '3',
            'postal_code' => '1000',
            'city' => 'Brussel',
            'country' => $country,
        ]);
        $this->configValues['billink']['customer_type'] = \BillinkCheckout::CUSTOMER_TYPE_B2C;
        $cart = $this->makeCart([\Cart::BOTH => 50.0]);
        $cart->id_carrier = 9;
        $checkout = $this->makeCheckout('billink', $cart);
        $checkout->setCheckout();

        self::assertSame([
            'street' => 'Servicepunt',
            'houseNumber' => '3',
            'houseNumberAdditional' => '',
            'zipcode' => '1000',
            'city' => 'Brussel',
            'country' => 'BE',
        ], $this->customVars($checkout)['shipping']['address']);
        self::assertSame(1, Stubs::callCount('SendcloudServicePoint::getFromCart'));
    }

    public function testShippingAddressIsNullWithoutAddress(): void
    {
        $checkout = $this->checkout(\BillinkCheckout::CUSTOMER_TYPE_B2C);
        self::setProperty($checkout, 'shipping_address', null);

        self::assertNull($checkout->getShippingAddress());
    }

    public function testEmptyEmailIsSentAsEmptyString(): void
    {
        Stubs::object('Customer', self::CUSTOMER_ID, ['email' => '']);
        $checkout = $this->checkout(\BillinkCheckout::CUSTOMER_TYPE_B2C);

        self::assertSame('', $this->customVars($checkout)['billing']['email']);
    }
}
