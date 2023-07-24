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

    <input type="hidden" name="buckarooKey" value="AFTERPAY">
    <form class="mb-1" name="booAfterPayForm_digi" id="booAfterPayForm_digi"
          action="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'afterpay', 'service' => 'digi'])|escape:'quotes':'UTF-8'}"
          method="post">
        {l s='Please provide additional data for Riverty | AfterPay.' mod='buckaroo3'}<br/><br/>
        {if !$phone_afterpay_billing}
            <div class="row row-padding">
                <div class="col-sm-5">
                    <label for="phone_afterpay_billing_digi"
                           class="required">
                        {l s='Invoice person phone number' mod='buckaroo3'}:
                    </label>
                </div>
                <div class="col-sm-7">
                    <input type="text"
                           class="form-control form-control-large"
                           id="phone_afterpay_billing_digi"
                           name="phone_afterpay_billing"
                           value="{$phone_afterpay_billing|escape:'html':'UTF-8'}"
                    >
                </div>
            </div>
        {else}
            <div class="row row-padding">
                <div class="col-sm-12">
                    <input type="hidden"
                           id="phone_afterpay_billing_digi"
                           name="phone_afterpay_billing"
                           value="{$phone_afterpay_billing|escape:'html':'UTF-8'}"
                    >
                </div>
            </div>
        {/if}

        <div class="row row-padding">
            <div class="col-xs-5">
                <label class="required">
                    {l s='Invoice person date of birth' mod='buckaroo3'} :
                </label>
            </div>
            <div class="col-xs-7" id="afterpay_digi_date">
                <input title="Day" name="customerbirthdate_d_billing" id="customerbirthdate_d_billing_digi"
                       type="text" value="{$customer_birthday[2]|escape:'html':'UTF-8'}"
                       class="form-control form-control-small" style="width: 50px;"
                       autocomplete="off" maxlength="2"/>
                {l s='DD' mod='buckaroo3'}
                <input title="Month" name="customerbirthdate_m_billing" id="customerbirthdate_m_billing_digi"
                       type="text" value="{$customer_birthday[1]|escape:'html':'UTF-8'}"
                       class="form-control form-control-small" style="width: 50px;"
                       autocomplete="off" maxlength="2"/>
                {l s='MM' mod='buckaroo3'}
                <input title="Year" name="customerbirthdate_y_billing" id="customerbirthdate_y_billing_digi"
                       type="text" value="{$customer_birthday[0]|escape:'html':'UTF-8'}"
                       class="form-control form-control-middle" style="width: 70px;"
                       autocomplete="off" maxlength="4"/>
                {l s='YYYY' mod='buckaroo3'}
            </div>
        </div>
        {if $address_differ == 1}
            <input type="hidden" id="phone_afterpay_shipping_digi" name="phone_afterpay_shipping"
                   value="{$phone_afterpay_shipping|escape:'html':'UTF-8'}"/>
            <div class="row row-padding">
                <div class="col-xs-5">
                    <label class="required">
                        {l s='Shipping person date of Birth' mod='buckaroo3'} :
                    </label>
                </div>
                <div class="col-xs-7">
                    <input title="Day" name="customerbirthdate_d_shipping"
                           id="customerbirthdate_d_shipping_digi" type="text"
                           value="{$customer_birthday[2]|escape:'html':'UTF-8'}"
                           class="form-control form-control-small" autocomplete="off"/>
                    {l s='DD' mod='buckaroo3'}
                    <input title="Month" name="customerbirthdate_m_shipping"
                           id="customerbirthdate_m_shipping_digi" type="text"
                           value="{$customer_birthday[1]|escape:'html':'UTF-8'}"
                           class="form-control form-control-small" autocomplete="off"/>
                    {l s='MM' mod='buckaroo3'}
                    <input title="Year" name="customerbirthdate_y_shipping"
                           id="customerbirthdate_y_shipping_digi" type="text"
                           value="{$customer_birthday[0]|escape:'html':'UTF-8'}"
                           class="form-control form-control-small" autocomplete="off"/>
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
                           class="form-control form-control-large" autocomplete="off"/>
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
                           class="form-control form-control-large" autocomplete="off"/>
                </div>
            </div>
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
                    <a href="https://www.afterpay.nl/nl/klantenservice/betalingsvoorwaarden/"
                            target="_blank"
                            style="text-decoration: underline">
                        {l s='Ik accepteer de algemene voorwaarden van Riverty | AfterPay.' mod='buckaroo3'}
                    </a>
                </label>
            </div>
        </div>
    </form>
</section>
