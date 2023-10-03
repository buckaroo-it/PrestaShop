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
require_once dirname(__FILE__) . '/../config.php';

abstract class BuckarooAbstract
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
        190 => [
            'message' => 'Success',
            'status' => self::BUCKAROO_SUCCESS,
        ],
        490 => [
            'message' => 'Payment failure',
            'status' => self::BUCKAROO_FAILED,
        ],
        491 => [
            'message' => 'Validation error',
            'status' => self::BUCKAROO_FAILED,
        ],
        492 => [
            'message' => 'Technical error',
            'status' => self::BUCKAROO_ERROR,
        ],
        690 => [
            'message' => 'Payment rejected',
            'status' => self::BUCKAROO_FAILED,
        ],
        790 => [
            'message' => 'Waiting for user input',
            'status' => self::BUCKAROO_PENDING_PAYMENT,
        ],
        791 => [
            'message' => 'Waiting for processor',
            'status' => self::BUCKAROO_PENDING_PAYMENT,
        ],
        792 => [
            'message' => 'Waiting on consumer action',
            'status' => self::BUCKAROO_PENDING_PAYMENT,
        ],
        793 => [
            'message' => 'Payment on hold',
            'status' => self::BUCKAROO_PENDING_PAYMENT,
        ],
        890 => [
            'message' => 'Cancelled by consumer',
            'status' => self::BUCKAROO_CANCELED,
        ],
        891 => [
            'message' => 'Cancelled by merchant',
            'status' => self::BUCKAROO_FAILED,
        ],
    ];
}
