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
{if !empty($top_error)}
    <div class="error">{$top_error|escape:'html':'UTF-8'}</div>
{/if}
<form action="{$form_action|escape:'quotes':'UTF-8'}" method="post" class="clear" id="buckaroo3settings_form" method="post"
      enctype="multipart/form-data">
    {foreach from=$fields_form item=fieldset}
        <fieldset id="fieldset_{$fieldset.name|escape:'quotes':'UTF-8'}">
            <legend class="{if $fieldset.enabled}{if $fieldset.test}test{else}active{/if}{/if}">
                <img src="../img/admin/contact.gif" alt="{l s='Global Settings' mod='buckaroo3'}"/>{$fieldset.legend|escape:'html':'UTF-8'}
            </legend>
            {if $fieldset.name == 'GLOBAL'}
                <div style="margin: 0 0 10px 0; padding: 5px;">
                    <b>Welcome to Buckaroo Payment Engine</b><br>
                    Contact Buckaroo for any questions regarding your account. <b>Phone number</b>: +31 (0)30 711 50 00 <b>E-mail</b>: info@buckaroo.nl
                </div>
                {foreach from=$fieldset.input item=input}
                    {include file="$dir/admin.input.tpl" input=$input}
                {/foreach}
            {else}
                {foreach from=$fieldset.input item=input}
                    {include file="$dir/admin.input.tpl" input=$input enabled=$fieldset.enabled}
                {/foreach}
            {/if}
            <div class="small">
                <sup>*</sup> {l s='Required field' mod='buckaroo3'}
            </div>
        </fieldset>
        <br/>
    {/foreach}
</form>
