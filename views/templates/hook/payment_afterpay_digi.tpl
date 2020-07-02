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
    
    <div id="booAfterPayErr_digi" class="alert alert-danger" style="display: none">
        {l s='You have to fill in all fields properly!' mod='buckaroo3'}
    </div>
    <div id="booAfterPayErrTerms_digi" class="alert alert-danger" style="display: none">
        {l s='Pleace accept AfterPay Terms of Payment' mod='buckaroo3'}
    </div>
    <div id="booAfterPayErr_digi_phone_shipping" class="alert alert-danger" style="display: none">
        {l s='Shippping person phone number is incorrect. Please provide 10 digit phone number in your account' mod='buckaroo3'}
    </div>
    <div id="booAfterPayErr_digi_phone_billing" class="alert alert-danger" style="display: none">
        {l s='Billing person phone number is incorrect. Please provide 10 digit phone number in your account' mod='buckaroo3'}
    </div>

    <form name="booAfterPayForm_digi" id="booAfterPayForm_digi"
          action="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'afterpay', 'service' => 'digi'])|escape:'quotes':'UTF-8'}"
          method="post">
        <input type="hidden" id="phone_afterpay_billing_digi" name="phone_afterpay_billing"
               value="{$phone_afterpay_billing|escape:'quotes':'UTF-8'}"/>
        {if $afterpay_btb != 'enable'}
            {l s='Please provide additional data for AfterPay.' mod='buckaroo3'}<br/><br/>
            <div class="row row-padding">
                <div class="col-xs-5"><label class="required">{l s='Invoice person gender' mod='buckaroo3'}
                        :</label></div>
                <div class="col-xs-4">
                    <select name="bpe_afterpay_invoice_person_gender"
                id="bpe_afterpay_invoice_person_gender_digi" class="required-entry form-control form-control">
                        <option value="1">{l s='Mr.' mod='buckaroo3'}</option>
                        <option value="2">{l s='Mrs.' mod='buckaroo3'}</option>
                    </select>
                </div>
            </div>


        <div class="row row-padding">
            <div class="col-xs-5"><label
                        class="required">{l s='Invoice person date of birth' mod='buckaroo3'} :</label></div>
            <div class="col-xs-7" id="afterpay_digi_date" >
                <input title="Day" name="customerbirthdate_d_billing" id="customerbirthdate_d_billing_digi" 
                       type="text" value="{$customer_birthday[2]|escape:'html':'UTF-8'}" class="form-control form-control-small" style="width: 50px;"
                       autocomplete="off" maxlength="2"/>
                {l s='DD' mod='buckaroo3'}
                <input title="Month" name="customerbirthdate_m_billing" id="customerbirthdate_m_billing_digi" 
                       type="text" value="{$customer_birthday[1]|escape:'html':'UTF-8'}" class="form-control form-control-small" style="width: 50px;"
                       autocomplete="off" maxlength="2"/>
                {l s='MM' mod='buckaroo3'}
                <input title="Year" name="customerbirthdate_y_billing" id="customerbirthdate_y_billing_digi" 
                       type="text" value="{$customer_birthday[0]|escape:'html':'UTF-8'}" class="form-control form-control-middle" style="width: 70px;"
                       autocomplete="off" maxlength="4"/>
                {l s='YYYY' mod='buckaroo3'}
            </div>
        </div>
        {/if}
        {if $address_differ == 1}
            <input type="hidden" id="phone_afterpay_shipping_digi" name="phone_afterpay_shipping"
                   value="{$phone_afterpay_shipping|escape:'html':'UTF-8'}"/>


            <div class="row row-padding">
                <div class="col-xs-12"><label class="required">{l s='Shipping person gender' mod='buckaroo3'}
                        :</label></div>
                <div class="col-xs-12"><select name="bpe_afterpay_shipping_person_gender"
                                               id="bpe_afterpay_shipping_person_gender_digi"
                                               class="required-entry form-control">
                        <option value="1" selected="selected" >{l s='Mr.' mod='buckaroo3'}</option>
                        <option value="2">{l s='Mrs.' mod='buckaroo3'}</option>
                    </select></div>
            </div>

            <div class="row row-padding">
                <div class="col-xs-12"><label
                            class="required">{l s='Shipping person date of Birth' mod='buckaroo3'}</label></div>
                <div class="col-xs-12">
                    <input title="Day" name="customerbirthdate_d_shipping"
                           id="customerbirthdate_d_shipping_digi" type="text" value="{$customer_birthday[2]|escape:'html':'UTF-8'}"
                           class="form-control form-control-small" autocomplete="off"/>
                    {l s='DD' mod='buckaroo3'}
                    <input title="Month" name="customerbirthdate_m_shipping"
                           id="customerbirthdate_m_shipping_digi" type="text" value="{$customer_birthday[1]|escape:'html':'UTF-8'}"
                           class="form-control form-control-small" autocomplete="off"/>
                    {l s='MM' mod='buckaroo3'}
                    <input title="Year" name="customerbirthdate_y_shipping"
                           id="customerbirthdate_y_shipping_digi" type="text" value="{$customer_birthday[0]|escape:'html':'UTF-8'}"
                           class="form-control form-control-small" autocomplete="off"/>
                    {l s='YYYY' mod='buckaroo3'}
                </div>
            </div>
        {/if}
        <br/><br/>
        {if $afterpay_btb == 'enable' && $customer_company != ''}
            {l s='Please provide additional data if for company' mod='buckaroo3'}
            <br/>
            <br/>
            <div class="row row-padding">
                <div class="col-xs-6"><label
                            class="required">{l s='COC (KvK) number' mod='buckaroo3'}:</label></div>
                <div class="col-xs-6"><input name="buckaroo-afterpay-CompanyCOCRegistration"
                                              id="buckaroo-afterpay-CompanyCOCRegistration_digi"
                                              value="" type="text" class="form-control"/>
                </div>
            </div>
            <div class="row row-padding">
                <div class="col-xs-6"><label
                            class="required">{l s='Name of the organization' mod='buckaroo3'}:</label></div>
                <div class="col-xs-6"><input name="buckaroo-afterpay-CompanyName"
                                              id="buckaroo-afterpay-CompanyName_digi" value="{$customer_company|escape:'html':'UTF-8'}"
                                              type="text" class="form-control"/></div>
            </div>
        {/if}

        <div class="row row-padding" style="padding: 25px 0 0 0">

            <!--div class="col-xs-12 hidden"><label class="required"></label></div-->
            <div class="col-xs-1">
                <span class="custom-checkbox">
                    <input id="bpe_afterpay_accept_digi" name="bpe_afterpay_accept" required="" type="checkbox" value="ON">
                    <span><i class="material-icons checkbox-checked">&#xE5CA;</i></span>
                </span>
            </div>
            <div class="col-xs-11"><label class="required" for="bpe_afterpay_accept" style="display: inline"><a
                            href="https://www.afterpay.nl/nl/klantenservice/betalingsvoorwaarden/"
                            target="_blank"
                            style="text-decoration: underline">{l s='Ik accepteer de algemene voorwaarden van AfterPay.' mod='buckaroo3'}</a></label>
            </div>
        </div>


        <br/>
    </form>
</section>
