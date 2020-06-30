{*
*
 * 2014-2015 Buckaroo.nl
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * It is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * @author Buckaroo.nl <plugins@buckaroo.nl>
 * @copyright 2014-2015 Buckaroo.nl
 * @license   http://opensource.org/licenses/afl-3.0 Academic Free License (AFL 3.0)
*}
{capture name=path}{l s='Order confirmation' mod='buckaroo3'}{/capture}

<h1>{l s='Order confirmation' mod='buckaroo3'}</h1>

{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}

{include file="$tpl_dir./errors.tpl"}

{l s='Your order  is complete.' mod='buckaroo3'}
<br/>
{l s='You have chosen the' mod='buckaroo3'} {$order->payment|escape:'html':'UTF-8'} {l s='payment method.' mod='buckaroo3'}
<br/>
{l s='Your order will be sent very soon.' mod='buckaroo3'}
<br/><br/>
{l s='For any questions or for further information, please contact our customer support.' mod='buckaroo3'}
<br/>

{if $order}
    <p>{l s='Total of the transaction (taxes incl.) :' mod='buckaroo3'} <span class="bold">{$price|escape:'html':'UTF-8'}</span></p>
    <p>{l s='Your order reference ID is :' mod='buckaroo3'} <span class="bold">{$order->reference|escape:'html':'UTF-8'}</span></p>
{/if}
<br/>

{if $is_guest}
    <a href="{$link->getPageLink('guest-tracking.php', true)|escape:'quotes':'UTF-8'}?id_order={$order_reference|escape:'quotes':'UTF-8'}"
       title="{l s='Follow my order' mod='buckaroo3'}" data-ajax="false"
       class="button btn btn-default standart-checkout button-medium"><span>{l s='Follow my order' mod='buckaroo3'}</span></a>
{else}
    <a href="{$link->getPageLink('history.php', true)|escape:'quotes':'UTF-8'}" title="{l s='Back to orders' mod='buckaroo3'}"
       data-ajax="false"
       class="button btn btn-default standart-checkout button-medium"><span>{l s='Back to orders' mod='buckaroo3'}</span></a>
{/if}
