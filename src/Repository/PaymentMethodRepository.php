<?php

namespace Buckaroo\Prestashop\Repository;
use Db;

final class PaymentMethodRepository
{
    public function insertPaymentMethods()
    {
        $paymentMethodsData = $this->getPaymentMethodsData();

        $db = Db::getInstance();

        foreach ($paymentMethodsData as $methodData) {
            $data = [
                'name' => pSQL($methodData['name']),
                'icon' => pSQL($methodData['icon']),
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $result = $db->insert('bk_payment_methods', $data);

            if (!$result) {
                // Handle error
                die('Database error');
            }

            // Get the ID of the newly inserted payment method
            $paymentMethodId = $db->Insert_ID();

            // Prepare the configuration data
            $configData = [
                'configurable_id' => $paymentMethodId,  // assuming the column name is configurable_id
                'value' => json_encode(['mode' => 'off'])
            ];

            // Insert the configuration data into the configuration table
            $result = $db->insert('bk_configuration', $configData);

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
            ['name' => 'trustly', 'icon' => 'Trustly.svg']
        ];
    }

    public function getPaymentMethodsFromDB()
    {
        $db = Db::getInstance();
        $query = 'SELECT id, name, icon FROM ps_bk_payment_methods';
        return $db->executeS($query);
    }

    public function getPaymentMethodId($name)
    {
        $db = Db::getInstance();
        $query = "SELECT id FROM ps_bk_payment_methods WHERE name = '" . $name . "'";
        return $db->getValue($query);
    }

    public function getPaymentMethodsId()
    {
        $db = Db::getInstance();
        $query = "SELECT id FROM ps_bk_payment_methods";
        return $db->executeS($query);
    }
    public function getPaymentMethodsFromDBWithConfig()
    {
        $db = Db::getInstance();

        $sql = "
            SELECT 
                p.name AS payment_name,
                p.icon AS payment_icon,
                c.value AS config_value
            FROM 
                ps_bk_payment_methods p
            LEFT JOIN 
                ps_bk_configuration c
            ON 
                p.id = c.configurable_id
        ";

        $results = $db->executeS($sql);

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
                }
            }

            // Append the payment array to the payments array
            $payments[] = $payment;
        }

        return $payments;
    }

    private function insertPaymentMethodsToDB($paymentMethods)
    {
        $db = Db::getInstance();

        foreach ($paymentMethods as $methodData) {
            $data = [
                'id' => pSQL($methodData['id']),
                'name' => pSQL($methodData['name']),
                'icon' => pSQL($methodData['icon']),
                'mode' => pSQL($methodData['mode']),
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $result = $db->insert('bk_payment_methods', $data);

            if (!$result) {
                // Handle error
                die('Database error');
            }
        }
    }
}

