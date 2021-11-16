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

/**
 * Class MailCore.
 */
class Mail extends MailCore
{
    public static function send(
        $idLang,
        $template,
        $subject,
        $templateVars,
        $to,
        $toName = null,
        $from = null,
        $fromName = null,
        $fileAttachment = null,
        $mode_smtp = null,
        $templatePath = _PS_MAIL_DIR_,
        $die = false,
        $idShop = null,
        $bcc = null,
        $replyTo = null,
        $replyToName = null
    ) {
        if ($template == 'order_conf') {
            $context = Context::getContext();
            if (!empty($context->cart->id)) {
                $payment_method = Tools::getValue('method');
                if ($buckarooFee = Config::get('BUCKAROO_' . Tools::strtoupper($payment_method) . '_FEE')) {
                    // @codingStandardsIgnoreStart
                    $templateVars['{discounts}'] .= '<tr class="order_summary"> <td bgcolor="#FDFDFD" colspan="3" align="right" style="border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-family: Open sans, Arial, sans-serif; background-color: #FDFDFD; color: #353943; font-weight: 600; font-size: 14px; padding: 10px; border: 1px solid #DFDFDF;"> Buckaroo Fee </td> <td bgcolor="#FDFDFD" colspan="3" style="border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-family: Open sans, Arial, sans-serif; background-color: #FDFDFD; color: #353943; font-weight: 600; font-size: 14px; padding: 10px; border: 1px solid #DFDFDF;"> ' . Tools::displayPrice($buckarooFee) . ' </td> </tr>';
                    // @codingStandardsIgnoreEnd
                }
            }
        }

        return parent::Send(
            $idLang,
            $template,
            $subject,
            $templateVars,
            $to,
            $toName,
            $from,
            $fromName,
            $fileAttachment,
            $mode_smtp,
            $templatePath,
            $die,
            $idShop,
            $bcc,
            $replyTo,
            $replyToName
        );
    }
}
