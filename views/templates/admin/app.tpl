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
{if isset($errorReportingPrompt) && $errorReportingPrompt}
    {include file='module:buckaroo3/views/templates/admin/error_reporting_prompt.tpl'}
{/if}
<style>
    /*
     * On phones PrestaShop's fixed toolbar is taller than the space it reserves:
     * the subtitle overlaps the buttons and the buttons spill over the app.
     */
    @media (max-width: 767px) {
        .page-head .page-subtitle { display: none; }
        #app { margin-top: 2.5rem; }
    }
</style>
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