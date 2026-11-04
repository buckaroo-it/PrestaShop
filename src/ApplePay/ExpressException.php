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

namespace Buckaroo\PrestaShop\Src\ApplePay;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * An Apple Pay express step that cannot continue. The message is translated
 * and safe to show to the shopper; the Apple Pay field tells the sheet which
 * contact field to highlight.
 */
class ExpressException extends \Exception
{
    public const FIELD_NONE = '';

    /** @var string Apple Pay contact field (country, postalAddress, ...) */
    private $contactField;

    public function __construct(string $message, string $contactField = self::FIELD_NONE)
    {
        parent::__construct($message);
        $this->contactField = $contactField;
    }

    public function getContactField(): string
    {
        return $this->contactField;
    }
}
