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
{capture name=path}{l s='Order Processing' mod='buckaroo3'}{/capture}
<h2>{l s='Order Processing Error' mod='buckaroo3'}</h2>
<p class="error">{$error_message|escape:'html':'UTF-8'}</p>
<br/>
<p>
    {if isset($order_id)}
        <a href="index.php?controller=order&submitReorder=&id_order={$order_id|escape:'html':'UTF-8'}"
           class="button btn btn-default standart-checkout button-medium"><span>{l s='Return to checkout' mod='buckaroo3'}</span></a>
    {else}
        <a href="index.php?controller=order"
           class="button btn btn-default standart-checkout button-medium"><span>{l s='Return to checkout' mod='buckaroo3'}</span></a>
    {/if}
</p>

