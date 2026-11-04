<?php

declare(strict_types=1);

use Buckaroo\PrestaShop\Src\ErrorReporting\ErrorReportingConsent;
use Buckaroo\PrestaShop\Src\Service\BuckarooSettingsService;
use PHPUnit\Framework\TestCase;

class ErrorReportingConsentTest extends TestCase
{
    protected function setUp(): void
    {
        \Configuration::$values = [];
    }

    protected function tearDown(): void
    {
        \Configuration::$values = [];
    }

    public function testDefaultIsNotAskedAndNotGranted(): void
    {
        $this->assertSame(ErrorReportingConsent::NOT_ASKED, ErrorReportingConsent::getState());
        $this->assertFalse(ErrorReportingConsent::isGranted());
        $this->assertTrue(ErrorReportingConsent::needsPrompt());
    }

    public function testAnsweringHidesThePrompt(): void
    {
        ErrorReportingConsent::setConsent(false);
        $this->assertSame('0', \Configuration::$values['BUCKAROO_ERROR_REPORTING']);
        $this->assertFalse(ErrorReportingConsent::isGranted());
        $this->assertFalse(ErrorReportingConsent::needsPrompt());

        ErrorReportingConsent::setConsent(true);
        $this->assertSame('1', \Configuration::$values['BUCKAROO_ERROR_REPORTING']);
        $this->assertTrue(ErrorReportingConsent::isGranted());
    }

    public function testResetKeepsExistingDecisionUnlessForced(): void
    {
        ErrorReportingConsent::setConsent(true);
        ErrorReportingConsent::resetToNotAsked();
        $this->assertTrue(ErrorReportingConsent::isGranted());

        ErrorReportingConsent::resetToNotAsked(true);
        $this->assertTrue(ErrorReportingConsent::needsPrompt());
    }

    public function testSettingsServiceExposesAndUpdatesConsent(): void
    {
        $service = new BuckarooSettingsService();
        $settings = $service->getSettings();
        $this->assertFalse($settings['error_reporting']);
        $this->assertFalse($settings['error_reporting_decided']);

        $data = $this->settingsPayload();

        // Older admin builds (or an untouched toggle) do not send the key: stays "not asked".
        $service->updateSettings($data);
        $this->assertTrue(ErrorReportingConsent::needsPrompt());

        $service->updateSettings($data + ['error_reporting' => true]);
        $this->assertTrue(ErrorReportingConsent::isGranted());

        $service->updateSettings($data + ['error_reporting' => false]);
        $this->assertSame(ErrorReportingConsent::NO, ErrorReportingConsent::getState());
        $this->assertTrue($service->getSettings()['error_reporting_decided']);
    }

    private function settingsPayload(): array
    {
        return [
            'website_key' => 'ABC', 'secret_key' => 'DEF', 'transaction_description' => '',
            'refundconf' => true, 'restock' => false, 'creditSlip' => true, 'voucher' => false,
            'negativePayment' => false, 'payment_fee_mode' => 'subtotal', 'payment_fee_frontend_label' => 'Fee',
        ];
    }
}
