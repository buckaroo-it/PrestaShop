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
 * @copyright 2014-2015 Buckaroo.nl
 * @license   http://opensource.org/licenses/afl-3.0 Academic Free License (AFL 3.0)
*}
{capture name=path}{l s='Order confirmation' mod='buckaroo3'}{/capture}
{include file="$tpl_dir./breadcrumb.tpl"}

<h1>{l s='Order confirmation' mod='buckaroo3'}</h1>

{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}

{include file="$tpl_dir./errors.tpl"}

{l s='Your order  is complete.' mod='buckaroo3'}
<br/>
{if $order}
    {l s='You have chosen to pay by bank transfer.' mod='buckaroo3'}
    <br/>
    <br/>
{/if}
{$message|escape:'htmlall':'UTF-8'}
<br/>
{l s='For any questions or for further information, please contact our customer support.' mod='buckaroo3'}
<br/>

{if $order}
    <p>{l s='Your order reference ID is :' mod='buckaroo3'} <span class="bold">{$order->reference|escape:'html':'UTF-8'}</span></p>
{/if}
<br/>

{if $is_guest}
    <a href="{$link->getPageLink('guest-tracking.php', true)}?id_order={$order_reference|escape:'html':'UTF-8'}"
       title="{l s='Follow my order' mod='buckaroo3'}" data-ajax="false"><img src="{$img_dir|escape:'html':'UTF-8'}icon/order.gif"
                                                                              alt="{l s='Follow my order' mod='buckaroo3'}"
                                                                              class="icon"/></a>
    <a href="{$link->getPageLink('guest-tracking.php', true)}?id_order={$order_reference|escape:'html':'UTF-8'}"
       title="{l s='Follow my order' mod='buckaroo3'}" data-ajax="false">{l s='Follow my order' mod='buckaroo3'}</a>
{else}
    <a href="{$link->getPageLink('history.php', true)|escape:'quotes':'UTF-8'}" title="{l s='Back to orders' mod='buckaroo3'}"
       data-ajax="false"><img src="{$img_dir|escape:'quotes':'UTF-8'}icon/order.gif" alt="{l s='Back to orders' mod='buckaroo3'}" class="icon"/></a>
    <a href="{$link->getPageLink('history.php', true)|escape:'quotes':'UTF-8'}" title="{l s='Back to orders' mod='buckaroo3'}"
       data-ajax="false">{l s='Back to orders' mod='buckaroo3'}</a>
{/if}
