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
namespace Buckaroo\Src\Service;

class PaymentMethodConfigService
{
    private $paymentMapping = [
        'bancontact' => 'MISTERCASH',
        'sofort' => 'SOFORTBANKING',
        'sepadirectdebit' => 'SDD',
    ];

    private $configKeyMap = [
        'mode' => 'MODE',
        'frontend_label' => 'LABEL',
        'payment_fee' => 'FEE',
        'min_order_amount' => 'MIN_VALUE',
        'max_order_amount' => 'MAX_VALUE',
    ];

    public function getPaymentConfig($paymentName)
    {
        $paymentMethod = $this->mapPaymentMethod($paymentName);

        $configData = [];

        foreach ($this->configKeyMap as $dataKey => $configKey) {
            $configData[$dataKey] = \Configuration::get('BUCKAROO_' . strtoupper($paymentMethod) . '_' . $configKey);
        }

        return $configData;
    }

    public function updatePaymentConfig($paymentName, $data)
    {
        $paymentMethod = $this->mapPaymentMethod($paymentName);

        foreach ($this->configKeyMap as $dataKey => $configKey) {
            if (isset($data[$dataKey])) {
                \Configuration::updateValue('BUCKAROO_' . strtoupper($paymentMethod) . '_' . $configKey, $data[$dataKey]);
            }
        }
    }

    private function mapPaymentMethod($paymentName)
    {
        return $this->paymentMethodMap[$paymentName] ?? $paymentName;
    }
}
