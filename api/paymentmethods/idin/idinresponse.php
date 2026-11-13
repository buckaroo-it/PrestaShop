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
if (!defined('_PS_VERSION_')) {
    exit;
}

use Buckaroo\PrestaShop\Src\Service\BuckarooIdinService;

require_once dirname(__FILE__) . '/../response.php';
require_once dirname(__FILE__) . '/../../corelogger.php';
class IdinResponse extends Response
{
    protected $buckarooIdinService;

    public function __construct($transactionResponse = null, ?BuckarooIdinService $buckarooIdinService = null)
    {
        $this->buckarooIdinService = $buckarooIdinService ?? new BuckarooIdinService();
        parent::__construct($transactionResponse);
    }

    /**
     * Stores the iDIN result for the customer the verify request was started for.
     *
     * Building the response stores nothing. The result counts only when the reply
     * signature is valid, the verification succeeded, and the signed customer id
     * owns the signed cart id (both are additional parameters of the verify request).
     */
    public function storeCustomerVerification(): bool
    {
        if (!$this->isPaid()) {
            $this->logger->logInfo('iDIN reply not stored: not a valid, successful reply');

            return false;
        }

        $customerId = $this->toPositiveInt(Tools::getValue('ADD_cid'));
        $cartId = $this->toPositiveInt(Tools::getValue('ADD_cart'));
        $consumerBin = (string) Tools::getValue('brq_SERVICE_idin_ConsumerBIN');
        $isEighteenOrOlder = (string) Tools::getValue('brq_SERVICE_idin_IsEighteenOrOlder');

        if ($customerId === 0 || $cartId === 0 || $consumerBin === '' || $isEighteenOrOlder === '') {
            $this->logger->logError('iDIN reply not stored: customer, cart or result missing');

            return false;
        }

        if ($this->getCartCustomerId($cartId) !== $customerId) {
            $this->logger->logError('iDIN reply not stored: customer does not own the cart', [
                'customer' => $customerId,
                'cart' => $cartId,
            ]);

            return false;
        }

        if ($this->buckarooIdinService->checkCustomerIdExists($customerId)) {
            $this->buckarooIdinService->updateCustomerData($customerId, $consumerBin, $isEighteenOrOlder);
        } else {
            $this->buckarooIdinService->insertCustomerData($customerId, $consumerBin, $isEighteenOrOlder);
        }

        return true;
    }

    protected function getCartCustomerId(int $cartId): int
    {
        $cart = new Cart($cartId);

        return Validate::isLoadedObject($cart) ? (int) $cart->id_customer : 0;
    }

    /**
     * @param mixed $value
     */
    private function toPositiveInt($value): int
    {
        if (is_int($value)) {
            return max($value, 0);
        }

        return is_string($value) && ctype_digit($value) ? (int) $value : 0;
    }
}
