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
    <input type="hidden" name="buckarooKey" value="paybybank">
    <form id="bk-paybybank-form" action="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'paybybank'])|escape:'quotes':'UTF-8'}" method="post">
        <p> {l s='Choose your bank' mod='buckaroo3'}</p>

        <fieldset>
            {if $payByBankDisplayMode === 'dropdown'}
                <p class="form-row form-row-wide">
                    <select name="BPE_Issuer" id="buckaroo-method-issuer">
                        <option value="0" style="color: grey !important">
                            <p> {l s='Select your bank' mod='buckaroo3'}</p>
                        </option>
                        {foreach $paybybankIssuers as $key => $issuer}
                            <div>
                                <option value="{$key|escape:'html':'UTF-8'}"
                                        {if (isset($issuer["selected"]) && $issuer["selected"] === true)}
                                            selected
                                        {/if}
                                        id="bankMethod{$key|escape:'html':'UTF-8'}">
                                    {l s=$issuer['name'] mod='buckaroo3'}
                                </option>
                            </div>
                        {/foreach}
                    </select>
                </p>
            {else}
                <p class="form-row form-row-wide bk-paybybank-mobile" style="display: none;">
                    <select id="buckaroo-method-issuer" class="form-control">
                        <option value="0" style="color: grey !important">
                            <p> {l s='Select your bank' mod='buckaroo3'}</p>
                        </option>
                        {foreach $paybybankIssuers as $key => $issuer}
                            <div>
                                <option value="{$key|escape:'html':'UTF-8'}"
                                        {if (isset($issuer["selected"]) && $issuer["selected"] === true)}
                                            selected
                                        {/if}
                                        id="bankMethod{$key|escape:'html':'UTF-8'}">
                                    {l s=$issuer['name'] mod='buckaroo3'}
                                </option>
                            </div>
                        {/foreach}
                    </select>
                </p>
                <div class="bk-paybybank-not-mobile">
                    <div class="bk-method-selector bk-paybybank-selector">
                        {foreach $paybybankIssuers as $key => $issuer}
                            <div rel="booRow" class="bk-method-issuer">
                                <input
                                        name="BPE_Issuer"
                                        id="paybybank_issuer_{$key|escape:'html':'UTF-8'}"
                                        value="{$key|escape:'html':'UTF-8'}"
                                        type="radio"
                                        {if (isset($issuer["selected"]) && $issuer["selected"] === true)}
                                            checked
                                        {/if}
                                />
                                <label for="paybybank_issuer_{$key|escape:'html':'UTF-8'}" class="bk-issuer-label">
                                    {if isset($issuer['logo']) && $issuer['logo'] !== null}
                                        <img
                                                class=""
                                                alt="{l s=$issuer['name'] mod='buckaroo3'}"
                                                title="{l s=$issuer['name'] mod='buckaroo3'}"
                                                src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo/PayByBank issuers/{$issuer['logo']|escape:'url':'UTF-8'}"
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
                </div>
            {/if}
        </fieldset>
    </form>
</section>