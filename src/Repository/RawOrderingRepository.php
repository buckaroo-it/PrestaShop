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

class RawOrderingRepository
{
    private $db;
    private $paymentMethodRepository;

    public function __construct()
    {
        $this->db = \Db::getInstance();
        $this->paymentMethodRepository = new RawPaymentMethodRepository();
    }

    /**
     * @throws \Exception
     */
    public function insertCountryOrdering($countryId = null, $paymentMethodsArray = null)
    {
        $this->clearOrderingTable();

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
        $result = $this->db->insert('bk_ordering', $data, true);
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

    private function clearOrderingTable(): void
    {
        $sql = 'DELETE FROM ' . _DB_PREFIX_ . 'bk_ordering';
        if (!\Db::getInstance()->execute($sql)) {
            throw new \Exception('Database error: Could not clear payment methods table');
        }
    }
}
