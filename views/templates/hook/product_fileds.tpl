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

<div class="m-b-1 m-t-1">
    <h2>{l s='Buckaroo' mod='buckaroo3'}</h2>
    <fieldset class="form-group">
        <div class="col-lg-12 col-xl-4">
            <label class="form-control-label">{l s='iDIN verify' mod='buckaroo3'}</label>
            <select name="buckaroo_idin" id="buckaroo_idin" data-toggle="select2" data-minimumResultsForSearch="7" class="feature-selector custom-select">
                <option value="0">{l s='Disabled' mod='buckaroo3'}</option>
                <option value="1" {if $buckaroo_idin}selected{/if}>{l s='Enabled' mod='buckaroo3'}</option>
            </select>
        </div>
    </fieldset>
    <div class="clearfix"></div>
</div>