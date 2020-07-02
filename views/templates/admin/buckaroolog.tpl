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
<div class="panel col-lg-12">
    <div class="panel-heading">
        Buckaroo Error Log
    </div>
    <div class="table-responsive clearfix">
        <table class="table  tax">
            <thead>
            <tr class="nodrag nodrop">
                <th class="fixed-width-xs center">ID</th>
                <th class="fixed-width-lg center">Date</th>
                <th class="">Error description</th>
            </tr>
            </thead>
            <tbody>
            {foreach from=$data item=option}
                <tr class="odd">
                    <td>{$option.id|escape:'html':'UTF-8'}</td>
                    <td>{$option.time|escape:'html':'UTF-8'}</td>
                    <td>{$option.value|escape:'html':'UTF-8'}</td>
                </tr>
                {foreachelse}
                <tr class="odd">
                    <td colspan="3">No data</td>
                </tr>
            {/foreach}
            </tbody>
        </table>
    </div>
</div>