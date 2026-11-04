<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * It is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this file
 *
 *  @author    Buckaroo.nl <plugins@buckaroo.nl>
 *  @copyright Copyright (c) Buckaroo B.V.
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace BuckarooTest\Unit\Data;

use Buckaroo\PrestaShop\Src\Entity\BkConfiguration;
use Buckaroo\PrestaShop\Src\Entity\BkCreditcards;
use Buckaroo\PrestaShop\Src\Entity\BkGiftcards;
use Buckaroo\PrestaShop\Src\Entity\BkOrdering;
use Buckaroo\PrestaShop\Src\Entity\BkPaymentMethods;
use Buckaroo\PrestaShop\Src\Entity\BkRefundRequest;
use BuckarooTest\TestCase;

/**
 * Doctrine entities: every setter is read back by its getter.
 */
class EntitiesTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: mixed}>
     */
    public function accessorProvider(): array
    {
        $date = new \DateTime('2026-03-04 05:06:07');

        return [
            'configuration id' => [BkConfiguration::class, 'Id', 3],
            'configuration configurable id' => [BkConfiguration::class, 'ConfigurableId', 12],
            'configuration value' => [BkConfiguration::class, 'Value', '{"mode":"live"}'],
            'configuration created at' => [BkConfiguration::class, 'CreatedAt', $date],

            'credit card icon' => [BkCreditcards::class, 'Icon', 'Visa.svg'],
            'credit card name' => [BkCreditcards::class, 'Name', 'VISA'],
            'credit card service code' => [BkCreditcards::class, 'ServiceCode', 'visa'],
            'credit card created at' => [BkCreditcards::class, 'CreatedAt', $date],

            'gift card code' => [BkGiftcards::class, 'Code', 'boekenbon'],
            'gift card name' => [BkGiftcards::class, 'Name', 'Boekenbon'],
            'gift card logo' => [BkGiftcards::class, 'Logo', 'Boekenbon.svg'],
            'gift card is custom' => [BkGiftcards::class, 'IsCustom', 1],
            'gift card created at' => [BkGiftcards::class, 'CreatedAt', $date],

            'ordering id' => [BkOrdering::class, 'Id', 9],
            'ordering country' => [BkOrdering::class, 'CountryId', 150],
            'ordering without country' => [BkOrdering::class, 'CountryId', null],
            'ordering value' => [BkOrdering::class, 'Value', '[1,2,3]'],
            'ordering created at' => [BkOrdering::class, 'CreatedAt', $date],

            'payment method id' => [BkPaymentMethods::class, 'Id', 4],
            'payment method name' => [BkPaymentMethods::class, 'Name', 'ideal'],
            'payment method label' => [BkPaymentMethods::class, 'Label', 'iDEAL'],
            'payment method template' => [BkPaymentMethods::class, 'Template', 'payment_ideal.tpl'],
            'payment method flag' => [BkPaymentMethods::class, 'IsPaymentMethod', 0],
            'payment method icon' => [BkPaymentMethods::class, 'Icon', 'iDEAL.svg'],
            'payment method created at' => [BkPaymentMethods::class, 'CreatedAt', $date],

            'refund status' => [BkRefundRequest::class, 'Status', 'failed'],
            'refund amount' => [BkRefundRequest::class, 'Amount', 12.5],
            'refund key' => [BkRefundRequest::class, 'Key', 'ABCDEF'],
            'refund created at' => [BkRefundRequest::class, 'CreatedAt', $date],
        ];
    }

    /**
     * @dataProvider accessorProvider
     */
    public function testSetterValueIsReturnedByGetter(string $class, string $property, $value): void
    {
        $entity = new $class();
        $entity->{'set' . $property}($value);

        self::assertSame($value, $entity->{'get' . $property}());
    }

    /**
     * Entities without setId() get their id from Doctrine.
     *
     * @return array<string, array{0: string}>
     */
    public function generatedIdProvider(): array
    {
        return [
            'credit card' => [BkCreditcards::class],
            'gift card' => [BkGiftcards::class],
            'refund request' => [BkRefundRequest::class],
        ];
    }

    /**
     * @dataProvider generatedIdProvider
     */
    public function testGeneratedIdIsReturned(string $class): void
    {
        $entity = new $class();
        self::setProperty($entity, 'id', 77);

        self::assertSame(77, $entity->getId());
    }

    public function testGiftCardGetAllExposesTheApiShape(): void
    {
        $card = new BkGiftcards();
        self::setProperty($card, 'id', 5);
        $card->setName('Your Gift');
        $card->setCode('yourgift');
        $card->setLogo('YourGift.svg');
        $card->setIsCustom(0);

        self::assertSame([
            'id' => 5,
            'name' => 'Your Gift',
            'service_code' => 'yourgift',
            'is_custom' => 0,
            'logo_url' => 'YourGift.svg',
        ], $card->getAll());
    }

    public function testRefundRequestSettersAreFluentAndStoreEveryField(): void
    {
        $date = new \DateTime('2026-01-01');
        $request = new BkRefundRequest();

        $result = $request
            ->setStatus(BkRefundRequest::STATUS_SUCCESS)
            ->setOrderId(42)
            ->setAmount(10.0)
            ->setKey('REFUNDKEY')
            ->setPaymentKey('PAYKEY')
            ->setPayload(['amount' => 10.0])
            ->setData(['status' => 190])
            ->setCreatedAt($date);

        self::assertSame($request, $result);
        self::assertSame('success', $request->getStatus());
        self::assertSame(42, self::getProperty($request, 'orderId'));
        self::assertSame('PAYKEY', self::getProperty($request, 'paymentKey'));
        self::assertSame(['amount' => 10.0], self::getProperty($request, 'payload'));
        self::assertSame(['status' => 190], self::getProperty($request, 'data'));
        self::assertSame($date, $request->getCreatedAt());
    }
}
