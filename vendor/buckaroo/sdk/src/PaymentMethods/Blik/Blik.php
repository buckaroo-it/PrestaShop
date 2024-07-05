<?php

declare(strict_types=1);

namespace Buckaroo\PaymentMethods\Blik;

use Buckaroo\Models\Model;
use Buckaroo\PaymentMethods\Blik\Models\Refund;
use Buckaroo\PaymentMethods\Blik\Models\Pay;
use Buckaroo\PaymentMethods\Interfaces\Combinable;
use Buckaroo\PaymentMethods\PayablePaymentMethod;
use Buckaroo\Transaction\Response\TransactionResponse;

/**
 *
 */
class Blik extends PayablePaymentMethod implements Combinable
{
    /**
     * @var string
     */
    protected string $paymentName = 'blik';
    /**
     * @var int
     */
    protected int $serviceVersion = 0;

    /**
     * @param Model|null $model
     * @return TransactionResponse
     */
    public function pay(?Model $model = null): TransactionResponse
    {
        return parent::pay($model ?? new Pay($this->payload));
    }

//    /**
//     * @param Model|null $model
//     * @return TransactionResponse
//     */
//    public function refund(?Model $model = null): TransactionResponse
//    {
//        return parent::refund($model ?? new Refund($this->payload));
//    }
//


}
