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
    <input type="hidden" name="buckarooKey" value="creditcard">
    <form id="booCreditCardForm" action="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'creditcard'])|escape:'quotes':'UTF-8'}" method="post">
        <div id="booCreditCardErr" class="booBlAnimError">
            {l s='Please choose your bank.' mod='buckaroo3'}"
        </div>
        <fieldset>
            {if $creditCardDisplayMode === 'dropdown'}
                <p class="form-row form-row-wide">
                    <select name="BPE_CreditCard" id="buckaroo-method-issuer" class="form-control creditcard_banks creditcard_dropdown" >
                        <option value="0" style="color: grey !important">
                            <p> {l s='Select your bank' mod='buckaroo3'}</p>
                        </option>
                        {foreach $creditcardIssuers as $key => $issuer}
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
                <div class="bk-method-selector bk-creditcard-selector">
                    {foreach $creditcardIssuers as $key => $issuer}
                        <div rel="booRow" class="bk-method-issuer creditcard_radio">
                            <input
                                    class="creditcard_banks"
                                    name="BPE_CreditCard"
                                    id="creditcard_issuer_{$key|escape:'html':'UTF-8'}"
                                    value="{$key|escape:'html':'UTF-8'}"
                                    type="radio"
                            />
                            <label for="creditcard_issuer_{$key|escape:'html':'UTF-8'}" class="bk-issuer-label">
                                {if isset($issuer['logo']) && $issuer['logo'] !== null}
                                    <img
                                            class=""
                                            alt="{l s=$issuer['name'] mod='buckaroo3'}"
                                            title="{l s=$issuer['name'] mod='buckaroo3'}"
                                            src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo/Creditcard issuers/SVG/{$issuer['logo']|escape:'html':'UTF-8'}"
                                    />
                                {/if}
                                <strong>{l s=$issuer['name'] mod='buckaroo3'}</strong>
                            </label>
                        </div>
                    {/foreach}
                </div>
            {/if}
        </fieldset>
    </form>
</section>
