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
{if $status == 'ok'}
    <p>{l s='Your order on %s is complete.' sprintf=$shop_name mod='buckaroo3'}

    </p>
{else}
    <p class="warning">
        {l s='We noticed a problem with your order. If you think this is an error, you can contact our' mod='buckaroo3'}
        <a href="{$link->getPageLink('contact', true)|escape:'quotes':'UTF-8'}">{l s='customer support' mod='buckaroo3'}</a>.
    </p>
{/if}
