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
    
    <div id="booSepaDirectdebitErr" class="booBlAnimError">
        {l s='You have to fill in all fields properly!' mod='buckaroo3'}
    </div>

    <form name="booSepaDirectdebitForm" id="booSepaDirectdebitForm"
          action="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'sepadirectdebit'])|escape:'quotes':'UTF-8'}"
          method="post">
        {l s='Please enter these fields as they appear on your bank account.' mod='buckaroo3'}<br/><br/>

        <div class="row row-padding">
            <div class="col-xs-3"><label class="required" style="text-align: inherit;">{l s='Bank account holder' mod='buckaroo3'}:</label>
            </div>
            <div class="col-xs-7"><input name="bpe_sepadirectdebit_bank_account_holder"
                                         id="bpe_sepadirectdebit_bank_account_holder" value="{$customer_name|escape:'html':'UTF-8'}"
                                         type="text" class="form-control"/></div>
        </div>
        <div class="row row-padding">
            <div class="col-xs-3"><label class="required" >{l s='IBAN' mod='buckaroo3'}:</label></div>
            <div class="col-xs-7"><input name="bpe_sepadirectdebit_iban" id="bpe_sepadirectdebit_iban" value=""
                                         type="text" class="form-control" autocomplete="off"/></div>
        </div>
        <div class="row row-padding">
            <div class="col-xs-3"><label  style="text-align: inherit;">{l s='BIC' mod='buckaroo3'}:</label></div>
            <div class="col-xs-7"><input name="bpe_sepadirectdebit_bic" id="bpe_sepadirectdebit_bic" value=""
                                         type="text" class="form-control" autocomplete="off"/></div>
            <div class="col-xs-2 form-control-comment">
                {l s='Optional' d='Shop.Forms.Labels'}
            </div>
        </div>
        {if $phone == "" && $phone_mobile == ""}
            <div class="row row-padding">
                <div class="col-xs-3"><label>{l s='Phone' mod='buckaroo3'}:</label></div>
                <div class="col-xs-9"><input name="booSepaDirectdebitPhone" id="booSepaDirectdebitPhone"
                                             value="" type="text" class="form-control" autocomplete="off"/>
                </div>
            </div>
        {else}
            {if $phone != ""}<input type="hidden" name="booSepaDirectdebitPhoneLand" value="{$phone|escape:'html':'UTF-8'}" />{/if}
            {if $phone_mobile != ""}<input type="hidden" name="booSepaDirectdebitPhoneMobile"
                                           value="{$phone_mobile}" />{/if}
        {/if}


        <br/>

    </form>
</section>
