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
    <input type="hidden" name="buckarooKey" value="afterpay">
    <form class="mb-1" name="booAfterPayForm_digi" id="booAfterPayForm_digi"
          action="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'afterpay', 'service' => 'digi'])|escape:'quotes':'UTF-8'}"
          method="post">
        {if (isset($houseNumbersAreValid['billing']) && $houseNumbersAreValid['billing'] === false)}
            <div class="alert alert-danger" role="alert" data-alert="danger">
                {l s='Invalid billing address, cannot find house number' mod='buckaroo3'}
            </div>
        {/if}

        {if (isset($houseNumbersAreValid['shipping']) && $houseNumbersAreValid['shipping'] === false)}
            <div class="alert alert-danger" role="alert" data-alert="danger">
                {l s='Invalid shipping address, cannot find house number' mod='buckaroo3'}
            </div>
        {/if}
        {*
         * Phone row wrapper — hidden when the billing address already supplies a phone.
         * The JS updater (fetchAndUpdateBnplPhoneFields) can toggle this at runtime if
         * the customer navigates back and changes the phone in their address.
         *}
        <div id="bk-afterpay-billing-phone-row" class="bk-bnpl-phone-row"{if !empty($phone_afterpay_billing)} style="display:none"{/if}>
            <div class="row row-padding">
                <div class="col-sm-5">
                    <label for="phone_afterpay_billing_digi"
                           class="required">
                        {l s='Invoice person phone number' mod='buckaroo3'}:
                    </label>
                </div>
                <div class="col-sm-7">
                    {*
                     * Single input — type "hidden" with the address phone when already provided,
                     * type "text" when the customer must supply it themselves.
                     *}
                    <input id="phone_afterpay_billing_digi"
                           name="phone_afterpay_billing"
                           type="{if !empty($phone_afterpay_billing)}hidden{else}text{/if}"
                           class="form-control bk-form-control-large"
                           value="{$phone_afterpay_billing|escape:'html':'UTF-8'}"
                    >
                </div>
            </div>
        </div>
        


        <div class="row row-padding">
            <div class="col-xs-5">
                <label class="required">
                    {l s='Date of birth' mod='buckaroo3'}:
                </label>
            </div>
            <div class="col-xs-7" id="afterpay_digi_date">
                <input title="Day" name="customerbirthdate_d_billing" id="customerbirthdate_d_billing_digi"
                       type="text" value="{$customer_birthday[2]|escape:'html':'UTF-8'}"
                       class="form-control bk-form-control-small" style="width: 50px;"
                       autocomplete="off" maxlength="2" placeholder="{l s='DD' mod='buckaroo3'}"/>
                {l s='DD' mod='buckaroo3'}
                <input title="Month" name="customerbirthdate_m_billing" id="customerbirthdate_m_billing_digi"
                       type="text" value="{$customer_birthday[1]|escape:'html':'UTF-8'}"
                       class="form-control bk-form-control-small" style="width: 50px;"
                       autocomplete="off" maxlength="2" placeholder="{l s='MM' mod='buckaroo3'}"/>
                {l s='MM' mod='buckaroo3'}
                <input title="Year" name="customerbirthdate_y_billing" id="customerbirthdate_y_billing_digi"
                       type="text" value="{$customer_birthday[0]|escape:'html':'UTF-8'}"
                       class="form-control bk-form-control-middle" style="width: 70px;"
                       autocomplete="off" maxlength="4" placeholder="{l s='YYYY' mod='buckaroo3'}"/>
                {l s='YYYY' mod='buckaroo3'}
            </div>
        </div>
        {if $address_differ == 1}
            <input type="hidden" id="phone_afterpay_shipping_digi" name="phone_afterpay_shipping"
                   value="{$phone_afterpay_shipping|escape:'html':'UTF-8'}"/>
            <div class="row row-padding">
                <div class="col-xs-5">
                    <label class="required">
                        {l s='Date of birth (shipping)' mod='buckaroo3'}:
                    </label>
                </div>
                <div class="col-xs-7">
                    <input title="Day" name="customerbirthdate_d_shipping"
                           id="customerbirthdate_d_shipping_digi" type="text"
                           value="{$customer_birthday[2]|escape:'html':'UTF-8'}"
                           class="form-control bk-form-control-small" autocomplete="off"
                           placeholder="{l s='DD' mod='buckaroo3'}"/>
                    {l s='DD' mod='buckaroo3'}
                    <input title="Month" name="customerbirthdate_m_shipping"
                           id="customerbirthdate_m_shipping_digi" type="text"
                           value="{$customer_birthday[1]|escape:'html':'UTF-8'}"
                           class="form-control bk-form-control-small" autocomplete="off"
                           placeholder="{l s='MM' mod='buckaroo3'}"/>
                    {l s='MM' mod='buckaroo3'}
                    <input title="Year" name="customerbirthdate_y_shipping"
                           id="customerbirthdate_y_shipping_digi" type="text"
                           value="{$customer_birthday[0]|escape:'html':'UTF-8'}"
                           class="form-control bk-form-control-small" autocomplete="off"
                           placeholder="{l s='YYYY' mod='buckaroo3'}"/>
                    {l s='YYYY' mod='buckaroo3'}
                </div>
            </div>
        {/if}

        {if $country == 'FI'}
            <div class="row row-padding">
                <div class="col-xs-5">
                    <label class="required">
                        {l s='Identification Number' mod='buckaroo3'}:
                    </label>
                </div>
                <div class="col-xs-7">
                    <input title="IdentificationNumber" name="customerIdentificationNumber"
                           id="customerIdentificationNumber" type="text" value=""
                           class="form-control bk-form-control-large" autocomplete="off"/>
                </div>
            </div>
        {/if}
        {if $afterpay_show_coc}
            <div class="row row-padding">
                <div class="col-xs-5">
                    <label class="required">
                        {l s='CoC-number' mod='buckaroo3'}:
                    </label>
                </div>
                <div class="col-xs-7">
                    <input title="afterpaynew-coc" name="customerafterpaynew-coc"
                           id="customerafterpaynew-coc" type="text" value="" required
                           class="form-control bk-form-control-large" autocomplete="off"/>
                </div>
            </div>
        {/if}

        {if $country == 'NL'}
            {assign var='riverty_tc_url' value='https://documents.riverty.com/terms_conditions/payment_methods/invoice/nl_nl/'}
        {elseif $country == 'BE'}
            {assign var='riverty_tc_url' value='https://documents.riverty.com/terms_conditions/payment_methods/invoice/be_nl/'}
        {elseif $country == 'DE'}
            {assign var='riverty_tc_url' value='https://documents.riverty.com/terms_conditions/payment_methods/invoice/de_de/'}
        {elseif $country == 'AT'}
            {assign var='riverty_tc_url' value='https://documents.riverty.com/terms_conditions/payment_methods/invoice/at_de/'}
        {elseif $country == 'FI'}
            {assign var='riverty_tc_url' value='https://documents.riverty.com/terms_conditions/payment_methods/invoice/fi_fi/'}
        {else}
            {assign var='riverty_tc_url' value='https://documents.riverty.com/terms_conditions/payment_methods/invoice/'}
        {/if}
        <div class="row row-padding" style="margin: 25px 0 0 0">

            <!--div class="col-xs-12 hidden"><label class="required"></label></div-->
            <div class="col-xs-1">
                <span class="custom-checkbox">
                    <input id="bpe_afterpay_accept_digi" name="bpe_afterpay_accept" required="" type="checkbox"
                           value="ON">
                    <span>
                        <i class="material-icons checkbox-checked">&#xE5CA;</i>
                    </span>
                </span>
            </div>
            <div class="col-xs-11">
                <label class="required" for="bpe_afterpay_accept" style="display: inline">
                    <a href="{$riverty_tc_url}"
                            target="_blank"
                            style="text-decoration: underline">
                        {l s='Ik accepteer de algemene voorwaarden van Riverty.' mod='buckaroo3'}
                    </a>
                </label>
            </div>
        </div>
        {if ($country == 'NL' && $methodsWithFinancialWarning['afterpay']) }
            <p class="small">
                {l s=$methodsWithFinancialWarning['warningText'] sprintf=['afterpay'] mod='buckaroo3'}
            </p>
        {/if}
    </form>
</section>
