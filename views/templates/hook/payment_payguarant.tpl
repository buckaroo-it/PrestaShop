<section class="additional-information">
    
    <div id="booGarantErr" class="booBlAnimError">
        {l s='You have to fill in all fields properly!' mod='buckaroo3'}
    </div>

    <form name="booGarantForm" id="booGarantForm"
          action="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'payguarant'])|escape:'quotes':'UTF-8'}" method="post">
        {l s='Fill in your personal information in order to process your order' mod='buckaroo3'}<br/><br/>

        <div class="row row-padding">
            <div class="col-xs-3"><label class="required">{l s='Your name' mod='buckaroo3'}:</label></div>
            <div class="col-xs-3"><select name="BPE_Customergender" id="BPE_Customergender"
                                          class="required-entry form-control">
                    <option value=""></option>
                    <option value="1"
                            {if $customer_gender == 1}selected{/if}>{l s='Mr.' mod='buckaroo3'}</option>
                    <option value="2"
                            {if $customer_gender == 2}selected{/if}>{l s='Mrs.' mod='buckaroo3'}</option>
                </select></div>
            <div class="col-xs-6"><input name="BPE_Customername" id="BPE_Customername" value="{$customer_name|escape:'html':'UTF-8'}"
                                         type="text" class="form-control"/></div>
        </div>

        <div class="row row-padding">
            <div class="col-xs-3"><label class="required">{l s='E-mail' mod='buckaroo3'}:</label></div>
            <div class="col-xs-9"><input name="BPE_Customermail" id="BPE_Customermail" value="{$customer_email|escape:'html':'UTF-8'}"
                                         type="text" class="form-control"/></div>
        </div>


        {if $phone == "" && $phone_mobile == ""}
            <div class="row row-padding">
                <div class="col-xs-3"><label>{l s='Phone' mod='buckaroo3'}:</label></div>
                <div class="col-xs-9"><input name="booGarantPhone" id="booGarantPhone" value="" type="text"
                                             class="form-control" autocomplete="off"/></div>
            </div>
        {else}
            {if $phone != ""}<input type="hidden" name="booGarantPhoneLand" value="{$phone|escape:'html':'UTF-8'}" />{/if}
            {if $phone_mobile != ""}<input type="hidden" name="booGarantPhoneMobile"
                                           value="{$phone_mobile}" />{/if}
        {/if}


        <div class="row row-padding">
            <div class="col-xs-3"><label class="required">{l s='IBAN' mod='buckaroo3'}:</label></div>
            <div class="col-xs-9"><input name="bpe_customer_account_number" id="bpe_customer_account_number"
                                         value="" type="text" class="form-control" autocomplete="off"/></div>
        </div>

        <div class="row row-padding">
            <div class="col-xs-3"><label>{l s='Date of Birth' mod='buckaroo3'}</label></div>
            <div class="col-xs-7">
                <input title="Day" name="customerbirthdate[day]" id="customerbirthdate_day" type="text"
                       value="{$customer_birthday[2]|escape:'quotes':'UTF-8'}" class="form-control form-control-small"
                       autocomplete="off"/>
                {l s='DD' mod='buckaroo3'}
                <input title="Month" name="customerbirthdate[month]" id="customerbirthdate_month" type="text"
                       value="{$customer_birthday[1]|escape:'quotes':'UTF-8'}" class="form-control form-control-small"
                       autocomplete="off"/>
                {l s='MM' mod='buckaroo3'}
                <input title="Year" name="customerbirthdate[year]" id="customerbirthdate_year" type="text"
                       value="{$customer_birthday[0]|escape:'quotes':'UTF-8'}" class="form-control form-control-middle"
                       autocomplete="off"/>
                {l s='YYYY' mod='buckaroo3'}
            </div>
            <div class="col-xs-2 form-control-comment">
                {l s='Optional' d='Shop.Forms.Labels'}
            </div>
        </div>
        <br/>

    </form>
</section>
