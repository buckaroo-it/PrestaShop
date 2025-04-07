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

namespace Buckaroo\PrestaShop\Src\Repository;

if (!defined('_PS_VERSION_')) {
    exit;
}

class RawPaymentMethodRepository
{
    /**
     * @throws \Exception
     */
    public function insertPaymentMethods()
    {
        $this->clearPaymentMethodsTable();
        $this->clearConfigurationTable();
        $paymentMethodsData = $this->getPaymentMethodsData();

        foreach ($paymentMethodsData as $methodData) {
            $this->insertPaymentMethod($methodData);
        }

        return $paymentMethodsData;
    }

    private function insertPaymentMethod(array $methodData): void
    {
        $data = [
            'name' => pSQL($methodData['name']),
            'label' => pSQL($methodData['label']),
            'icon' => pSQL($methodData['icon']),
            'template' => pSQL($methodData['template']),
            'is_payment_method' => pSQL($methodData['is_payment_method']),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if (!\Db::getInstance()->insert('bk_payment_methods', $data)) {
            throw new \Exception('Database error: Could not insert payment method');
        }

        $paymentMethodId = \Db::getInstance()->Insert_ID();
        $this->insertConfiguration($methodData['name'], $paymentMethodId);
    }

    private function insertConfiguration(string $paymentName, int $paymentMethodId): void
    {
        $configValue = ['mode' => 'off'];

        switch ($paymentName) {
            case 'klarna':
                $configValue['financial_warning'] = true;
                break;

            case 'creditcard':
            case 'ideal':
                $configValue['show_issuers'] = true;

            case 'paybybank':
                $configValue['display_type'] = 'radio';
                break;

            case 'in3':
                $configValue['version'] = 'V3';
                $configValue['financial_warning'] = true;
                break;

            case 'paypal':
                $configValue['seller_protection'] = '0';
                break;

            case 'afterpay':
            case 'billink':
                $configValue['customer_type'] = 'B2C';
                $configValue['financial_warning'] = true;
                break;

            case 'payperemail':
                $configValue['send_instruction_email'] = '1';
                $configValue['due_days'] = '7';
                $configValue['allowed_payments'] = 'ideal';
                break;

            case 'transfer':
                $configValue['send_instruction_email'] = '0';
                $configValue['due_days'] = '14';
                break;

            case 'idin':
                $configValue['display_mode'] = 'global';
                break;
            default:
        }

        $configData = [
            'configurable_id' => $paymentMethodId,
            'value' => json_encode($configValue),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if (!\Db::getInstance()->insert('bk_configuration', $configData)) {
            throw new \Exception('Configuration insert error: Could not insert configuration');
        }
    }

    private function getPaymentMethodsData()
    {
        return [
            ['name' => 'ideal', 'label' => 'iDEAL', 'icon' => 'iDEAL.svg', 'template' => 'payment_ideal.tpl', 'is_payment_method' => '1'],
            ['name' => 'paybybank', 'label' => 'PayByBank', 'icon' => 'PayByBank.gif', 'template' => 'payment_paybybank.tpl', 'is_payment_method' => '1'],
            ['name' => 'paypal', 'label' => 'PayPal', 'icon' => 'PayPal.svg', 'template' => '', 'is_payment_method' => '1'],
            ['name' => 'sepadirectdebit', 'label' => 'SEPA Direct Debit', 'icon' => 'SEPA-directdebit.svg', 'template' => 'payment_sepadirectdebit.tpl', 'is_payment_method' => '1'],
            ['name' => 'kbcpaymentbutton', 'label' => 'KBC', 'icon' => 'KBC.svg', 'template' => '', 'is_payment_method' => '1'],
            ['name' => 'bancontactmrcash', 'label' => 'Bancontact', 'icon' => 'Bancontact.svg', 'template' => '', 'is_payment_method' => '1'],
            ['name' => 'blik', 'label' => 'Blik', 'icon' => 'Blik.svg', 'template' => '', 'is_payment_method' => '1'],
            ['name' => 'giftcard', 'label' => 'Giftcards', 'icon' => 'Giftcards.svg', 'template' => 'payment_giftcards.tpl', 'is_payment_method' => '1'],
            ['name' => 'creditcard', 'label' => 'Cards', 'icon' => 'Creditcards.svg', 'template' => 'payment_creditcard.tpl', 'is_payment_method' => '1'],
            ['name' => 'belfius', 'label' => 'Belfius', 'icon' => 'Belfius.svg', 'template' => '', 'is_payment_method' => '1'],
            ['name' => 'afterpay', 'label' => 'Riverty', 'icon' => 'AfterPay.svg', 'template' => 'payment_afterpay.tpl', 'is_payment_method' => '1'],
            ['name' => 'klarna', 'label' => 'Klarna', 'icon' => 'Klarna.svg', 'template' => 'payment_klarna.tpl', 'is_payment_method' => '1'],
            ['name' => 'applepay', 'label' => 'Apple Pay', 'icon' => 'ApplePay.svg', 'template' => '', 'is_payment_method' => '1'],
            ['name' => 'in3', 'label' => 'In3', 'icon' => 'In3.svg', 'template' => 'payment_in3.tpl', 'is_payment_method' => '1'],
            ['name' => 'billink', 'label' => 'Billink', 'icon' => 'Billink.svg', 'template' => 'payment_billink.tpl', 'is_payment_method' => '1'],
            ['name' => 'eps', 'label' => 'EPS', 'icon' => 'EPS.svg', 'template' => '', 'is_payment_method' => '1'],
            ['name' => 'przelewy24', 'label' => 'Przelewy24', 'icon' => 'Przelewy24.svg', 'template' => '', 'is_payment_method' => '1'],
            ['name' => 'payperemail', 'label' => 'PayPerEmail', 'icon' => 'PayPerEmail.svg', 'template' => 'payment_payperemail.tpl', 'is_payment_method' => '1'],
            ['name' => 'payconiq', 'label' => 'Payconiq', 'icon' => 'Payconiq.svg', 'template' => '', 'is_payment_method' => '1'],
            ['name' => 'trustly', 'label' => 'Trustly', 'icon' => 'Trustly.svg', 'template' => '', 'is_payment_method' => '1'],
            ['name' => 'transfer', 'label' => 'Bank Transfer', 'icon' => 'SEPA-credittransfer.svg', 'template' => '', 'is_payment_method' => '1'],
            ['name' => 'wechatpay', 'label' => 'WeChatPay', 'icon' => 'WeChat Pay.svg', 'template' => '', 'is_payment_method' => '1'],
            ['name' => 'alipay', 'label' => 'Alipay', 'icon' => 'Alipay.svg', 'template' => '', 'is_payment_method' => '1'],
            ['name' => 'idin', 'label' => 'iDIN', 'icon' => 'iDIN.svg', 'template' => 'idin.tpl', 'is_payment_method' => '0'],
            ['name' => 'multibanco', 'label' => 'Multibanco', 'icon' => 'Multibanco.svg', 'template' => '', 'is_payment_method' => '1'],
            ['name' => 'mbway', 'label' => 'MB WAY', 'icon' => 'MBWay.svg', 'template' => '', 'is_payment_method' => '1'],
            ['name' => 'knaken', 'label' => 'goSettle', 'icon' => 'GoSettle.svg', 'template' => '', 'is_payment_method' => '1'],
        ];
    }

    public function getPaymentMethodsFromDB()
    {
        $sql = new \DbQuery();

        $sql->select('id');
        $sql->from('bk_payment_methods');

        return \Db::getInstance()->executeS($sql);
    }

    public function getPaymentMethodId($name)
    {
        $sql = new \DbQuery();

        $sql->select('id');
        $sql->from('bk_payment_methods');
        $sql->where('name = "' . pSQL($name) . '"');

        return \Db::getInstance()->getValue($sql);
    }

    public function getPaymentMethodMode($name)
    {
        // Fetch the payment method ID
        $paymentId = $this->getPaymentMethodId($name);

        $sql = new \DbQuery();

        $sql->select('value');
        $sql->from('bk_configuration');
        $sql->where('configurable_id = ' . (int) pSQL($paymentId));

        $existingConfig = \Db::getInstance()->getValue($sql);

        if ($existingConfig === false) {
            throw new \Exception('Configuration not found for payment id ' . $paymentId);
        }

        // Decode the existing configuration
        $configArray = json_decode($existingConfig, true);
        if ($configArray === null) {
            throw new \Exception('JSON decode error');
        }

        // Fetch and return the mode from the configuration
        if (isset($configArray['mode'])) {
            return $configArray['mode'];
        } else {
            throw new \Exception('Mode not set for payment id ' . $paymentId);
        }
    }

    public function getPaymentMethodsLabel($name)
    {
        $sql = new \DbQuery();

        $sql->select('label');
        $sql->from('bk_payment_methods');
        $sql->where('name = "' . pSQL($name) . '"');

        return \Db::getInstance()->getValue($sql);
    }

    private function clearPaymentMethodsTable(): void
    {
        $sql = 'DELETE FROM ' . _DB_PREFIX_ . 'bk_payment_methods';
        if (!\Db::getInstance()->execute($sql)) {
            throw new \Exception('Database error: Could not clear payment methods table');
        }
    }

    private function clearConfigurationTable(): void
    {
        $sql = 'DELETE FROM ' . _DB_PREFIX_ . 'bk_configuration';
        if (!\Db::getInstance()->execute($sql)) {
            throw new \Exception('Database error: Could not clear payment methods table');
        }
    }
}
