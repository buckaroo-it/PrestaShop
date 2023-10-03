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

use Buckaroo\PrestaShop\Src\Entity\BkConfiguration;

final class ConfigurationRepository extends BkConfiguration
{
    protected $db;
    protected $orderingRepository;

    public function __construct()
    {
        $this->db = \Db::getInstance();
        $this->orderingRepository = new OrderingRepository();
    }

    public function findOneBy($paymentId)
    {
        // Build and execute the SQL query
        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'bk_configuration WHERE configurable_id = ' . pSQL($paymentId);

        return $this->db->getRow($sql);
    }

    public function getPaymentMethodId($name)
    {
        $query = 'SELECT id FROM ' . _DB_PREFIX_ . 'bk_payment_methods WHERE name = "' . pSQL($name) . '"';

        return $this->db->getValue($query);
    }

    private function getConfigArray(int $paymentId): array
    {
        $query = sprintf(
            'SELECT value FROM %sbk_configuration WHERE configurable_id = %d',
            _DB_PREFIX_,
            $paymentId
        );
        $existingConfig = $this->db->getValue($query);

        if ($existingConfig === false) {
            throw new \Exception('Configuration not found for payment id ' . $paymentId);
        }

        $configArray = json_decode($existingConfig, true);

        if ($configArray === null) {
            throw new \Exception('JSON decode error: ' . json_last_error_msg());
        }

        return $configArray;
    }

    public function updatePaymentMethodConfig($name, $data)
    {
        $paymentId = $this->getPaymentMethodId($name);
        $configArray = $this->getConfigArray($paymentId);
        $mergedConfig = array_merge($configArray, $data);

        $configUpdateStatus = $this->updateConfig($paymentId, $mergedConfig);

        //        $orderingUpdateStatus = $this->updateOrdering($data['countries'], $paymentId);

        return $configUpdateStatus;
    }

    private function getOrderingEntry($countryId)
    {
        $query = sprintf(
            'SELECT value FROM %sbk_ordering WHERE country_id = %d',
            _DB_PREFIX_,
            $countryId
        );

        return json_decode($this->db->getValue($query));
    }

    public function updatePaymentMethodMode(string $name, string $mode): bool
    {
        $paymentId = $this->getPaymentMethodId($name);
        $configArray = $this->getConfigArray($paymentId);
        $configArray['mode'] = $mode;

        return $this->updateConfig($paymentId, $configArray);
    }

    private function updateConfig(int $paymentId, array $config): bool
    {
        $updatedConfigEscaped = pSQL(json_encode($config));
        $query = sprintf(
            'UPDATE %sbk_configuration SET value = "%s" WHERE configurable_id = %d',
            _DB_PREFIX_,
            $updatedConfigEscaped,
            $paymentId
        );

        return $this->db->execute($query);
    }

    public function getPaymentMethodConfig(string $name)
    {
        $query = sprintf(
            'SELECT value FROM %sbk_configuration WHERE configurable_id = %d',
            _DB_PREFIX_,
            $this->getPaymentMethodId($name)
        );

        return json_decode($this->db->getValue($query));
    }
}
