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
<div id="app">
    <Dashboard
            token="{$token|escape:'htmlall':'UTF-8'}"
            base-url="{$baseUrl|escape:'htmlall':'UTF-8'}"
            admin-url="{$adminUrl|escape:'htmlall':'UTF-8'}">
    </Dashboard>
</div>
{if isset($moduleVersion)}
<p class="text-muted small buckaroo-module-version">
    {l s='Buckaroo Payments module version' mod='buckaroo3'} {$moduleVersion|escape:'htmlall':'UTF-8'}
</p>
{/if}
<script type="module" src="{$pathApp|escape:'htmlall':'UTF-8'}"></script>