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
<div class="row">
    <div class="col-lg-3">
        <div class="panel">
            <div class="panel-heading">
                Log files
            </div>
            {if $files}
                <div class="list-group">
                    {foreach from=$files item=file}
                        <a href="{$file.url|escape:'html':'UTF-8'}" class="list-group-item{if $file.name == $selected_file} active{/if}">
                            <strong>{$file.channel|escape:'html':'UTF-8'}</strong>
                            {if $file.date}{$file.date|escape:'html':'UTF-8'}{/if}
                            <span class="badge">{$file.size_label|escape:'html':'UTF-8'}</span>
                        </a>
                    {/foreach}
                </div>
            {else}
                <p>No log files found in {$log_directory|escape:'html':'UTF-8'}</p>
            {/if}
        </div>
    </div>
    <div class="col-lg-9">
        <div class="panel">
            <div class="panel-heading">
                {if $selected_file}{$selected_file|escape:'html':'UTF-8'}{else}Buckaroo log{/if}
            </div>
            <div class="btn-group" role="group" style="margin-bottom: 10px;">
                {foreach from=$levels item=option}
                    <a href="{$option.url|escape:'html':'UTF-8'}" class="btn btn-default{if $option.value == $selected_level} active{/if}">{$option.label|escape:'html':'UTF-8'}</a>
                {/foreach}
            </div>
            {if $truncated}
                <div class="alert alert-info">This file is large; only its most recent entries are shown.</div>
            {/if}
            <div class="table-responsive clearfix">
                <table class="table">
                    <thead>
                    <tr class="nodrag nodrop">
                        <th class="fixed-width-lg">Date</th>
                        <th class="fixed-width-sm">Level</th>
                        <th>Message</th>
                    </tr>
                    </thead>
                    <tbody>
                    {foreach from=$entries item=entry}
                        <tr>
                            <td>{$entry.time|escape:'html':'UTF-8'}</td>
                            <td>
                                <span class="label {if $entry.level == 'Error'}label-danger{elseif $entry.level == 'Warning'}label-warning{elseif $entry.level == 'Info'}label-info{else}label-default{/if}">{$entry.level|escape:'html':'UTF-8'}</span>
                            </td>
                            <td>
                                {$entry.message|escape:'html':'UTF-8'}
                                {if $entry.details}
                                    <pre style="margin-top: 5px; max-height: 300px; overflow: auto;">{$entry.details|escape:'html':'UTF-8'}</pre>
                                {/if}
                            </td>
                        </tr>
                    {foreachelse}
                        <tr>
                            <td colspan="3">No data</td>
                        </tr>
                    {/foreach}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
