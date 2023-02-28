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

require_once dirname(__FILE__) . '/idin/idinresponse.php';
require_once dirname(__FILE__) . '/responsedefault.php';
require_once _PS_ROOT_DIR_ . '/modules/buckaroo3/vendor/autoload.php';
use Buckaroo\BuckarooClient;
use Buckaroo\Handlers\Reply\ReplyHandler;
use Buckaroo\Transaction\Response\TransactionResponse;


class ResponseFactory
{
    final public static function getResponse($transactionResponse = null)
    {

        if($transactionResponse != null) {
            //print_r($transactionResponse);
            //exit;
            $data = $transactionResponse->data();

            if(isset($data['Services'][0]['Name']))
            {
                $paymentmethod = $data['Services'][0]['Name'];
            }elseif(!empty($data['ServiceCode'])) {
                $paymentmethod = $data['ServiceCode'];
            }else{
                $paymentmethod = null;
            }
        } elseif(isset($_POST['brq_payment_method'])) {
            $paymentmethod = $_POST['brq_payment_method'];
        } else {
            $paymentmethod = null;
        }
        
        switch ($paymentmethod) {
            case 'IDIN':
                return new IdinResponse($transactionResponse);
            default:
                return new ResponseDefault($transactionResponse);
                break;
        }
    }
}
