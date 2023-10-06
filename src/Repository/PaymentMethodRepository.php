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

final class PaymentMethodRepository
{
    protected $db;

    public function __construct()
    {
        $this->db = \Db::getInstance();
    }

    public function findOneByName($name)
    {
        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'bk_payment_methods WHERE name = "' . pSQL($name) . '"';

        return $this->db->getRow($sql);
    }

    public function insertPaymentMethods()
    {
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
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if (!$this->db->insert('bk_payment_methods', $data)) {
            throw new \Exception('Database error: Could not insert payment method');
        }

        $paymentMethodId = $this->db->Insert_ID();
        $this->insertConfiguration($paymentMethodId);
    }

    private function insertConfiguration(int $paymentMethodId): void
    {
        $configData = [
            'configurable_id' => $paymentMethodId,
            'value' => json_encode(['mode' => 'off']),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if (!$this->db->insert('bk_configuration', $configData)) {
            throw new \Exception('Configuration insert error: Could not insert configuration');
        }
    }

    private function getPaymentMethodsData()
    {
        return [
            ['name' => 'ideal', 'label' => 'iDEAL', 'icon' => 'iDEAL.svg', 'template' => 'payment_ideal.tpl'],
            ['name' => 'paybybank', 'label' => 'PayByBank', 'icon' => 'PayByBank.gif', 'template' => 'payment_paybybank.tpl'],
            ['name' => 'paypal', 'label' => 'PayPal', 'icon' => 'PayPal.svg', 'template' => ''],
            ['name' => 'sepadirectdebit', 'label' => 'SEPA Direct Debit', 'icon' => 'SEPA-directdebit.svg', 'template' => 'payment_sepadirectdebit.tpl'],
            ['name' => 'giropay', 'label' => 'GiroPay', 'icon' => 'Giropay.svg', 'template' => 'payment_giropay.tpl'],
            ['name' => 'kbc', 'label' => 'KBC', 'icon' => 'KBC.svg', 'template' => ''],
            ['name' => 'bancontact', 'label' => 'Bancontact / Mister Cash', 'icon' => 'Bancontact.svg', 'template' => ''],
            ['name' => 'giftcard', 'label' => 'Giftcards', 'icon' => 'Giftcards.svg', 'template' => ''],
            ['name' => 'creditcard', 'label' => 'Credit and debit card', 'icon' => 'Creditcards.svg', 'template' => 'payment_creditcard.tpl'],
            ['name' => 'sofort', 'label' => 'Sofortbanking', 'icon' => 'Sofort.svg', 'template' => ''],
            ['name' => 'belfius', 'label' => 'Belfius', 'icon' => 'Belfius.svg', 'template' => ''],
            ['name' => 'afterpay', 'label' => 'Riverty | AfterPay', 'icon' => 'AfterPay.svg', 'template' => 'payment_afterpay.tpl'],
            ['name' => 'klarna', 'label' => 'KlarnaKP', 'icon' => 'Klarna.svg', 'template' => 'payment_klarna.tpl'],
            ['name' => 'applepay', 'label' => 'Apple Pay', 'icon' => 'ApplePay.svg', 'template' => ''],
            ['name' => 'in3', 'label' => 'In3', 'icon' => 'In3.svg', 'template' => 'payment_in3.tpl'],
            ['name' => 'billink', 'label' => 'Billink', 'icon' => 'Billink.svg', 'template' => 'payment_billink.tpl'],
            ['name' => 'eps', 'label' => 'EPS', 'icon' => 'EPS.svg', 'template' => ''],
            ['name' => 'przelewy24', 'label' => 'Przelewy24', 'icon' => 'Przelewy24.svg', 'template' => ''],
            ['name' => 'payperemail', 'label' => 'PayPerEmail', 'icon' => 'PayPerEmail.svg', 'template' => 'payment_payperemail.tpl'],
            ['name' => 'payconiq', 'label' => 'Payconiq', 'icon' => 'Payconiq.svg', 'template' => ''],
            ['name' => 'tinka', 'label' => 'Tinka', 'icon' => 'Tinka.svg', 'template' => 'payment_tinka.tpl'],
            ['name' => 'trustly', 'label' => 'Trustly', 'icon' => 'Trustly.svg', 'template' => ''],
            ['name' => 'transfer', 'label' => 'Bank Transfer', 'icon' => 'SEPA-credittransfer.svg', 'template' => ''],
            ['name' => 'wechatpay', 'label' => 'WeChatPay', 'icon' => 'WeChat Pay.svg', 'template' => ''],
            ['name' => 'alipay', 'label' => 'Alipay', 'icon' => 'Alipay.svg', 'template' => '']
        ];
    }

    public function getPaymentMethodsFromDB()
    {
        $query = 'SELECT id FROM ' . _DB_PREFIX_ . 'bk_payment_methods';

        return $this->db->executeS($query);
    }

    public function fetchAllPaymentMethods()
    {
        $query = 'SELECT name, label, icon, template FROM ' . _DB_PREFIX_ . 'bk_payment_methods';

        return $this->db->executeS($query);
    }

    public function getPaymentMethodId($name)
    {
        $query = 'SELECT id FROM ' . _DB_PREFIX_ . 'bk_payment_methods WHERE name = "' . pSQL($name) . '"';

        return $this->db->getValue($query);
    }

    public function getPaymentMethodNames()
    {
        $query = 'SELECT name FROM ' . _DB_PREFIX_ . 'bk_payment_methods';

        return $this->db->executeS($query);
    }

    public function getPaymentMethod($id)
    {
        $query = 'SELECT * FROM ' . _DB_PREFIX_ . 'bk_payment_methods WHERE id = ' . (int) pSQL($id);

        return $this->db->executeS($query);
    }

    public function getPaymentMethodsFromDBWithConfig()
    {
        $sql = '
            SELECT 
                p.name AS payment_name,
                p.icon AS payment_icon,
                c.value AS config_value
            FROM 
                ' . _DB_PREFIX_ . 'bk_payment_methods p
            LEFT JOIN 
                ' . _DB_PREFIX_ . 'bk_configuration c
            ON 
                p.id = c.configurable_id
        ';

        $results = $this->db->executeS($sql);

        if ($results === false) {
            throw new Exception('Database error: Could not fetch payment methods with config');
        }

        // Process and format the results as per your needs
        $payments = [];
        foreach ($results as $result) {
            // Prepare a base payment array
            $payment = [
                'name' => $result['payment_name'],
                'icon' => $result['payment_icon'],
            ];

            // Check if config_value is set and is a valid JSON string
            if (isset($result['config_value']) && ($configArray = json_decode($result['config_value'], true)) !== null) {
                // Check if json_decode was successful
                if (json_last_error() === JSON_ERROR_NONE) {
                    // Merge the config array with the other values
                    $payment = array_merge($payment, $configArray);
                } else {
                    throw new \Exception('JSON decode error: ' . json_last_error_msg());
                }
            }

            // Append the payment array to the payments array
            $payments[] = $payment;
        }

        return $payments;
    }

    public function getPaymentMethodMode($name)
    {
        // Fetch the payment method ID
        $paymentId = $this->getPaymentMethodId($name);

        // Fetch the existing configuration
        $query = 'SELECT value FROM ' . _DB_PREFIX_ . 'bk_configuration WHERE configurable_id = ' . (int) pSQL($paymentId);
        $existingConfig = $this->db->getValue($query);

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
}
