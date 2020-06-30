<section class="additional-information">
    
    <div id="booAfterPayErr_ssd" class="alert alert-danger" style="display: none">
        {l s='You have to fill in all fields properly!' mod='buckaroo3'}
    </div>
    <div id="booAfterPayErrTerms_ssd" class="alert alert-danger" style="display: none">
        {l s='Pleace accept AfterPay Terms of Payment' mod='buckaroo3'}
    </div>
    <div id="booAfterPayErr_ssd_phone_shipping" class="alert alert-danger" style="display: none">
        {l s='Shippping person phone number is incorrect. Please provide 10 digit phone number in your account' mod='buckaroo3'}
    </div>
    <div id="booAfterPayErr_ssd_phone_billing" class="alert alert-danger" style="display: none">
        {l s='Billing person phone number is incorrect. Please provide 10 digit phone number in your account' mod='buckaroo3'}
    </div>

    <form name="booAfterPayForm_ssd" id="booAfterPayForm_ssd"
          action="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'afterpay', 'service' => 'sepa'])|escape:'quotes':'UTF-8'}"
          method="post">
        {l s='Please provide additional data for AfterPay.' mod='buckaroo3'}<br/><br/>
        <input type="hidden" id="phone_afterpay_billing_ssd" name="phone_afterpay_billing"
               value="{$phone_afterpay_billing|escape:'html':'UTF-8'}"/>

        <div class="row row-padding">
            <div class="col-xs-5"><label class="required">{l s='IBAN' mod='buckaroo3'}:</label></div>
            <div class="col-xs-7"><input name="bpe_afterpay_iban" id="bpe_afterpay_iban_ssd" value=""
                                          type="text" class="form-control"/></div>
        </div>
        <div class="row row-padding">
            <div class="col-xs-5"><label class="required">{l s='Invoice person gender' mod='buckaroo3'}:</label></div>
            <div class="col-xs-4"><select name="bpe_afterpay_invoice_person_gender"
                                           id="bpe_afterpay_invoice_person_gender_ssd"
                                           class="required-entry form-control">
                    <option value="1">{l s='Mr.' mod='buckaroo3'}</option>
                    <option value="2">{l s='Mrs.' mod='buckaroo3'}</option>
                </select></div>
        </div>
        <div class="row row-padding">
            <div class="col-xs-5"><label
                        class="required">{l s='Invoice person date of birth' mod='buckaroo3'}:</label></div>
            <div class="col-xs-7" id="afterpay_ssd_date">
                <input title="Day" name="customerbirthdate_d_billing" id="customerbirthdate_d_billing_ssd"
                       type="text" value="{$customer_birthday[2]|escape:'html':'UTF-8'}" class="form-control form-control-small"
                       autocomplete="off" maxlength="2"/>
                {l s='DD' mod='buckaroo3'}
                <input title="Month" name="customerbirthdate_m_billing" id="customerbirthdate_m_billing_ssd"
                       type="text" value="{$customer_birthday[1]|escape:'html':'UTF-8'}" class="form-control form-control-small"
                       autocomplete="off" maxlength="2"/>
                {l s='MM' mod='buckaroo3'}
                <input title="Year" name="customerbirthdate_y_billing" id="customerbirthdate_y_billing_ssd"
                       type="text" value="{$customer_birthday[0]|escape:'html':'UTF-8'}" class="form-control form-control-middle"
                       autocomplete="off" maxlength="4"/>
                {l s='YYYY' mod='buckaroo3'}
            </div>
        </div>
        {if $address_differ == 1}
            <input type="hidden" id="phone_afterpay_shipping_ssd" name="phone_afterpay_shipping"
                   value="{$phone_afterpay_shipping|escape:'quotes':'UTF-8'}"/>
            <div class="row row-padding">
                <div class="col-xs-12"><label class="required">{l s='Shipping person gender' mod='buckaroo3'}
                        :</label></div>
                <div class="col-xs-12"><select name="bpe_afterpay_shipping_person_gender"
                                               id="bpe_afterpay_shipping_person_gender_ssd"
                                               class="required-entry form-control">
                        <option value="1">{l s='Mr.' mod='buckaroo3'}</option>
                        <option value="2">{l s='Mrs.' mod='buckaroo3'}</option>
                    </select></div>
            </div>
            <div class="row row-padding">
                <div class="col-xs-12"><label
                            class="required">{l s='Shipping person date of Birth' mod='buckaroo3'}</label></div>
                <div class="col-xs-12">
                    <input title="Day" name="customerbirthdate_d_shipping" id="customerbirthdate_d_shipping_ssd"
                           type="text" value="{$customer_birthday[2]|escape:'html':'UTF-8'}" class="form-control form-control-small"
                           autocomplete="off"/>
                    {l s='DD' mod='buckaroo3'}
                    <input title="Month" name="customerbirthdate_m_shipping"
                           id="customerbirthdate_m_shipping_ssd" type="text" value="{$customer_birthday[1]|escape:'html':'UTF-8'}"
                           class="form-control form-control-small" autocomplete="off"/>
                    {l s='MM' mod='buckaroo3'}
                    <input title="Year" name="customerbirthdate_y_shipping"
                           id="customerbirthdate_y_shipping_ssd" type="text" value="{$customer_birthday[0]|escape:'html':'UTF-8'}"
                           class="form-control form-control-small" autocomplete="off"/>
                    {l s='YYYY' mod='buckaroo3'}
                </div>
            </div>
        {/if}

        <div class="row row-padding" style="padding: 25px 0 0 0">
            <div class="col-xs-1" style="/*width: 1%*/">
                <span class="custom-checkbox">
                    <input id="bpe_afterpay_accept_ssd" name="bpe_afterpay_accept" required="" type="checkbox" value="ON">
                    <span><i class="material-icons checkbox-checked">&#xE5CA;</i></span>
                </span>
            </div>
            <div class="col-xs-11">
                <label class="required" for="bpe_afterpay_accept" style="display: inline">
                    <a href="https://www.afterpay.nl/nl/klantenservice/betalingsvoorwaarden/" target="_blank" style="text-decoration: underline">
                        {l s='Ik accepteer de algemene voorwaarden van AfterPay.' mod='buckaroo3'}</a></label>
            </div>
        </div>

    </form>
</section>
