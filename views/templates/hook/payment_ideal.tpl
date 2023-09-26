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
    <input type="hidden" name="buckarooKey" value="IDEAL">
    <form id="bk-ideal-form" action="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'ideal'])|escape:'quotes':'UTF-8'}" method="post">
       <p> {l s='Choose your bank' mod='buckaroo3'}</p>
        <fieldset>
            {if $idealDisplayMode === 'dropdown'}
                <p class="form-row form-row-wide">
                    <select name="BPE_Issuer" id="buckaroo-method-issuer">
                        <option value="0" style="color: grey !important">
                            <p> {l s='Select your bank' mod='buckaroo3'}</p>
                        </option>
                        {foreach $idealIssuers as $key => $issuer}
                            <div>
                                <option value="{$issuer['id']|escape:'html':'UTF-8'}"
                                        id="bankMethod{$issuer['id']|escape:'html':'UTF-8'}">
                                    {l s=$issuer['name'] mod='buckaroo3'}
                                </option>
                            </div>
                        {/foreach}
                    </select>
                </p>
            {else}
                <div class="bk-method-selector bk-ideal-selector">
                    {foreach $idealIssuers as $key => $issuer}
                        <div rel="booRow" class="bk-method-issuer">
                            <input
                                    name="BPE_Issuer"
                                    id="ideal_issuer_{$issuer['id']|escape:'html':'UTF-8'}"
                                    value="{$issuer['id']|escape:'html':'UTF-8'}"
                                    type="radio"
                            />
                            <label for="ideal_issuer_{$issuer['id']|escape:'html':'UTF-8'}" class="bk-issuer-label">
                                {if isset($issuer['logo']) && $issuer['logo'] !== null}
                                    <img
                                            class=""
                                            alt="{l s=$issuer['name'] mod='buckaroo3'}"
                                            title="{l s=$issuer['name'] mod='buckaroo3'}"
                                            src="{$this_path|escape:'quotes':'UTF-8'}views/images/payments/ideal/{$issuer['logo']|escape:'html':'UTF-8'}"
                                    />
                                {/if}
                                <strong>{l s=$issuer['name'] mod='buckaroo3'}</strong>
                            </label>
                        </div>
                    {/foreach}
                </div>
                <div class="bk-method-toggle-list">
                    <div class="bk-toggle-wrap">
                        <div class="bk-toggle-text" text-less="{l s='Less banks' mod='buckaroo3'}" text-more="{l s='More banks' mod='buckaroo3'}">
                            {l s='More banks' mod='buckaroo3'}
                        </div>
                        <div class="bk-toggle bk-toggle-down"></div>
                    </div>
                </div>
            {/if}
        </fieldset>
    </form>
</section>