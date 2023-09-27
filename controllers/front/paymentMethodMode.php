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
include dirname(__FILE__) . '/BaseApiController.php';
use Buckaroo\Src\Repository\ConfigurationRepository;
use Buckaroo\Src\Repository\PaymentMethodRepository;

class Buckaroo3PaymentMethodModeModuleFrontController extends BaseApiController
{
    private $paymentMethodRepository;
    private $configurationRepository;

    public function __construct()
    {
        parent::__construct();

        $this->paymentMethodRepository = new PaymentMethodRepository();  // Instantiate the repository
        $this->configurationRepository = new ConfigurationRepository();
    }
    private const PAYMENT_MAPPING = [
        'bancontact' => 'MISTERCASH',
        'sofort' => 'SOFORTBANKING',
        'sepadirectdebit' => 'SDD',
    ];

    public function initContent()
    {
        parent::initContent();
        $this->authenticate();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendErrorResponse('Invalid request method', 405); // 405: Method Not Allowed

            return;
        }

        $data = $this->getJsonInput();
        if (!isset($data['name'], $data['mode'])) {
            $this->sendErrorResponse('Required data not provided', 400); // 400: Bad Request

            return;
        }

        $this->updatePaymentMode($data['name'], $data['mode']);
        $this->sendResponse(['status' => true]);
    }

    private function updatePaymentMode($name, $mode)
    {
        $this->configurationRepository->updatePaymentMethodMode($name, $mode);  // Call the repository to update the data
        $paymentName = $this->getPaymentConfigName($name);
        Configuration::updateValue('BUCKAROO_' . $paymentName . '_MODE', $mode);
    }

    private function getPaymentConfigName($name)
    {
        return self::PAYMENT_MAPPING[$name] ?? strtoupper($name);
    }
}
