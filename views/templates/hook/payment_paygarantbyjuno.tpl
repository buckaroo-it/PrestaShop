<section class="additional-information">
    
    <div id="booByJunoErr" class="booBlAnimError">
                    {l s='You have to fill in all fields properly!' mod='buckaroo3'}
                </div>
    <form name="booByJunoForm" id="booByJunoForm" action="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'paygarantbyjuno'])|escape:'quotes':'UTF-8'}" method="post">
        {l s='Fill in your personal information in order to process your order' mod='buckaroo3'}<br/><br/>

        <div class="row row-padding">
          <div class="col-xs-3"><label class="required">{l s='Your name' mod='buckaroo3'}:</label></div>
          <div class="col-xs-3"><select name="BPE_BJ_Customergender" id="BPE_BJ_Customergender"
                                        class="required-entry form-control">
                  <option value=""></option>
                  <option value="1"
                          {if $customer_gender == 1}selected{/if}>{l s='Mr.' mod='buckaroo3'}</option>
                  <option value="2"
                          {if $customer_gender == 2}selected{/if}>{l s='Mrs.' mod='buckaroo3'}</option>
              </select></div>
          <div class="col-xs-6"><input name="BPE_BJ_Customername" id="BPE_BJ_Customername"
                                       value="{$customer_name|escape:'html':'UTF-8'}" type="text" class="form-control"/></div>

        </div>

        <div class="row row-padding">
          <div class="col-xs-3"><label class="required">{l s='E-mail' mod='buckaroo3'}:</label></div>
          <div class="col-xs-9"><input name="BPE_BJ_Customermail" id="BPE_BJ_Customermail"
                                       value="{$customer_email|escape:'html':'UTF-8'}" type="text" class="form-control"/></div>
        </div>


        {if $phone == "" && $phone_mobile == ""}
          <div class="row row-padding">
              <div class="col-xs-3"><label class="required">{l s='Phone' mod='buckaroo3'}:</label></div>
              <div class="col-xs-9"><input name="booByJunoPhone" id="booByJunoPhone" value="" type="text"
                                           class="form-control" autocomplete="off"/></div>
          </div>
        {else}
          {if $phone != ""}<input type="hidden" name="booByJunoPhoneLand" value="{$phone|escape:'html':'UTF-8'}" />{/if}
          {if $phone_mobile != ""}<input type="hidden" name="booByJunoPhoneMobile"
                                         value="{$phone_mobile}" />{/if}
        {/if}


        <div class="row row-padding">
          <div class="col-xs-3"><label class="required">{l s='IBAN' mod='buckaroo3'}:</label></div>
          <div class="col-xs-9"><input name="bpe_bj_customer_account_number"
                                       id="bpe_bj_customer_account_number" value="" type="text"
                                       class="form-control" autocomplete="off"/></div>
        </div>
        <div class="row row-padding">
          <div class="col-xs-3"><label>{l s='Date of Birth' mod='buckaroo3'}:</label></div>
          <div class="col-xs-7" id="paygarant_byjuno_date">
              <input title="Day" name="bjcustomerbirthdate[day]" id="bjcustomerbirthdate_day" type="text"
                     value="{$customer_birthday[2]|escape:'quotes':'UTF-8'}" class="form-control form-control-small"
                     autocomplete="off" maxlength="2"/>
              {l s='DD' mod='buckaroo3'}
              <input title="Month" name="bjcustomerbirthdate[month]" id="bjcustomerbirthdate_month"
                     type="text" value="{$customer_birthday[1]|escape:'quotes':'UTF-8'}" class="form-control form-control-small"
                     autocomplete="off" maxlength="2"/>
              {l s='MM' mod='buckaroo3'}
              <input title="Year" name="bjcustomerbirthdate[year]" id="bjcustomerbirthdate_year" type="text"
                     value="{$customer_birthday[0]|escape:'quotes':'UTF-8'}" class="form-control form-control-middle"
                     autocomplete="off" maxlength="4" minlength="4"/>
              {l s='YYYY' mod='buckaroo3'}
          </div>
            <div class="col-xs-2 form-control-comment">
                {l s='Optional' d='Shop.Forms.Labels'}
            </div>
        </div>
        <br/>
    </form>
</section>
