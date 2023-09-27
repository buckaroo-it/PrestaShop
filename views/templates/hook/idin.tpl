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


<div style="display: none">
<div rel="booAnimBl" id="iDINBankLinks" class="booBlAnimConts row">
    <div class="col-xs-12 col-md-12">
        <h2> {l s='Age Verification' mod='buckaroo3'}</h2>
        
        <div id="booIdealErr" class="booBlAnimError">
            {*l s='You have to choose a bank first!' mod='buckarooideal'*}
        </div>

        <form id="booIdinForm" action="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'idin'])|escape:'quotes':'UTF-8'}"
              method="post" style="padding: 10px; background: #d4eded; display: inline-block; width: 100%; ">
            <img src="{$this_path|escape:'quotes':'UTF-8'}views/images/buckaroo/Identification methods/SVG/iDIN.svg" class="middle" style="width: 70px;"/>{l s='To continue you must verify your age using iDIN' mod='buckaroo3'}
            <select name="BPE_Issuer" id="BPE_Issuer">
                <option value="ABNAMRO">{l s='ABN AMRO' mod='buckaroo3'}</option>
                <option value="INGBANK">{l s='ING' mod='buckaroo3'}</option>
                <option value="RABOBANK">{l s='Rabobank' mod='buckaroo3'}</option>
                <option value="SNSBANK">{l s='SNS Bank' mod='buckaroo3'}</option>
                <option value="ASNBANK">{l s='ASN Bank' mod='buckaroo3'}</option>
                <option value="SNSREGIO">{l s='RegioBank' mod='buckaroo3'}</option>
                <option value="BUNQ">{l s='Bunq' mod='buckaroo3'}</option>
                <option value="TRIODOS">{l s='Triodos Bank' mod='buckaroo3'}</option>
                {if $buckaroo_idin_test eq '1'}
                    <option value="BANKNL2Y">{l s='TEST BANK' mod='buckaroo3'}</option>
                {/if}
            </select>
            <div class="row row-padding">
                <button type="submit" id="booIdinSendBtn" name="processCarrier"
                        class="continue btn btn-primary float-xs-right">
                        {l s='Verify your age via iDIN' mod='buckaroo3'}
                </button>
            </div>
        </form>
    </div>
</div>
</div>

<script>
    window.addEventListener("load", function(){
        var fragment = document.createDocumentFragment();
        fragment.appendChild(document.getElementById('iDINBankLinks'));
        var cps = document.getElementById("checkout-payment-step").getElementsByClassName("content")[0];
        cps.innerHTML = '';
        cps.appendChild(fragment);
    });
</script>