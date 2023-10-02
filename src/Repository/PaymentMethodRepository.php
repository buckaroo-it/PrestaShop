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

            $data = [
                'name' => pSQL($methodData['name']),
                'icon' => pSQL($methodData['icon']),
                'created_at' => date('Y-m-d H:i:s'),
            ];

            if (!$this->db->insert('bk_payment_methods', $data)) {
                throw new \Exception('Database error: Could not insert payment method');
            }
            $paymentMethodId = $this->db->Insert_ID();

            $this->insertConfiguration($paymentMethodId);
        }

        return $paymentMethodsData;
    }

    private function insertPaymentMethod(array $methodData): void
    {
        $data = [
            'name' => pSQL($methodData['name']),
            'icon' => pSQL($methodData['icon']),
            'created_at' => (new DateTime())->format('Y-m-d H:i:s'),
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
        ];

        if (!$this->db->insert('bk_configuration', $configData)) {
            throw new \Exception('Configuration insert error: Could not insert configuration');
        }
    }

    private function getPaymentMethodsData()
    {
        return [
            ['name' => 'ideal', 'icon' => 'iDEAL.svg'],
            ['name' => 'paybybank', 'icon' => 'paybybank.gif'],
            ['name' => 'paypal', 'icon' => 'PayPal.svg'],
            ['name' => 'sepadirectdebit', 'icon' => 'SEPA-directdebit.svg'],
            ['name' => 'giropay', 'icon' => 'Giropay.svg'],
            ['name' => 'kbc', 'icon' => 'KBC.svg'],
            ['name' => 'bancontact', 'icon' => 'Bancontact.svg'],
            ['name' => 'giftcard', 'icon' => 'Giftcards.svg'],
            ['name' => 'creditcard', 'icon' => 'Creditcards.svg'],
            ['name' => 'sofort', 'icon' => 'Sofort.svg'],
            ['name' => 'belfius', 'icon' => 'Belfius.svg'],
            ['name' => 'afterpay', 'icon' => 'AfterPay.svg'],
            ['name' => 'klarna', 'icon' => 'Klarna.svg'],
            ['name' => 'applepay', 'icon' => 'ApplePay.svg'],
            ['name' => 'in3', 'icon' => 'In3.svg'],
            ['name' => 'billink', 'icon' => 'Billink.svg'],
            ['name' => 'eps', 'icon' => 'EPS.svg'],
            ['name' => 'przelewy24', 'icon' => 'Przelewy24.svg'],
            ['name' => 'payperemail', 'icon' => 'PayPerEmail.svg'],
            ['name' => 'payconiq', 'icon' => 'Payconiq.svg'],
            ['name' => 'tinka', 'icon' => 'Tinka.svg'],
            ['name' => 'trustly', 'icon' => 'Trustly.svg'],
            ['name' => 'transfer', 'icon' => 'SEPA-credittransfer.svg'],
        ];
    }

    public function getPaymentMethodsFromDB()
    {
        $query = 'SELECT id FROM ' . _DB_PREFIX_ . 'bk_payment_methods';

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
