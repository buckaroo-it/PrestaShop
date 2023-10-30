<?php
/*
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */

namespace Buckaroo\PaymentMethods\Surepay;

use Buckaroo\Models\Payload\DataRequestPayload;
use Buckaroo\PaymentMethods\PaymentMethod;
use Buckaroo\PaymentMethods\Surepay\Models\Verify;

class Surepay extends PaymentMethod
{
    /**
     * @var string
     */
    protected string $paymentName = 'surepay';

    /**
     * @return Surepay|mixed
     */
    public function verify()
    {
        $verify = new Verify($this->payload);

        $this->setServiceList('verify', $verify);

        $this->request->setPayload(new DataRequestPayload($this->payload));

        return $this->dataRequest();
    }
}
