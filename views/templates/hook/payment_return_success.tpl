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
<h1>{l s='Your order  is complete.' mod='buckaroo3'}</h1>
<br/>
{l s='You have chosen the' mod='buckaroo3'} {$order->payment|escape:'htmlall':'UTF-8'} {l s='payment method.' mod='buckaroo3'}
<br/>
{l s='Your order will be sent very soon.' mod='buckaroo3'}
<br/><br/>
{l s='For any questions or for further information, please contact our customer support.' mod='buckaroo3'}
<br/>

{if $order}
    <p>{l s='Total of the transaction (taxes incl.) :' mod='buckaroo3'} <span class="bold">{$price|escape:'htmlall':'UTF-8'}</span></p>
    <p>{l s='Your order reference ID is :' mod='buckaroo3'} <span class="bold">{$order->reference|escape:'htmlall':'UTF-8'}</span></p>
{/if}

