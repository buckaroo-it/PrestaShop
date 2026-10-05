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

require_once dirname(__FILE__) . '/../paymentmethod.php';
class GiftCard extends PaymentMethod
{
    /**
     * Service codes that use FashionCheque-specific parameters.
     */
    private const FASHION_CHEQUE_CODES = ['fashioncheque'];

    /**
     * Service codes that use TCS-specific parameters.
     */
    private const TCS_CODES = ['tcs'];

    public function __construct()
    {
        $this->type = 'giftcard';
    }

    public function pay($customVars = [])
    {
        $this->payload = $this->getPayload($customVars);

        return parent::executeCustomPayAction('payRedirect');
    }

    public function payDirect($customVars = [])
    {
        $this->payload = $this->getPayload($this->mapDirectPayParameters($customVars));
        $action = !empty($this->OriginalTransactionKey) ? 'payRemainder' : 'pay';

        return parent::executeCustomPayAction($action);
    }

    public function getPayload($data)
    {
        return array_merge_recursive($this->payload, $data);
    }

    /**
     * Map checkout card number/PIN to the Buckaroo service parameters expected
     * for the selected giftcard brand (mirrors Magento2 Giftcard request).
     */
    private function mapDirectPayParameters(array $data): array
    {
        $cardNumber = (string) ($data['cardNumber'] ?? $data['intersolveCardnumber'] ?? '');
        $pin = (string) ($data['pin'] ?? $data['intersolvePIN'] ?? '');
        $cardCode = strtolower((string) ($data['name'] ?? ''));

        unset($data['cardNumber'], $data['pin']);

        if ($cardNumber === '' && $pin === '') {
            return $data;
        }

        // Already mapped by caller.
        if (isset($data['intersolveCardnumber'])
            || isset($data['fashionChequeCardNumber'])
            || isset($data['tcsCardnumber'])
        ) {
            return $data;
        }

        if (in_array($cardCode, self::FASHION_CHEQUE_CODES, true)) {
            $data['fashionChequeCardNumber'] = $cardNumber;
            $data['fashionChequePin'] = $pin;
        } elseif (in_array($cardCode, self::TCS_CODES, true)) {
            $data['tcsCardnumber'] = $cardNumber;
            $data['tcsValidationCode'] = $pin;
        } elseif ($cardCode !== '' && strpos($cardCode, 'customgiftcard') === 0) {
            // Custom giftcard services use the generic Cardnumber/PIN parameters.
            $data['cardNumber'] = $cardNumber;
            $data['pin'] = $pin;
        } else {
            // Default brands (boekenbon, yourgift, vvvgiftcard, …) are Intersolve.
            $data['intersolveCardnumber'] = $cardNumber;
            $data['intersolvePIN'] = $pin;
        }

        return $data;
    }
}
