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

{extends file='page.tpl'}
{block name='head_seo' append}
    {if $buckaroo_pending_refresh_url}
        <meta http-equiv="refresh" content="{$buckaroo_pending_refresh_seconds|intval};url={$buckaroo_pending_refresh_url|escape:'html':'UTF-8'}">
    {/if}
{/block}
{block name='page_content'}
    <div class="container js-buckaroo-payment-pending">
        <article class="alert alert-info" role="status" data-alert="info">
            <p>{$buckaroo_pending_message|escape:'html':'UTF-8'}</p>
            {if $buckaroo_pending_refresh_url}
                <p class="mb-0">{l s='This page refreshes automatically.' mod='buckaroo3'}</p>
            {/if}
        </article>
    </div>
{/block}
