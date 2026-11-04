<?php

namespace BuckarooTest\Unit\RefundAdmin;

use Buckaroo\PrestaShop\Src\Refund\Request\PaymentMethodHelper;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;

require_once __DIR__ . '/StubsSupport.php';

class RefundMethodCodeTest extends TestCase
{
    public function methodProvider(): array
    {
        return [
            'blank is returned untouched' => ['  ', '  '],
            'card brand is lower-cased' => [' VISA ', 'visa'],
            'gift card service code' => ['FashionCheque', 'fashioncheque'],
            'sepa alias' => ['SEPA', 'sepadirectdebit'],
            'label with spaces' => ['Click to Pay', 'clicktopay'],
            'push casing' => ['SepaDirectDebit', 'sepadirectdebit'],
        ];
    }

    /**
     * @dataProvider methodProvider
     */
    public function testResolveMethodCode(string $method, string $expected): void
    {
        Stubs::db('/bk_giftcards/', [['code' => 'fashioncheque']]);

        self::assertSame($expected, PaymentMethodHelper::resolveMethodCode($method));
    }
}
