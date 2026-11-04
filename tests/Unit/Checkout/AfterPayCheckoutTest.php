<?php

namespace BuckarooTest\Unit\Checkout;

require_once __DIR__ . '/CheckoutTestCase.php';

class AfterPayCheckoutTest extends CheckoutTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->request([
            'method' => 'afterpay',
            'customerbirthdate_y_billing' => '1985',
            'customerbirthdate_m_billing' => '04',
            'customerbirthdate_d_billing' => '23',
        ]);
    }

    private function checkout(string $customerType, array $invoice = [], array $delivery = []): \AfterPayCheckout
    {
        $this->configValues['afterpay']['customer_type'] = $customerType;
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
        $checkout = $this->makeCheckout('afterpay', $this->makeCart([\Cart::BOTH => 50.0], [
            ['id_product' => 2, 'quantity' => 1, 'price_wt' => 50, 'rate' => 9, 'name' => 'Book', 'link_rewrite' => 'book'],
        ]));
        $checkout->setCheckout();

        return $checkout;
    }

    public function testB2cPayload(): void
    {
        $checkout = $this->checkout(\AfterPayCheckout::CUSTOMER_TYPE_B2C, ['company' => 'ACME BV']);
        $vars = $this->customVars($checkout);

        self::assertSame(['address' => '127.0.0.1', 'type' => 0], $vars['clientIP']);
        self::assertSame([
            'recipient' => [
                'category' => 'Person',
                'conversationLanguage' => 'NL',
                'careOf' => 'Jan Jansen',
                'firstName' => 'Jan',
                'lastName' => 'Jansen',
                'birthDate' => '23-04-1985',
            ],
            'phone' => ['mobile' => '06-12345678'],
            'address' => [
                'street' => 'Hoofdstraat',
                'houseNumber' => '12',
                'houseNumberAdditional' => 'A',
                'zipcode' => '1234AB',
                'city' => 'Amsterdam',
                'country' => 'NL',
            ],
            'email' => 'jan@example.com',
        ], $vars['billing']);
        self::assertSame([
            'recipient' => [
                'category' => 'Person',
                'conversationLanguage' => 'NL',
                'careOf' => 'Piet Pietersen',
                'firstName' => 'Piet',
                'lastName' => 'Pietersen',
                'birthDate' => '23-04-1985',
            ],
            'address' => [
                'street' => 'Kerkstraat',
                'houseNumber' => '22',
                'houseNumberAdditional' => 'b',
                'zipcode' => '5678CD',
                'city' => 'Utrecht',
                'country' => 'NL',
            ],
            'phone' => ['mobile' => '06-12345678'],
            'email' => 'jan@example.com',
        ], $vars['shipping']);
        self::assertSame([
            ['identifier' => 2, 'quantity' => 1, 'price' => '50.00', 'vatPercentage' => 9, 'description' => 'Book'],
        ], array_values($vars['articles']));
    }

    public function testB2bPayloadAddsCompanyAndChamberOfCommerce(): void
    {
        $_GET['customerafterpaynew-coc'] = '12345678';
        $checkout = $this->checkout(\AfterPayCheckout::CUSTOMER_TYPE_B2B, ['company' => 'ACME BV']);
        $vars = $this->customVars($checkout);

        self::assertSame('Company', $vars['billing']['recipient']['category']);
        self::assertSame('ACME BV', $vars['billing']['recipient']['companyName']);
        self::assertSame('12345678', $vars['billing']['recipient']['chamberOfCommerce']);
        self::assertSame('Company', $vars['shipping']['recipient']['category']);
        self::assertSame('ACME BV', $vars['shipping']['recipient']['companyName']);
    }

    public function testB2bWithoutCompanyHasNoCompanyFields(): void
    {
        $checkout = $this->checkout(\AfterPayCheckout::CUSTOMER_TYPE_B2B);
        $vars = $this->customVars($checkout);

        self::assertSame('Company', $vars['billing']['recipient']['category']);
        self::assertArrayNotHasKey('companyName', $vars['billing']['recipient']);
        self::assertSame('Company', $vars['shipping']['recipient']['category']);
        self::assertArrayNotHasKey('companyName', $vars['shipping']['recipient']);
    }

    public function bothCategories(): array
    {
        // Regression: 'both' with a company used to yield the literal 'B2B' instead of RecipientCategory::COMPANY.
        return [
            'company' => ['ACME BV', 'Company'],
            'no company' => ['', 'Person'],
        ];
    }

    /**
     * @dataProvider bothCategories
     */
    public function testBothCustomerTypeDependsOnTheCompany(string $company, string $category): void
    {
        $checkout = $this->checkout(\AfterPayCheckout::CUSTOMER_TYPE_BOTH, ['company' => $company]);

        self::assertSame($category, $this->customVars($checkout)['billing']['recipient']['category']);
    }

    public function cocNumbers(): array
    {
        return [
            'identification number wins' => [['customerIdentificationNumber' => 'ID-1', 'customerafterpaynew-coc' => 'COC-1'], 'ID-1'],
            'coc field' => [['customerafterpaynew-coc' => 'COC-1'], 'COC-1'],
            'blank coc' => [['customerafterpaynew-coc' => '   '], ''],
            'nothing' => [[], ''],
        ];
    }

    /**
     * @dataProvider cocNumbers
     */
    public function testCocNumber(array $post, string $expected): void
    {
        $checkout = $this->checkout(\AfterPayCheckout::CUSTOMER_TYPE_B2C);
        $_POST = $post;

        self::assertSame($expected, $checkout->getCocNumber());
    }

    public function phones(): array
    {
        return [
            'posted phone' => [['phone_afterpay_billing' => '0699'], '06-1', '020-1', '0699'],
            'mobile' => [[], '06-1', '020-1', '06-1'],
            'landline' => [[], '', '020-1', '020-1'],
            // Regression: Tools::getValue()'s default (false) used to leak through.
            'none' => [[], '', '', ''],
        ];
    }

    /**
     * @dataProvider phones
     */
    public function testPhone(array $post, string $mobile, string $phone, $expected): void
    {
        $checkout = $this->checkout(\AfterPayCheckout::CUSTOMER_TYPE_B2C);
        $_POST = $post;
        $address = new \Address();
        $address->phone_mobile = $mobile;
        $address->phone = $phone;

        self::assertSame($expected, $checkout->getPhone($address));
    }

    public function testHouseNumberFallsBackToAddress2(): void
    {
        $checkout = $this->checkout(
            \AfterPayCheckout::CUSTOMER_TYPE_B2C,
            ['address1' => 'Hauptstrasse', 'address2' => '7', 'id_country' => self::COUNTRY_DE],
            ['address1' => 'Bahnhofstrasse', 'address2' => '99']
        );
        $vars = $this->customVars($checkout);

        self::assertSame('Hauptstrasse', $vars['billing']['address']['street']);
        self::assertSame('7', $vars['billing']['address']['houseNumber']);
        self::assertSame('DE', $vars['billing']['address']['country']);
        self::assertSame('DE', $vars['billing']['recipient']['conversationLanguage']);
        // Regression: the shipping fallback used to read the invoice address2 ('7') and the
        // shipping country was the invoice country ('DE'); both now come from the delivery address.
        self::assertSame('Bahnhofstrasse', $vars['shipping']['address']['street']);
        self::assertSame('99', $vars['shipping']['address']['houseNumber']);
        self::assertSame('NL', $vars['shipping']['address']['country']);
        self::assertSame('NL', $vars['shipping']['recipient']['conversationLanguage']);
    }

    public function testShippingCountryComesFromTheDeliveryAddress(): void
    {
        $checkout = $this->checkout(
            \AfterPayCheckout::CUSTOMER_TYPE_B2C,
            [],
            ['address1' => 'Bahnhofstrasse 5', 'id_country' => self::COUNTRY_DE]
        );
        $vars = $this->customVars($checkout);

        self::assertSame('NL', $vars['billing']['address']['country']);
        self::assertSame('DE', $vars['shipping']['address']['country']);
        self::assertSame('DE', $vars['shipping']['recipient']['conversationLanguage']);
    }

    public function testGermanBillingAddressNeedsAHouseNumber(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid billing address, cannot find house number');

        $this->checkout(\AfterPayCheckout::CUSTOMER_TYPE_B2C, ['address1' => 'Hauptstrasse', 'address2' => '', 'id_country' => self::COUNTRY_DE]);
    }

    public function testGermanShippingAddressNeedsAHouseNumber(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid shipping address, cannot find house number');

        // German delivery address, Dutch invoice address: the delivery country decides.
        $this->checkout(
            \AfterPayCheckout::CUSTOMER_TYPE_B2C,
            [],
            ['address1' => 'Bahnhofstrasse', 'address2' => '', 'id_country' => self::COUNTRY_DE]
        );
    }

    public function testDutchShippingAddressWithoutHouseNumberIsAccepted(): void
    {
        // A German invoice address no longer makes the (Dutch) shipping address require a house number.
        $checkout = $this->checkout(
            \AfterPayCheckout::CUSTOMER_TYPE_B2C,
            ['address1' => 'Hauptstrasse 5', 'address2' => '', 'id_country' => self::COUNTRY_DE],
            ['address1' => 'Bahnhofstrasse', 'address2' => '']
        );

        self::assertSame('NL', $this->customVars($checkout)['shipping']['address']['country']);
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
        $cart = $this->makeCart([\Cart::BOTH => 50.0]);
        $cart->id_carrier = 9;
        $this->configValues['afterpay']['customer_type'] = \AfterPayCheckout::CUSTOMER_TYPE_B2C;
        $checkout = $this->makeCheckout('afterpay', $cart);
        $checkout->setCheckout();

        self::assertSame([
            'street' => 'Servicepunt',
            'houseNumber' => '3',
            'houseNumberAdditional' => '',
            'zipcode' => '1000',
            'city' => 'Brussel',
            'country' => 'BE',
        ], $this->customVars($checkout)['shipping']['address']);
        self::assertSame([self::CART_ID], \BuckarooTest\Stubs::callsTo('SendcloudServicePoint::getFromCart')[0]);
    }

    public function testShippingAddressIsNullWithoutAddress(): void
    {
        $checkout = $this->checkout(\AfterPayCheckout::CUSTOMER_TYPE_B2C);
        self::setProperty($checkout, 'shipping_address', null);

        self::assertNull($checkout->getShippingAddress());
    }

    public function testEmptyEmailIsSentAsEmptyString(): void
    {
        \BuckarooTest\Stubs::object('Customer', self::CUSTOMER_ID, ['email' => '']);
        $checkout = $this->checkout(\AfterPayCheckout::CUSTOMER_TYPE_B2C);
        $vars = $this->customVars($checkout);

        self::assertSame('', $vars['billing']['email']);
        self::assertSame('', $vars['shipping']['email']);
    }
}
