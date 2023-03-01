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
    <input type="hidden" name="buckarooKey" value="BILLINK">
    <form id="booIdealForm" action="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'billink'])|escape:'quotes':'UTF-8'}" method="post">
        {l s='Please select gender:' mod='buckaroo3'}<br/><br/>
                <div class="col-xs-12">
                    <select name="bpe_billink_person_gender"
                                               id="bpe_billink_person_gender"
                                               class="required-entry form-control">
                        <option value="Male" selected="selected" >{l s='He/him' mod='buckaroo3'}</option>
                        <option value="Female">{l s='She/her' mod='buckaroo3'}</option>
                        <option value="Unknown">{l s='They/Them' mod='buckaroo3'}</option>
                        <option value="Unknown">{l s='I prefer not to say' mod='buckaroo3'}</option>
                    </select>
                </div>
        <br/>
                <div class="row row-padding">
            <div class="col-xs-5"><label
                        class="required">{l s='Date of birth' mod='buckaroo3'} :</label></div>
            <div class="col-xs-7" id="billink_date" >
                <input title="Day" name="customerbirthdate_d_billing_billink" id="customerbirthdate_d_billing_billink" 
                       type="text" value="{$customer_birthday[2]|escape:'html':'UTF-8'}" class="form-control form-control-small" style="width: 50px;"
                       autocomplete="off" maxlength="2"/>
                {l s='DD' mod='buckaroo3'}
                <input title="Month" name="customerbirthdate_m_billing_billink" id="customerbirthdate_m_billing_billink" 
                       type="text" value="{$customer_birthday[1]|escape:'html':'UTF-8'}" class="form-control form-control-small" style="width: 50px;"
                       autocomplete="off" maxlength="2"/>
                {l s='MM' mod='buckaroo3'}
                <input title="Year" name="customerbirthdate_y_billing_billink" id="customerbirthdate_y_billing_billink" 
                       type="text" value="{$customer_birthday[0]|escape:'html':'UTF-8'}" class="form-control form-control-middle" style="width: 70px;"
                       autocomplete="off" maxlength="4"/>
                {l s='YYYY' mod='buckaroo3'}
            </div>
        </div>
    </form>
</section>