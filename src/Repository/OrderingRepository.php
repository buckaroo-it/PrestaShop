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

namespace Buckaroo\Src\Repository;

class OrderingRepository
{
    private $db;
    private $paymentMethodRepository;

    public function __construct()
    {
        $this->db = \Db::getInstance();
        $this->paymentMethodRepository = new PaymentMethodRepository();
    }

    public function getOrdering()
    {
        $query = 'SELECT * FROM ' . _DB_PREFIX_ . 'bk_ordering';
        $result = $this->db->executeS($query);
        $row = $result[0];

        // Decode the JSON data in the 'value' column.
        $value = json_decode($row['value'], true);

        // Now we'll construct the desired output format.
        $output = [
            'id' => $row['id'],
            'country_id' => $row['country_id'],
            'created_at' => $row['created_at'],
            'value' => $value,
            'status' => true,
        ];

        return $output;
    }

    /**
     * @throws \Exception
     */
    public function insertCountryOrdering($countryId = null, $paymentMethodsArray = null)
    {
        return $this->insertCountryOrderingToDB($countryId, $paymentMethodsArray);
    }

    /**
     * @throws \Exception
     */
    private function insertCountryOrderingToDB($countryId, $paymentMethodsArray)
    {
        if ($paymentMethodsArray === null) {
            $paymentMethods = $this->paymentMethodRepository->getPaymentMethodsFromDB();
            $paymentMethodsArray = [];
            foreach ($paymentMethods as $row) {
                $paymentMethodsArray[] = $row;
            }
        }
        $data = $this->prepareData($countryId, $paymentMethodsArray);
        $result = $this->db->insert('bk_ordering', $data, $null_values = true);
        if (!$result) {
            throw new \Exception('Database error: Unable to insert country');
        }

        return $result;
    }

    private function prepareData($countryId, $paymentMethodsArray)
    {
        return [
            'country_id' => pSQL($countryId),
            'value' => pSQL(json_encode($paymentMethodsArray)),
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }
}
