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

use Buckaroo\Resources\Constants\ResponseStatus;

abstract class BuckarooAbstract extends ResponseStatus
{
    public const BUCKAROO_SUCCESS = 'BUCKAROO_SUCCESS';
    public const BUCKAROO_FAILED = 'BUCKAROO_FAILED';
    public const BUCKAROO_CANCELED = 'BUCKAROO_CANCELED';
    public const BUCKAROO_ERROR = 'BUCKAROO_ERROR';
    public const BUCKAROO_NEUTRAL = 'BUCKAROO_NEUTRAL';
    public const BUCKAROO_PENDING_PAYMENT = 'BUCKAROO_PENDING_PAYMENT';
    public const BUCKAROO_INCORRECT_PAYMENT = 'BUCKAROO_INCORRECT_PAYMENT';
    public const REQUEST_ERROR = 'REQUEST_ERROR';

    /**
     *  List of possible response codes sent by buckaroo.
     *  This is the list for the BPE 3.0 gateway.
     */
    public $responseCodes = [
        self::BUCKAROO_STATUSCODE_SUCCESS => [
            'message' => 'Success',
            'status' => self::BUCKAROO_SUCCESS,
        ],
        self::BUCKAROO_STATUSCODE_FAILED => [
            'message' => 'Payment failure',
            'status' => self::BUCKAROO_FAILED,
        ],
        self::BUCKAROO_STATUSCODE_VALIDATION_FAILURE => [
            'message' => 'Validation error',
            'status' => self::BUCKAROO_FAILED,
        ],
        self::BUCKAROO_STATUSCODE_TECHNICAL_ERROR => [
            'message' => 'Technical error',
            'status' => self::BUCKAROO_ERROR,
        ],
        self::BUCKAROO_STATUSCODE_REJECTED => [
            'message' => 'Payment rejected',
            'status' => self::BUCKAROO_FAILED,
        ],
        self::BUCKAROO_STATUSCODE_WAITING_ON_USER_INPUT => [
            'message' => 'Waiting for user input',
            'status' => self::BUCKAROO_PENDING_PAYMENT,
        ],
        self::BUCKAROO_STATUSCODE_PENDING_PROCESSING => [
            'message' => 'Waiting for processor',
            'status' => self::BUCKAROO_PENDING_PAYMENT,
        ],
        self::BUCKAROO_STATUSCODE_WAITING_ON_CONSUMER => [
            'message' => 'Waiting on consumer action',
            'status' => self::BUCKAROO_PENDING_PAYMENT,
        ],
        self::BUCKAROO_STATUSCODE_PAYMENT_ON_HOLD => [
            'message' => 'Payment on hold',
            'status' => self::BUCKAROO_PENDING_PAYMENT,
        ],
        self::BUCKAROO_STATUSCODE_CANCELLED_BY_USER => [
            'message' => 'Cancelled by consumer',
            'status' => self::BUCKAROO_CANCELED,
        ],
        self::BUCKAROO_STATUSCODE_CANCELLED_BY_MERCHANT => [
            'message' => 'Cancelled by merchant',
            'status' => self::BUCKAROO_FAILED,
        ],
    ];
}
