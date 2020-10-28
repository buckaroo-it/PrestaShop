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
    public static function Send($id_lang, $template, $subject, $template_vars, $to, $to_name = null, $from = null, $from_name = null, $file_attachment = null, $mode_smtp = null, $template_path = _PS_MAIL_DIR_, $die = false, $id_shop = null, $bcc = null, $reply_to = null)
    {

        if ($template == 'order_conf') {
            $context = Context::getContext();
            if (!empty($context->cart->id)) {
                $payment_method = Tools::getValue('method');
                if ($buckarooFee = Config::get('BUCKAROO_' . strtoupper($payment_method) . '_FEE')) {
                    $template_vars['{discounts}'] .= '<tr class="order_summary"> <td bgcolor="#FDFDFD" colspan="3" align="right" style="border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-family: Open sans, Arial, sans-serif; background-color: #FDFDFD; color: #353943; font-weight: 600; font-size: 14px; padding: 10px; border: 1px solid #DFDFDF;"> Buckaroo Fee </td> <td bgcolor="#FDFDFD" colspan="3" style="border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-family: Open sans, Arial, sans-serif; background-color: #FDFDFD; color: #353943; font-weight: 600; font-size: 14px; padding: 10px; border: 1px solid #DFDFDF;"> ' . Tools::displayPrice($buckarooFee) . ' </td> </tr>';
                }
            }
        }

        return parent::Send($id_lang, $template, $subject, $template_vars, $to, $to_name, $from, $from_name, $file_attachment, $mode_smtp, $template_path, $die, $id_shop, $bcc, $reply_to);
    }
}
