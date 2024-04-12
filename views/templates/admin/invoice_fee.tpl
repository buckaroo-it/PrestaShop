{*
*
 *
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * It is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * @author Buckaroo.nl <plugins@buckaroo.nl>
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   http://opensource.org/licenses/afl-3.0 Academic Free License (AFL 3.0)
*}
<table width="100%" id="body" border="0" cellpadding="0" cellspacing="0" style="margin:0;">
    <tr>
        <td colspan="6" class="left">
        </td>

        <td colspan="6" rowspan="6" class="right">
            <table id="payment-tab" width="100%" class="right">
                <tr class="bold">
                    <td class="grey" width="50%">
                        {l s='Payment Fee' mod='buckaroo3'}
                    </td>
                    <td class="white" width="50%">
                        {$order_buckaroo_fee|escape:'html':'UTF-8'}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
