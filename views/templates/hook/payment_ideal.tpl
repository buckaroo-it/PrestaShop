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
<section class="additional-information">
    <input type="hidden" name="buckarooKey" value="ideal">
    <form id="bk-ideal-form" {if !$showIdealIssuers}class="noIdealIssuers"{/if} action="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'ideal'])|escape:'quotes':'UTF-8'}" method="post">
        <div id="booIdealErr" style=" display:none" class="booBlAnimError">
            {l s='Please choose your bank.' mod='buckaroo3'}"
        </div>
    {if $showIdealIssuers}
       <p> {l s='Choose your bank' mod='buckaroo3'}</p>
        <fieldset>
            {if $idealDisplayMode === 'dropdown'}
                <p class="form-row form-row-wide">
                    <select name="BPE_Issuer" id="buckaroo-method-issuer" class="ideal_issuer ideal_dropdown">
                        <option value="0" style="color: grey !important">
                            <p> {l s='Select your bank' mod='buckaroo3'}</p>
                        </option>
                        {foreach $idealIssuers as $key => $issuer}
                            <div>
                                <option value="{$key|escape:'html':'UTF-8'}"
                                        id="bankMethod{$key|escape:'html':'UTF-8'}">
                                    {l s=$issuer['name'] mod='buckaroo3'}
                                </option>
                            </div>
                        {/foreach}
                    </select>
                </p>
            {else}
                <div class="bk-method-selector bk-ideal-selector">
                    {foreach $idealIssuers as $key => $issuer}
                        <div rel="booRow" class="bk-method-issuer ideal_radio">
                            <input
                                    class="ideal_issuer"
                                    name="BPE_Issuer"
                                    id="ideal_issuer_{$key|escape:'html':'UTF-8'}"
                                    value="{$key|escape:'html':'UTF-8'}"
                                    type="radio"
                            />
                            <label for="ideal_issuer_{$key|escape:'html':'UTF-8'}" class="bk-issuer-label">
                                {if isset($issuer['logo']) && $issuer['logo'] !== null}
                                    <img
                                            class=""
                                            alt="{l s=$issuer['name'] mod='buckaroo3'}"
                                            title="{l s=$issuer['name'] mod='buckaroo3'}"
                                            src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo/iDEAL bank issuers/SVG/{$issuer['logo']|escape:'html':'UTF-8'}"
                                    />
                                {/if}
                                <strong>{l s=$issuer['name'] mod='buckaroo3'}</strong>
                            </label>
                        </div>
                    {/foreach}
                </div>
            {/if}
        </fieldset>
        {/if}
    </form>
</section>
