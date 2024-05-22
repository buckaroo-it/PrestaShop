{*
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
{if $order_buckaroo_fee}
    <tr>
        <td colspan="4" class="right">{l s='Payment Fee' mod='buckaroo3'}:</td>
        <td colspan="2" class="right">{$order_buckaroo_fee|escape:'html':'UTF-8'}</td>
    </tr>
{/if}
