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
            $data = [
                'name' => pSQL($methodData['name']),
                'icon' => pSQL($methodData['icon']),
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $result = $this->db->insert('bk_payment_methods', $data);

            if (!$result) {
                // Handle error
                die('Database error');
            }

            // Get the ID of the newly inserted payment method
            $paymentMethodId = $this->db->Insert_ID();

            // Prepare the configuration data
            $configData = [
                'configurable_id' => $paymentMethodId,  // assuming the column name is configurable_id
                'value' => json_encode(['mode' => 'off']),
            ];

            // Insert the configuration data into the configuration table
            $result = $this->db->insert('bk_configuration', $configData);

            if (!$result) {
                // Handle error
                die('Configuration insert error');
            }
        }

        return $paymentMethodsData;
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
        $query = 'SELECT id FROM ' . _DB_PREFIX_ . "bk_payment_methods WHERE name = '$name'";

        return $this->db->getValue($query);
    }

    public function getPaymentMethodNames()
    {
        $query = 'SELECT name FROM ' . _DB_PREFIX_ . 'bk_payment_methods';

        return $this->db->executeS($query);
    }

    public function getPaymentMethod($id)
    {
        $query = 'SELECT * FROM ' . _DB_PREFIX_ . 'bk_payment_methods WHERE id = ' . (int) $id;

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
                    // Optionally, handle JSON decoding error
                    error_log('JSON decoding error: ' . json_last_error_msg());
                    exit;
                }
            }

            // Append the payment array to the payments array
            $payments[] = $payment;
        }

        return $payments;
    }

    private function insertPaymentMethodsToDB($paymentMethods)
    {
        foreach ($paymentMethods as $methodData) {
            $data = [
                'id' => pSQL($methodData['id']),
                'name' => pSQL($methodData['name']),
                'icon' => pSQL($methodData['icon']),
                'mode' => pSQL($methodData['mode']),
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $result = $this->db->insert('bk_payment_methods', $data);

            if (!$result) {
                // Handle error
                error_log('Database error');
                exit;
            }
        }
    }

    public function getPaymentMethodMode($name)
    {
        // Fetch the payment method ID
        $paymentId = $this->getPaymentMethodId($name);

        // Fetch the existing configuration
        $query = 'SELECT value FROM ' . _DB_PREFIX_ . 'bk_configuration WHERE configurable_id = ' . (int) $paymentId;
        $existingConfig = $this->db->getValue($query);

        if ($existingConfig === false) {
            // Handle error (e.g., configuration not found)
            error_log('Configuration not found for payment id ' . $paymentId);

            return null;  // You might want to return null or some default value, or throw an exception
        }

        // Decode the existing configuration
        $configArray = json_decode($existingConfig, true);
        if ($configArray === null) {
            // Handle JSON decode error
            error_log('JSON decode error: ' . json_last_error_msg());

            return null;  // Again, decide how you want to handle this error
        }

        // Fetch and return the mode from the configuration
        if (isset($configArray['mode'])) {
            return $configArray['mode'];
        } else {
            error_log('Mode not set for payment id ' . $paymentId);

            return null;  // Or some default value, or throw an exception
        }
    }
}
