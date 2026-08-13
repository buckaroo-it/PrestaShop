<?php

declare(strict_types=1);

use Buckaroo\PrestaShop\Src\Config\Config;
use PHPUnit\Framework\TestCase;

/**
 * Basic tests to ensure configuration keys used by the module
 * match the expected names in the database / configuration table.
 *
 * This is similar in spirit to how the Mollie module guards its config keys:
 * https://github.com/mollie/PrestaShop/tree/master/tests
 */
class ConfigTest extends TestCase
{
    public function testConfigConstantsHaveExpectedValues(): void
    {
        $this->assertSame('BUCKAROO_TEST', Config::BUCKAROO_TEST);
        $this->assertSame('BUCKAROO_MERCHANT_KEY', Config::BUCKAROO_MERCHANT_KEY);
        $this->assertSame('BUCKAROO_SECRET_KEY', Config::BUCKAROO_SECRET_KEY);
        $this->assertSame('BUCKAROO_TRANSACTION_LABEL', Config::BUCKAROO_TRANSACTION_LABEL);
        $this->assertSame('BUCKAROO_TRANSACTION_FEE', Config::BUCKAROO_TRANSACTION_FEE);
        $this->assertSame('BUCKAROO_REFUND_CONF', Config::LABEL_REFUND_CONF);
        $this->assertSame('BUCKAROO_REFUND_RESTOCK', Config::LABEL_REFUND_RESTOCK);
        $this->assertSame('BUCKAROO_REFUND_CREDIT_SLIP', Config::LABEL_REFUND_CREDIT_SLIP);
        $this->assertSame('BUCKAROO_REFUND_VOUCHER', Config::LABEL_REFUND_VOUCHER);
        $this->assertSame(
            'BUCKAROO_REFUND_CREATE_NEGATIVE_PAYMENT',
            Config::LABEL_REFUND_CREATE_NEGATIVE_PAYMENT
        );
        $this->assertSame('PAYMENT_FEE_MODE', Config::PAYMENT_FEE_MODE);
        $this->assertSame('PAYMENT_FEE_FRONTEND_LABEL', Config::PAYMENT_FEE_FRONTEND_LABEL);
    }

    public function testPaymentFeeIsNotAllowedForPayByBank(): void
    {
        $this->assertFalse(Config::isPaymentFeeAllowed('paybybank'));
        $this->assertFalse(Config::isPaymentFeeAllowed('PayByBank'));
        $this->assertTrue(Config::isPaymentFeeAllowed('ideal'));
        $this->assertTrue(Config::isPaymentFeeAllowed('paypal'));
    }
}

