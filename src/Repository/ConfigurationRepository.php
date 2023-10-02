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
    private $paymentMethodRepository;
    protected $db;

    public function __construct()
    {
        $this->paymentMethodRepository = new PaymentMethodRepository();  // Instantiate the repository
        $this->db = \Db::getInstance();
    }

    public function findOneBy(array $criteria)
    {
        // Build the WHERE clause of the SQL query from the criteria array
        $whereClauses = [];
        foreach ($criteria as $field => $value) {
            $whereClauses[] = $field . ' = "' . pSQL($value) . '"';
        }
        $whereClause = implode(' AND ', $whereClauses);

        // Build and execute the SQL query
        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'bk_configuration WHERE ' . $whereClause;

        return $this->db->getRow($sql);
    }

    public function updatePaymentMethodConfig($name, $data)
    {
        $paymentId = $this->paymentMethodRepository->getPaymentMethodId($name);

        // Fetch the existing configuration
        $query = 'SELECT value FROM ' . _DB_PREFIX_ . 'bk_configuration WHERE configurable_id = ' . (int) $paymentId;
        $existingConfig = $this->db->getValue($query);

        if ($existingConfig === false) {
            // Handle error (e.g., configuration not found)
            error_log('Configuration not found for payment id ' . $paymentId);
            exit;
        }

        // Decode the existing configuration
        $configArray = json_decode($existingConfig, true);
        if ($configArray === null) {
            // Handle JSON decode error
            error_log('JSON decode error: ' . json_last_error_msg());
            exit;
        }

        // Merge the new data into the existing configuration
        $mergedConfig = array_merge($configArray, $data);

        // Encode the merged configuration
        $updatedConfig = json_encode($mergedConfig);

        // Escape the updated configuration string to prevent SQL injection
        $updatedConfigEscaped = pSQL($updatedConfig);

        // Update the configuration in the database
        $query = '
        UPDATE 
            ' . _DB_PREFIX_ . "bk_configuration 
        SET 
            value = '$updatedConfigEscaped'
        WHERE 
            configurable_id = " . (int) $paymentId;

        if ($this->db->execute($query)) {
            return true;
        } else {
            return false;
        }
    }

    public function updatePaymentMethodMode($name, $mode)
    {
        $paymentId = $this->paymentMethodRepository->getPaymentMethodId($name);

        // Fetch the existing configuration
        $query = 'SELECT value FROM ' . _DB_PREFIX_ . 'bk_configuration WHERE configurable_id = ' . (int) $paymentId;
        $existingConfig = $this->db->getValue($query);

        if ($existingConfig === false) {
            // Handle error (e.g., configuration not found)
            error_log('Configuration not found for payment id ' . $paymentId);
            exit;
        }

        // Decode the existing configuration
        $configArray = json_decode($existingConfig, true);
        if ($configArray === null) {
            // Handle JSON decode error
            error_log('JSON decode error: ' . json_last_error_msg());
            exit;
        }

        // Update the mode
        $configArray['mode'] = $mode;

        // Encode the updated configuration
        $updatedConfig = json_encode($configArray);

        // Escape the updated configuration string to prevent SQL injection
        $updatedConfigEscaped = pSQL($updatedConfig);

        // Update the configuration in the database
        $query = '
        UPDATE 
            ' . _DB_PREFIX_ . "bk_configuration 
        SET 
            value = '$updatedConfigEscaped'
        WHERE 
            id = " . (int) $paymentId;

        return $this->db->execute($query);
    }

    public function getPaymentMethodConfig($name)
    {
        $query = 'SELECT value FROM ' . _DB_PREFIX_ . 'bk_configuration WHERE configurable_id = ' . $this->paymentMethodRepository->getPaymentMethodId($name);

        return json_decode($this->db->getValue($query));
    }
}
