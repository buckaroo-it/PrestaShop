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
{if $input.type == 'mode'}
    <label for="{$input.name|escape:'quotes':'UTF-8'}">{l s='Mode' mod='buckaroo3'}</label>
    <div class="margin-form">
        <select id="{$input.name|escape:'quotes':'UTF-8'}" class="mode" name="{$input.name|escape:'quotes':'UTF-8'}">
            <option value="0" {if $fields_value[$input.name] == 0}selected{/if}>{l s='Live' mod='buckaroo3'}</option>
            <option value="1" {if $fields_value[$input.name] == 1}selected{/if}>{l s='Test' mod='buckaroo3'}</option>
        </select>
    </div>
{elseif $input.type == 'enabled'}
    <label for="{$input.name|escape:'quotes':'UTF-8'}">{if !empty($input.label)} {$input.label|escape:'html':'UTF-8'}{else} Enabled{/if}</label>
    <div class="margin-form">
        <select id="{$input.name|escape:'quotes':'UTF-8'}" class="enabledisable" name="{$input.name|escape:'quotes':'UTF-8'}">
            <option value="0"
                    {if empty($fields_value[$input.name])}selected="selected"{/if}>{l s='No' mod='buckaroo3'}</option>
            <option value="1"
                    {if !empty($fields_value[$input.name]) && ($fields_value[$input.name] == 1)}selected="selected"{/if}>{l s='Yes' mod='buckaroo3'}</option>
        </select>
        {if isset($input.description)}
            <div class="buckaroo-field-description">{$input.description}</div>
        {/if}
    </div>
{elseif $input.type == 'submit'}
    <label>&nbsp;</label>
    <div class="margin-form">
        <input id="{$input.name|escape:'quotes':'UTF-8'}" name="{$input.name|escape:'quotes':'UTF-8'}" {if isset($input.class)}class="{$input.class|escape:'quotes':'UTF-8'}"{/if} type="submit"
               value=" {$input.label|escape:'html':'UTF-8'} "/>
    </div>
{elseif $input.type == 'multiselect'}
    <label for="{$input.name|escape:'html':'UTF-8'}">{$input.label|escape:'html':'UTF-8'}</label>
    <div class="margin-form">
        <select id="{$input.name|escape:'html':'UTF-8'}" class="" name="{$input.name|escape:'quotes':'UTF-8'}[]" multiple="multiple"
                style="{if isset($input.height)}height:{$input.height|escape:'quotes':'UTF-8'}px;{/if} width:200px">
            {foreach from=$input.options item=option}
                <option value="{$option.value|escape:'quotes':'UTF-8'}"
                        {if isset($fields_value[$input.name][$option.value])}selected="selected"{/if}>{$option.text|escape:'html':'UTF-8'}</option>
            {/foreach}
        </select>
        {if isset($input.description)}
           <div class="buckaroo-field-description">{$input.description}</div>
        {/if}
    </div>
{elseif $input.type == 'select'}
    <label for="{$input.name|escape:'quotes':'UTF-8'}">{$input.label|escape:'html':'UTF-8'}</label>
    <div class="margin-form">
        <select id="{$input.name|escape:'quotes':'UTF-8'}" class="" name="{$input.name|escape:'quotes':'UTF-8'}">
            {foreach from=$input.options item=option}
                <option value="{$option.value|escape:'quotes':'UTF-8'}"
                        {if isset($fields_value[$input.name]) && ($fields_value[$input.name] == $option.value)}selected="selected"{/if}>{$option.text|escape:'html':'UTF-8'}</option>
            {/foreach}
        </select>
        {if isset($input.description)}
            <div class="buckaroo-field-description">{$input.description}</div>
        {/if}
    </div>
{elseif $input.type == 'text'}
    <label for="{$input.name|escape:'quotes':'UTF-8'}">{$input.label|escape:'html':'UTF-8'}{if isset($input.required)} <sup style="color:red">*</sup>{/if}</label>
    <div class="margin-form">
        <input id="{$input.name|escape:'quotes':'UTF-8'}" name="{$input.name|escape:'quotes':'UTF-8'}" {if isset($input.class)}class="{$input.class|escape:'html':'UTF-8'}"{/if} type="text"
               size="{if $input.size}{$input.size|escape:'quotes':'UTF-8'}{else}25{/if}" value="{$fields_value[$input.name]|escape:'html':'UTF-8'}"/>
        
        {if isset($input.description)}
            <div class="buckaroo-field-description">{$input.description}</div>
        {/if}
    </div>
{elseif $input.type == 'number'}
    <label for="{$input.name|escape:'quotes':'UTF-8'}">{$input.label|escape:'html':'UTF-8'}{if isset($input.required)} <sup style="color:red">*</sup>{/if}</label>
    <div class="margin-form">
        <input id="{$input.name|escape:'quotes':'UTF-8'}" name="{$input.name|escape:'quotes':'UTF-8'}" {if isset($input.class)}class="{$input.class|escape:'html':'UTF-8'}"{/if} type="number"
               {if isset($input.step)}step="{$input.step|escape:'html':'UTF-8'}"{/if} {if isset($input.min)}min="{$input.min|escape:'html':'UTF-8'}"{/if} {if isset($input.max)}max="{$input.max|escape:'html':'UTF-8'}"{/if} value="{$fields_value[$input.name]|escape:'html':'UTF-8'}"/>
        
        {if isset($input.description)}
            <div class="buckaroo-field-description">{$input.description}</div>
        {/if}
    </div>
{elseif $input.type == 'taxrate'}
    <label>{$input.label|escape:'html':'UTF-8'}</label>
    <div class="margin-form">
        <table>
            {foreach from=$input.taxarray key=it item=option}
                <tr>
                    <td style="color: #000000">{$option|escape:'quotes':'UTF-8'}:&nbsp;</td>
                    <td><select id="{$input.name|escape:'quotes':'UTF-8'}[{$it|escape:'quotes':'UTF-8'}]" class="" name="{$input.name|escape:'quotes':'UTF-8'}[{$it|escape:'quotes':'UTF-8'}]">
                            {foreach from=$input.taxoptions item=option}
                                <option value="{$option.value|escape:'quotes':'UTF-8'}"
                                        {if (Tools::getIsset($input.taxvalues[$it]) && $input.taxvalues[$it] == $option.value)}selected="selected"{/if}>{$option.text|escape:'html':'UTF-8'}</option>
                            {/foreach}
                        </select></td>
                </tr>
            {/foreach}
        </table>
    </div>
{elseif $input.type == 'simpletext'}
    <div class="margin-form">
        {$input.name|escape:'html':'UTF-8'}
    </div>
{elseif $input.type == 'hidearea_start'}
    <div class='hidable {if empty($enabled)}disabled{/if}'>
        {elseif $input.type == 'hidearea_end'}
    </div>
{/if}

{if !isset($input.type) || !in_array($input.type,array('hidearea_start','hidearea_end'))}
    <div class="clear"></div>
{/if}