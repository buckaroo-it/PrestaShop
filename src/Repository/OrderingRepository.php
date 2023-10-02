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

class OrderingRepository
{
    private $db;
    private $paymentMethodRepository;

    public function __construct()
    {
        $this->db = \Db::getInstance();
        $this->paymentMethodRepository = new PaymentMethodRepository();
    }

    public function updateOrdering($value, $countryId)
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Handle JSON decode error
                return false;
            }
        }

        $idArray = [];
        foreach ($value as $item) {
            if (isset($item['id'])) {
                $idArray[] = $item['id'];
            }
        }
        $value = json_encode($idArray);

        // Note: pSQL is used for simple sanitization, but does not replace the security of prepared statements.
        // Be sure to validate and sanitize all input.
        $query = '
        UPDATE 
            ' . _DB_PREFIX_ . 'bk_ordering
        SET 
            value = "' . pSQL($value, true) . '"
        WHERE 
            country_id ' . ($countryId === null ? 'is NULL' : '= "' . pSQL($countryId, true) . '"')
        ;

        return $this->db->execute($query);
    }

    public function getOrdering($countryIsoCode)
    {
        if ($countryIsoCode === null) {
            $query = 'SELECT * FROM ' . _DB_PREFIX_ . 'bk_ordering';
        } else {
            $query = '
            SELECT ' . _DB_PREFIX_ . 'bk_ordering.* 
            FROM ' . _DB_PREFIX_ . 'bk_ordering
            JOIN ' . _DB_PREFIX_ . 'bk_countries ON ' . _DB_PREFIX_ . 'bk_ordering.country_id = ' . _DB_PREFIX_ . 'bk_countries.country_id
            WHERE ' . _DB_PREFIX_ . 'bk_countries.iso_code_2 ' . ($countryIsoCode === null ? 'IS NULL' : '= "' . pSQL($countryIsoCode) . '"');
        }

        $result = $this->db->executeS($query);
        if (empty($result)) {
            return null;  // or however you want to handle no results
        }
        $row = $result[0];

        // Decode the JSON data in the 'value' column.
        $value = json_decode($row['value'], true);

        $output = [
            'id' => $row['id'],
            'country_id' => $row['country_id'],
            'created_at' => $row['created_at'],
            'value' => [],
            'status' => true,
        ];

        // Iterate over the payment method IDs and fetch their data
        foreach ($value as $id) {
            $paymentMethodData = $this->paymentMethodRepository->getPaymentMethod($id);
            if (!empty($paymentMethodData)) {
                $output['value'][] = $paymentMethodData[0];  // Assumes getPaymentMethod() returns an array with the payment method data as the first element
            }
        }

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
                $paymentMethodsArray[] = $row['id'];
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

    public function getPositionByCountryId(int $countryId): ?array
    {
        $positions = $this->fetchPositions($countryId);

        // If no positions found for the given country_id, try fetching positions for null country_id
        if ($positions === null) {
            $positions = $this->fetchPositions(null);
        }

        return $positions;
    }

    /**
     * Helper method to fetch positions based on country_id
     *
     * @param int|null $countryId
     *
     * @return array|null The positions array if found, or null if not
     */
    private function fetchPositions(?int $countryId): ?array
    {
        $query = '
            SELECT value
            FROM ' . _DB_PREFIX_ . 'bk_ordering
            WHERE country_id ' . ($countryId === null ? 'IS NULL' : '= ' . pSQL((int) $countryId)) . '
        ';

        $result = $this->db->getRow($query);  // assuming getRow returns a single row as an associative array

        if ($result && isset($result['value'])) {
            $positionsArray = json_decode($result['value'], true);

            $output = [];
            foreach ($positionsArray as $position => $id) {
                $paymentMethodData = $this->paymentMethodRepository->getPaymentMethod($id);
                if (!empty($paymentMethodData)) {
                    $output[] = $paymentMethodData[0]['name'];
                }
            }

            return $output;
        }

        return null;  // or however you want to handle no results
    }
}
