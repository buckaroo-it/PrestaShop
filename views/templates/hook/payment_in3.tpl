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
    <input type="hidden" name="buckarooKey" value="in3">
    <form name="booIn3Form" id="booIn3Form"
          action="{$link->getModuleLink('buckaroo3', 'request', ['method' => {$in3Method|escape:'html':'UTF-8'}])|escape:'quotes':'UTF-8'}" method="post" class="mb-2">

        {* Resolve which phone value is available from the address *}
        {if !empty($phone)}
            {assign var='bk_in3_phone' value=$phone}
        {elseif !empty($phone_mobile)}
            {assign var='bk_in3_phone' value=$phone_mobile}
        {else}
            {assign var='bk_in3_phone' value=''}
        {/if}

        {*
         * Phone row wrapper — hidden when the address already supplies a phone.
         * The JS updater (fetchAndUpdateBnplPhoneFields) can re-show this wrapper and
         * flip the input type back to "text" if the customer removes the phone from
         * their address, and vice-versa.
         *}
        <div id="bk-in3-phone-row" class="bk-bnpl-phone-row"{if !empty($bk_in3_phone)} style="display:none"{/if}>
            <div id="booIn3Err" class="booBlAnimError">
                {l s='Phone number is required' mod='buckaroo3'}
            </div>
            <div class="row row-padding">
                <div class="col-xs-3">
                    <label class="required">{l s='Phone number' mod='buckaroo3'}:</label>
                </div>
                <div class="col-xs-9">
                    {*
                     * Single input — type is "hidden" (with the address phone value) when the
                     * address already has a phone so the value is passed to the backend without
                     * bothering the customer; type is "text" when the address has no phone so
                     * the customer can fill it in themselves.
                     *}
                    <input name="customer_phone"
                           id="customer_phone"
                           type="{if !empty($bk_in3_phone)}hidden{else}text{/if}"
                           value="{$bk_in3_phone|escape:'html':'UTF-8'}"
                           class="form-control bk-form-control-large"/>
                </div>
            </div>
        </div>
        <div class="row row-padding" style="margin: 25px 0 0 0">
            <div class="col-xs-1">
                <span class="custom-checkbox">
                    <input id="bpe_in3_accept" name="bpe_in3_accept" required="" type="checkbox" value="ON">
                    <span>
                        <i class="material-icons checkbox-checked">&#xE5CA;</i>
                    </span>
                </span>
            </div>
            <div class="col-xs-11">
                <label class="required" for="bpe_in3_accept" style="display: inline">
                    <a href="https://www.in3.nl/voorwaarden/" target="_blank" style="text-decoration: underline">
                        {l s='Ik accepteer de Algemene Voorwaarden, Privacyverklaring en Cookieverklaring van in3.' mod='buckaroo3'}
                    </a>
                </label>
            </div>
        </div>

    </form>
</section>
