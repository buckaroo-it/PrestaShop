<?php

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Tests\Unit\Refund;

use Buckaroo\PrestaShop\Src\Refund\Request\PaymentMethodHelper;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/RefundTestSupport.php';

/**
 * OrderPayment stores the checkout label ("Click to Pay", "SEPA Direct Debit"),
 * while the config lookup and the SDK factory need the method code
 * ("clicktopay", "sepadirectdebit"). A refund on an unresolved code silently
 * falls back to the test environment.
 */
class PaymentMethodHelperTest extends TestCase
{
    /**
     * @dataProvider labelProvider
     */
    public function testResolveMethodCodeMapsCheckoutLabels(string $label, string $expected): void
    {
        $this->assertSame($expected, PaymentMethodHelper::resolveMethodCode($label));
    }

    public static function labelProvider(): array
    {
        return [
            'Click to Pay label' => ['Click to Pay', 'clicktopay'],
            'Click to Pay code' => ['clicktopay', 'clicktopay'],
            'SEPA label' => ['SEPA Direct Debit', 'sepadirectdebit'],
            'SEPA SDK name' => ['SepaDirectDebit', 'sepadirectdebit'],
            'card brand' => ['visa', 'visa'],
        ];
    }
}
