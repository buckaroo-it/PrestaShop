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

namespace Buckaroo\PrestaShop\Src\Service;

require_once dirname(__FILE__) . '/../../vendor/autoload.php';
require_once dirname(__FILE__) . '/../../library/logger.php';

use Buckaroo\PrestaShop\Src\Repository\ConfigurationRepository;
use Buckaroo\PrestaShop\Src\Repository\PaymentMethodRepository;

class BuckarooConfigService
{
    /** @var PaymentMethodRepository */
    private $paymentMethodRepository;

    /** @var ConfigurationRepository */
    private $configurationRepository;

    protected $logger;

    public function __construct()
    {
        $this->paymentMethodRepository = new PaymentMethodRepository();
        $this->configurationRepository = new ConfigurationRepository();
        $this->logger = new \Logger(\Logger::INFO, $fileName = '');
    }

    public function getConfigArrayForMethod($method)
    {
        $paymentId = $this->getPaymentId($method);
        if ($paymentId === null) {
            $this->logError('Payment method not found: ' . $method);

            return null;
        }

        $configuration = $this->getConfiguration($paymentId);
        if ($configuration === null) {
            $this->logError('Configuration not found for payment id ' . $paymentId);

            return null;
        }

        $configArray = $this->getConfigArray($configuration);
        if ($configArray === null) {
            $this->logError('JSON decode error: ' . json_last_error_msg());

            return null;
        }

        return $configArray;
    }

    public function getSpecificValueFromConfig($method, $key)
    {
        $configArray = $this->getConfigArrayForMethod($method);
        if ($configArray === null) {
            return null;
        }

        if (!isset($configArray[$key])) {
            $this->logError("Key {$key} not found in configuration for method {$method}");
        }

        return $configArray[$key] ?? null;
    }

    private function getPaymentId($method)
    {
        $paymentId = $this->paymentMethodRepository->findOneByName($method);

        return is_array($paymentId) && isset($paymentId['id']) ? $paymentId['id'] : null;
    }

    private function getConfiguration($paymentId)
    {
        return $this->configurationRepository->findOneBy(['configurable_id' => $paymentId]);
    }

    protected function logError($message): void
    {
        $this->logger->logInfo($message, 'error');
    }

    private function getConfigArray($configuration)
    {
        if (is_array($configuration) && isset($configuration['value'])) {
            return json_decode($configuration['value'], true);
        }

        return null;
    }
}
