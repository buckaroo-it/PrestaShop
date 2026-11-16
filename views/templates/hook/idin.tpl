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

{* The checkout's own Place Order button submits this form when iDIN is selected. *}
<section class="additional-information" id="iDINBankLinks">
    <form id="booIdinForm" action="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'idin'])|escape:'quotes':'UTF-8'}" method="post">
        <p>{l s='To continue you must verify your age using iDIN' mod='buckaroo3'}</p>
        <div id="booIdealErr" class="booBlAnimError"></div>
        <p class="form-row form-row-wide">
            <label for="BPE_Issuer">{l s='Choose your bank' mod='buckaroo3'}</label>
            <select name="BPE_Issuer" id="BPE_Issuer" class="form-control">
                <option value="ABNAMRO">{l s='ABN AMRO' mod='buckaroo3'}</option>
                <option value="INGBANK">{l s='ING' mod='buckaroo3'}</option>
                <option value="RABOBANK">{l s='Rabobank' mod='buckaroo3'}</option>
                <option value="SNSBANK">{l s='SNS Bank' mod='buckaroo3'}</option>
                <option value="ASNBANK">{l s='ASN Bank' mod='buckaroo3'}</option>
                <option value="SNSREGIO">{l s='RegioBank' mod='buckaroo3'}</option>
                <option value="BUNQ">{l s='Bunq' mod='buckaroo3'}</option>
                <option value="TRIODOS">{l s='Triodos Bank' mod='buckaroo3'}</option>
                {if $buckaroo_idin_test eq 'test'}
                    <option value="BANKNL2Y">{l s='TEST BANK' mod='buckaroo3'}</option>
                {/if}
            </select>
        </p>
    </form>
</section>
