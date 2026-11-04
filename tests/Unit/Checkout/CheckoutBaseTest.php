<?php

namespace BuckarooTest\Unit\Checkout;

use BuckarooTest\Stubs;

require_once __DIR__ . '/CheckoutTestCase.php';

/**
 * Behaviour of the abstract Checkout shared by every method: amount and
 * giftcard remainder, Buckaroo fee, article lines and the small helpers.
 * IDealCheckout is used as the concrete subclass where nothing method specific matters.
 */
class CheckoutBaseTest extends CheckoutTestCase
{
    private function products(): array
    {
        return [
            ['id_product' => 1, 'quantity' => 2, 'price_wt' => 12.999, 'rate' => 21, 'name' => 'Shirt', 'link_rewrite' => 'shirt'],
            ['id_product' => 2, 'quantity' => 1, 'price_wt' => 50, 'rate' => 9, 'name' => 'Book', 'link_rewrite' => 'book'],
            // Same product twice in the cart (e.g. customization): merged by identifier.
            ['id_product' => 1, 'quantity' => 1, 'price_wt' => 12.999, 'rate' => 21, 'name' => 'Shirt', 'link_rewrite' => 'shirt'],
        ];
    }

    // ------------------------------------------------------------------
    // Amount and giftcard remainder
    // ------------------------------------------------------------------

    public function giftcardRemainders(): array
    {
        return [
            'no giftcard paid' => [[], [], '100.00', null],
            'remainder with group transaction' => [
                [['amount' => 40, 'refunded_amount' => 0, 'group_transaction_id' => 'GTX1']],
                [],
                '60.00',
                'GTX1',
            ],
            'newest group transaction wins' => [
                [
                    ['amount' => 10, 'refunded_amount' => 0, 'group_transaction_id' => 'OLD'],
                    ['amount' => 15.5, 'refunded_amount' => 0, 'group_transaction_id' => 'NEW'],
                ],
                [],
                '74.50',
                'NEW',
            ],
            'group transaction from the cookie of this cart' => [
                [['amount' => 40, 'refunded_amount' => 10, 'group_transaction_id' => '']],
                ['buckaroo_giftcard_cart_id' => self::CART_ID, 'buckaroo_giftcard_group_tx' => 'COOKIE-TX'],
                '70.00',
                'COOKIE-TX',
            ],
            'cookie of another cart is ignored' => [
                [['amount' => 40, 'refunded_amount' => 0, 'group_transaction_id' => '']],
                ['buckaroo_giftcard_cart_id' => 6, 'buckaroo_giftcard_group_tx' => 'STALE', 'buckaroo_giftcard_remainder' => 5],
                '100.00',
                null,
            ],
            'remainder from the cookie when the giftcards cover the total' => [
                [['amount' => 100, 'refunded_amount' => 0, 'group_transaction_id' => 'GTX2']],
                ['buckaroo_giftcard_cart_id' => self::CART_ID, 'buckaroo_giftcard_remainder' => '12.5'],
                '12.50',
                'GTX2',
            ],
            'fully paid without cookie charges the full amount' => [
                [['amount' => 100, 'refunded_amount' => 0, 'group_transaction_id' => 'GTX3']],
                [],
                '100.00',
                null,
            ],
        ];
    }

    /**
     * @dataProvider giftcardRemainders
     */
    public function testAmountDebitChargesTheGiftcardRemainder(array $rows, array $cookie, string $amount, ?string $originalKey): void
    {
        Stubs::db('/bk_group_transaction/', $rows);
        foreach ($cookie as $key => $value) {
            $this->context()->cookie->{$key} = $value;
        }
        $checkout = $this->makeCheckout('ideal');

        $checkout->setCheckout();

        $request = $this->paymentRequest($checkout);
        self::assertSame($amount, $request->amountDebit);
        self::assertSame($originalKey, $request->OriginalTransactionKey);
    }

    public function testGiftcardCheckoutAlwaysChargesTheFullAmount(): void
    {
        Stubs::db('/bk_group_transaction/', [['amount' => 40, 'refunded_amount' => 0, 'group_transaction_id' => 'GTX1']]);
        $checkout = $this->makeCheckout('giftcard');

        $checkout->setCheckout();

        self::assertSame('100.00', $this->paymentRequest($checkout)->amountDebit);
        self::assertNull($this->paymentRequest($checkout)->OriginalTransactionKey);
    }

    public function testGroupTransactionFailureFallsBackToTheFullAmount(): void
    {
        Stubs::db('/bk_group_transaction/', function () {
            throw new \RuntimeException('table missing');
        });
        $this->context()->cookie->buckaroo_giftcard_cart_id = self::CART_ID;
        $this->context()->cookie->buckaroo_giftcard_remainder = 10;
        $checkout = $this->makeCheckout('ideal');

        $checkout->setCheckout();

        self::assertSame('100.00', $this->paymentRequest($checkout)->amountDebit);
        self::assertNull($this->paymentRequest($checkout)->OriginalTransactionKey);
    }

    public function roundedAmounts(): array
    {
        return [
            'rounds up' => [19.999, '20.00'],
            'half rounds up' => [19.995, '20.00'],
            'below half rounds down' => [19.994, '19.99'],
            'already rounded' => [19.99, '19.99'],
        ];
    }

    /**
     * Regression: roundBuckarooPrice() used DecimalNumber::toPrecision(2), which
     * truncates by default (19.999 became 19.99). It now rounds half up.
     *
     * @dataProvider roundedAmounts
     */
    public function testAmountIsRoundedHalfUpToTwoDecimals(float $total, string $expected): void
    {
        $checkout = $this->makeCheckout('ideal', $this->makeCart([\Cart::BOTH => $total]));

        $checkout->setCheckout();

        self::assertSame($expected, $this->paymentRequest($checkout)->amountDebit);
    }

    public function testDescriptionUsesTheConfiguredTransactionLabel(): void
    {
        \Configuration::updateValue('BUCKAROO_TRANSACTION_LABEL', 'Order {order_number} at {shop_name}');
        Stubs::returns('Order::getByCartId', (new \Order())->hydrate(['reference' => 'XKBKNABJK']));
        $checkout = $this->makeCheckout('ideal');

        $checkout->setCheckout();

        self::assertSame('Order XKBKNABJK at Test shop', $this->paymentRequest($checkout)->description);
    }

    // ------------------------------------------------------------------
    // Buckaroo fee
    // ------------------------------------------------------------------

    public function testBuckarooFeeIsAddedToTheAmountAndStored(): void
    {
        $this->request(['method' => 'ideal']);
        $fees = [];
        $this->module->stub('getBuckarooFee', function ($method, $base) use (&$fees) {
            $fees[] = [$method, $base];

            return ['buckaroo_fee_tax_excl' => 2.0, 'buckaroo_fee_tax_incl' => 2.42];
        });
        Stubs::returns('Order::getIdByCartId', 77);
        Stubs::object('Order', 77, ['id_cart' => self::CART_ID]);
        $checkout = $this->makeCheckout('ideal', $this->makeCart([\Cart::BOTH => 100.0, '3_excl' => 82.64]));

        $checkout->setCheckout();

        self::assertSame([['ideal', 100.0]], $fees);
        self::assertSame('102.42', $this->paymentRequest($checkout)->amountDebit);
        self::assertSame('buckarooFeeTaxExcl: 2, buckarooFeeTaxIncl: 2.42', Stubs::callsTo('PrestaShopLogger::addLog')[0][0]);

        $inserts = array_values(array_filter(Stubs::$queries, function ($query) {
            return $query['method'] === 'insert';
        }));
        self::assertCount(1, $inserts);
        self::assertSame('bk_buckaroo_fee', $inserts[0]['args'][0]);
        $row = $inserts[0]['args'][1];
        self::assertSame('REF', $row['reference']);
        self::assertSame(self::CART_ID, $row['id_cart']);
        self::assertSame(77, $row['id_order']);
        self::assertSame(2.0, $row['buckaroo_fee_tax_excl']);
        self::assertSame(2.42, $row['buckaroo_fee_tax_incl']);
        self::assertSame('EUR', $row['currency']);

        $orders = Stubs::persisted('Order', 'update');
        self::assertCount(1, $orders);
        self::assertSame(77, $orders[0]->id);
        self::assertSame('84.64', $orders[0]->total_paid_tax_excl);
        self::assertSame('102.42', $orders[0]->total_paid_tax_incl);
        self::assertSame('102.42', $orders[0]->total_paid);
        self::assertSame('102.42', $orders[0]->total_paid_real);
    }

    public function testOrderTotalsAreRoundedHalfUp(): void
    {
        Stubs::object('Order', 77, ['id_cart' => self::CART_ID]);
        $checkout = $this->makeCheckout('ideal', $this->makeCart([\Cart::BOTH => 100.0, '3_excl' => 82.64]));

        self::invoke($checkout, 'updateOrderTotals', [77, 2.005, 2.425]);

        $orders = Stubs::persisted('Order', 'update');
        self::assertCount(1, $orders);
        // 84.645 and 102.425 used to be truncated to 84.64 and 102.42.
        self::assertSame('84.65', $orders[0]->total_paid_tax_excl);
        self::assertSame('102.43', $orders[0]->total_paid_tax_incl);
        self::assertSame('102.43', $orders[0]->total_paid);
        self::assertSame('102.43', $orders[0]->total_paid_real);
    }

    public function noFees(): array
    {
        return [
            'no fee configured' => [null],
            'zero fee' => [['buckaroo_fee_tax_excl' => 0, 'buckaroo_fee_tax_incl' => 0]],
        ];
    }

    /**
     * @dataProvider noFees
     */
    public function testNoFeeLeavesTheAmountAndOrderAlone($fee): void
    {
        $this->module->stub('getBuckarooFee', $fee);
        $checkout = $this->makeCheckout('ideal');

        $checkout->setCheckout();

        self::assertSame('100.00', $this->paymentRequest($checkout)->amountDebit);
        self::assertSame([], Stubs::persisted('Order'));
        self::assertSame([], Stubs::sql('insert'));
    }

    public function testUpdateOrderFeeRejectsANonArrayFee(): void
    {
        $checkout = $this->makeCheckout('ideal');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('buckarooFee should be an array');

        $checkout->updateOrderFee('2.42');
    }

    // ------------------------------------------------------------------
    // Articles
    // ------------------------------------------------------------------

    public function testArticlesListProductsWrappingAndShipping(): void
    {
        $this->taxRate(21.0);
        $cart = $this->makeCart([
            \Cart::BOTH => 98.45,
            \Cart::ONLY_WRAPPING => 2.5,
            \Cart::ONLY_SHIPPING => 6.95,
        ], $this->products());
        $checkout = $this->makeCheckout('ideal', $cart);
        $checkout->setCheckout();

        $articles = array_values($checkout->getArticles());

        // 12.999 rounds (half up) to 13.00; it used to be truncated to 12.99.
        self::assertSame([
            ['identifier' => 1, 'quantity' => 3, 'price' => '13.00', 'vatPercentage' => 21, 'description' => 'Shirt'],
            ['identifier' => 2, 'quantity' => 1, 'price' => '50.00', 'vatPercentage' => 9, 'description' => 'Book'],
            ['identifier' => '0', 'quantity' => '1', 'price' => '2.50', 'vatPercentage' => 21.0, 'description' => 'Wrapping'],
            ['identifier' => 'shipping', 'description' => 'Shipping Costs', 'vatPercentage' => 21.0, 'quantity' => 1, 'price' => '6.95'],
        ], $articles);
        // Both tax lookups use the delivery address.
        self::assertSame(self::DELIVERY_ADDRESS_ID, Stubs::callsTo('TaxManagerFactory::getManager')[0][0]->id);
    }

    public function adjustments(): array
    {
        return [
            // Lines: 3 x 13.00 + 50.00 + 2.50 + 6.95 = 98.45.
            'total above the lines' => [100.0, '1.55'],
            'total below the lines' => [98.40, '-0.05'],
            'one cent below after rounding the total' => [98.444, '-0.01'],
            'total rounds onto the lines' => [98.445, null],
            'lines match the total' => [98.45, null],
        ];
    }

    /**
     * @dataProvider adjustments
     */
    public function testRoundingDifferenceBecomesAnAdjustmentLine(float $total, ?string $adjustment): void
    {
        $this->taxRate(21.0);
        $cart = $this->makeCart([
            \Cart::BOTH => $total,
            \Cart::ONLY_WRAPPING => 2.5,
            \Cart::ONLY_SHIPPING => 6.95,
        ], $this->products());
        $checkout = $this->makeCheckout('ideal', $cart);
        $checkout->setCheckout();

        $articles = array_values($checkout->getArticles());
        $last = end($articles);

        if ($adjustment === null) {
            self::assertCount(4, $articles);
            self::assertNotSame('adjustment', $last['identifier']);

            return;
        }
        self::assertCount(5, $articles);
        self::assertSame([
            'identifier' => 'adjustment',
            'quantity' => 1,
            'price' => $adjustment,
            'vatPercentage' => '0',
            'description' => 'Adjustment',
        ], $last);
        // The adjustment line makes the articles add up to the amount.
        $sum = 0.0;
        foreach ($articles as $article) {
            $sum += (float) $article['price'] * (float) $article['quantity'];
        }
        self::assertEqualsWithDelta((float) $this->paymentRequest($checkout)->amountDebit, $sum, 0.0001);
    }

    public function testBuckarooFeeBecomesAnArticle(): void
    {
        $this->module->stub('getBuckarooFee', ['buckaroo_fee_tax_excl' => 2.0, 'buckaroo_fee_tax_incl' => 2.425]);
        Stubs::returns('Order::getIdByCartId', 0);
        $cart = $this->makeCart([\Cart::BOTH => 50.0], [
            ['id_product' => 2, 'quantity' => 1, 'price_wt' => 50, 'rate' => 9, 'name' => 'Book'],
        ]);
        $checkout = $this->makeCheckout('ideal', $cart);
        $checkout->setCheckout();

        $articles = array_values($checkout->getArticles());

        // The 2.425 fee rounds half up to 2.43 (it used to be truncated to 2.42).
        self::assertSame('52.43', $this->paymentRequest($checkout)->amountDebit);
        self::assertSame(
            ['identifier' => '0', 'quantity' => '1', 'price' => '2.43', 'vatPercentage' => '0', 'description' => 'buckaroo_fee'],
            $articles[1]
        );
        self::assertCount(2, $articles, 'fee line plus product add up to the amount');
    }

    public function testWrappingAndFeeShareIdentifierZeroAndAreMerged(): void
    {
        // Documents current behaviour: both lines use identifier '0', so
        // mergeProductsBySKU() folds the fee into the wrapping line (qty 2 x wrapping price).
        $this->taxRate(21.0);
        $this->module->stub('getBuckarooFee', ['buckaroo_fee_tax_excl' => 2.0, 'buckaroo_fee_tax_incl' => 2.42]);
        Stubs::returns('Order::getIdByCartId', 0);
        $cart = $this->makeCart([\Cart::BOTH => 52.5, \Cart::ONLY_WRAPPING => 2.5], [
            ['id_product' => 2, 'quantity' => 1, 'price_wt' => 50, 'rate' => 9, 'name' => 'Book'],
        ]);
        $checkout = $this->makeCheckout('ideal', $cart);
        $checkout->setCheckout();

        $articles = array_values($checkout->getArticles());

        self::assertSame('54.92', $this->paymentRequest($checkout)->amountDebit);
        self::assertSame('Wrapping', $articles[1]['description']);
        self::assertSame(2, $articles[1]['quantity']);
        self::assertSame('2.50', $articles[1]['price']);
        self::assertSame(['identifier' => 'adjustment', 'quantity' => 1, 'price' => '-0.08', 'vatPercentage' => '0', 'description' => 'Adjustment'], $articles[2]);
    }

    public function imageMethods(): array
    {
        return [
            'afterpay' => ['afterpay', 'https://shop.test/img/55-large_default/shirt.jpg'],
            'klarna' => ['klarna', 'https://shop.test/img/55-large_default/shirt.jpg'],
            'ideal sends no image' => ['ideal', null],
        ];
    }

    /**
     * @dataProvider imageMethods
     */
    public function testProductImageIsSentForAfterpayAndKlarna(string $method, ?string $image): void
    {
        $this->request(['method' => $method]);
        Stubs::returns('Product::getCover', ['id_image' => 55]);
        Stubs::returns('ImageType::getImagesTypes', [
            ['name' => 'small_default', 'height' => 98, 'width' => 98],
            ['name' => 'huge', 'height' => 2000, 'width' => 2000],
            ['name' => 'large_default', 'height' => 800, 'width' => 800],
        ]);
        $checkout = $this->makeCheckout('ideal', $this->makeCart([\Cart::BOTH => 12.99], [
            ['id_product' => 1, 'quantity' => 1, 'price_wt' => 12.99, 'rate' => 21, 'name' => 'Shirt', 'link_rewrite' => 'shirt'],
        ]));
        $checkout->setCheckout();

        $articles = array_values($checkout->getArticles());

        if ($image === null) {
            self::assertArrayNotHasKey('imageUrl', $articles[0]);
            self::assertSame(0, Stubs::callCount('Product::getCover'));
        } else {
            self::assertSame($image, $articles[0]['imageUrl']);
            self::assertSame([1], Stubs::callsTo('Product::getCover')[0]);
        }
    }

    public function testNoImageWhenNoImageTypeFits(): void
    {
        $this->request(['method' => 'afterpay']);
        Stubs::returns('Product::getCover', ['id_image' => 55]);
        Stubs::returns('ImageType::getImagesTypes', [
            ['name' => 'small_default', 'height' => 98, 'width' => 98],
            ['name' => 'no_size'],
        ]);
        $checkout = $this->makeCheckout('ideal', $this->makeCart([\Cart::BOTH => 12.99], [
            ['id_product' => 1, 'quantity' => 1, 'price_wt' => 12.99, 'rate' => 21, 'name' => 'Shirt', 'link_rewrite' => 'shirt'],
        ]));
        $checkout->setCheckout();

        $articles = array_values($checkout->getArticles());

        self::assertArrayNotHasKey('imageUrl', $articles[0]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    public function phones(): array
    {
        return [
            'mobile first' => ['020-1', '06-1', '06-1'],
            'landline when no mobile' => ['020-1', '', '020-1'],
            'nothing' => ['', '', ''],
        ];
    }

    /**
     * @dataProvider phones
     */
    public function testGetPhonePrefersTheMobileNumber(string $phone, string $mobile, string $expected): void
    {
        $checkout = $this->makeCheckout('ideal');
        $address = new \Address();
        $address->phone = $phone;
        $address->phone_mobile = $mobile;

        self::assertSame($expected, $checkout->getPhone($address));
    }

    public function testInitials(): void
    {
        $checkout = $this->makeCheckout('ideal');

        self::assertSame('J.P.', $checkout->initials('jan piet'));
        self::assertSame('A.', $checkout->initials('Anna'));
    }

    public function companies(): array
    {
        return [
            'name' => ['ACME BV', true],
            'blank' => ['   ', false],
            'empty' => ['', false],
            'null' => [null, false],
        ];
    }

    /**
     * @dataProvider companies
     */
    public function testCompanyExists($company, bool $exists): void
    {
        $checkout = $this->makeCheckout('ideal');

        self::assertSame($exists, self::invoke($checkout, 'companyExists', [$company]));
    }

    public function testAddressComponentsSplitStreetNumberAndAddition(): void
    {
        $checkout = $this->makeCheckout('ideal');

        self::assertSame(
            ['house_number' => '12', 'number_addition' => 'A', 'street' => 'Hoofdstraat'],
            self::invoke($checkout, 'getAddressComponents', ['Hoofdstraat 12 A'])
        );
        self::assertSame(2, self::invoke($checkout, 'getPricePrecision'));
    }

    public function testSameInvoiceAndDeliveryAddressIsSharedObject(): void
    {
        $checkout = $this->makeCheckout('ideal', $this->makeCart([], [], false));

        self::assertSame(self::getProperty($checkout, 'invoice_address'), self::getProperty($checkout, 'shipping_address'));
        self::assertSame('jan@example.com', self::getProperty($checkout, 'customer')->email);
    }

    public function testSeparateDeliveryAddressIsLoaded(): void
    {
        $checkout = $this->makeCheckout('ideal');

        self::assertSame('Utrecht', self::getProperty($checkout, 'shipping_address')->city);
        self::assertSame('Amsterdam', self::getProperty($checkout, 'invoice_address')->city);
    }
}
