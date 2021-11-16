<?php
/**
 *
 *
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

require_once dirname(__FILE__) . '/../../library/logger.php';
require_once dirname(__FILE__) . '/../abstract.php';
require_once dirname(__FILE__) . '/../soap.php';
require_once dirname(__FILE__) . '/responsefactory.php';

abstract class PaymentMethod extends BuckarooAbstract
{
    //put your code here
    protected $type;

    /**
     * @param mixed $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    public $currency;
    public $amountDedit;
    public $amountCredit = 0;
    public $orderId;
    public $invoiceId;
    public $description;
    public $OriginalTransactionKey;
    public $returnUrl;
    public $mode;
    public $version;
    public $usecreditmanagment = 0;
    protected $data            = array();

    // @codingStandardsIgnoreStart
    public function pay($customVars = array())
    {
        // @codingStandardsIgnoreEnd
        $this->data['services'][$this->type]['action']  = 'Pay';
        $this->data['services'][$this->type]['version'] = $this->version;

        return $this->payGlobal();
    }

    public function refund()
    {
        $this->data['services'][$this->type]['action']  = 'Refund';
        $this->data['services'][$this->type]['version'] = $this->version;

        return $this->refundGlobal();
    }

    public function payGlobal()
    {
        $this->data['currency']     = $this->currency;
        $this->data['amountDebit']  = $this->amountDedit;
        $this->data['amountCredit'] = $this->amountCredit;
        $this->data['invoice']      = $this->invoiceId;
        $this->data['order']        = $this->orderId;
        $this->data['description']  = $this->description;
        $this->data['returnUrl']    = $this->returnUrl;
        $this->data['mode']         = $this->mode;
        $soap                       = new Soap($this->data);
        return ResponseFactory::getResponse($soap->transactionRequest());
    }

    public function refundGlobal()
    {
        if ($this->type == "afterpay") {
            if ($refund_amount = Tools::getValue('refund_amount')) {
                $this->data['customVars'][$this->type]["RefundType"][0]["value"] = 'Return';
                $this->data['customVars'][$this->type]["RefundType"][0]["group"] = 'Article';
                $this->data['customVars'][$this->type]["Description"][0]["value"] = 'Refund';
                $this->data['customVars'][$this->type]["Description"][0]["group"] = 'Article';
                $this->data['customVars'][$this->type]["Identifier"][0]["value"] = '1';
                $this->data['customVars'][$this->type]["Identifier"][0]["group"] = 'Article';
                $this->data['customVars'][$this->type]["Quantity"][0]["value"] = 1;
                $this->data['customVars'][$this->type]["Quantity"][0]["group"] = 'Article';
                $this->data['customVars'][$this->type]["GrossUnitprice"][0]["value"] = $refund_amount;
                $this->data['customVars'][$this->type]["GrossUnitprice"][0]["group"] = 'Article';
                $this->data['customVars'][$this->type]["VatPercentage"][0]["value"] = 0;
                $this->data['customVars'][$this->type]["VatPercentage"][0]["group"] = 'Article';
            }
        }

        $this->data['currency']               = $this->currency;
        $this->data['amountDebit']            = $this->amountDedit;
        $this->data['amountCredit']           =
            Tools::getValue('refund_amount') ? Tools::getValue('refund_amount') : $this->amountCredit;
        $this->data['invoice']                = $this->invoiceId;
        $this->data['order']                  = $this->orderId;
        $this->data['description']            = $this->description;
        $this->data['OriginalTransactionKey'] = $this->OriginalTransactionKey;
        $this->data['returnUrl']              = $this->returnUrl;
        $this->data['mode']                   = $this->mode;
        $soap                                 = new Soap($this->data);
        return ResponseFactory::getResponse($soap->transactionRequest());
    }

    public static function isIBAN($iban)
    {
        // Normalize input (remove spaces and make upcase)
        $iban = Tools::strtoupper(str_replace(' ', '', $iban));

        if (preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/', $iban)) {
            $country = Tools::substr($iban, 0, 2);
            $check   = (int) (Tools::substr($iban, 2, 2));
            $account = Tools::substr($iban, 4);

            // To numeric representation
            $search  = range('A', 'Z');
            $replace = array();
            foreach (range(10, 35) as $tmp) {
                $replace[] = (string) ($tmp);
            }
            $numstr = str_replace($search, $replace, $account . $country . '00');

            // Calculate checksum
            $checksum = (int) (Tools::substr($numstr, 0, 1));
            for ($pos = 1; $pos < Tools::strlen($numstr); $pos++) {
                $checksum *= 10;
                $checksum += (int) (Tools::substr($numstr, $pos, 1));
                $checksum %= 97;
            }

            return ((98 - $checksum) == $check);
        } else {
            return false;
        }
    }

    // @codingStandardsIgnoreStart
    public function verify($customVars = array())
    {
        // @codingStandardsIgnoreEnd
        $this->data['services'][$this->type]['action']  = 'verify';
        $this->data['services'][$this->type]['version'] = $this->version;

        $this->data['returnUrl']    = $this->returnUrl;
        $this->data['mode']         = $this->mode;
        $soap                       = new Soap($this->data);
        return ResponseFactory::getResponse($soap->dataRequest());
    }
}
