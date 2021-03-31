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
<div class="modal fade" id="modal-loading" tabindex="-1" role="dialog" aria-labelledby="moadal-loadingLabel"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-body">
                {l s='Payment in progress' mod='buckaroo3'}
                <div class="progress progress-striped active">
                    <div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="100"
                         aria-valuemin="0" aria-valuemax="100" style="width: 100%">
                        <span class="sr-only">&nbsp;</span>
                    </div>
                </div>
            </div>
        </div>
        <!-- /.modal-content -->
    </div>
    <!-- /.modal-dialog -->
</div><!-- /.modal -->

{***********PayPal*************}
{if $paypal_enabled}
    <div class="row">
        <div class="col-xs-12 col-md-6">
            <p class="payment_module">
                <a id="paypal_enabled" style="background-image: url({$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/buckaroo_paypal.png)"
                   class="buckaroo_paylink buckaroo_paylink" onclick="paymentMethodValidation.init(this); return false;"
                   href="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'buckaroopaypal'])|escape:'quotes':'UTF-8'}"
                   title="{l s='Pay by PayPal' mod='buckaroo3'}">
                    {l s='Pay by PayPal' mod='buckaroo3'}
                </a>
            </p>
        </div>
    </div>
{/if}

{******Direct Debit*******}
{if $sepadirectdebit_enabled}
    <div class="row">
        <div class="col-xs-12 col-md-6">
            <p class="payment_module">
                <a id="sepadirectdebit_enabled"
                   style="background-image: url({$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/directdebit.png)"
                   class="buckaroo_paylink" onclick="paymentMethodValidation.init(this); return false;" rel="booAnimLnk"
                   href="#" title="{l s='Pay by SEPA Direct debit' mod='buckaroo3'}">
                    {l s='Pay by SEPA Direct Debit' mod='buckaroo3'}
                </a>
            </p>
        </div>
    </div>
    <div rel="booAnimBl" class="booBlAnimCont row" style="display: none;">
        <div class="col-xs-12 col-md-6">
            <div id="booSepaDirectdebitErr" class="booBlAnimError">
                {l s='You have to fill in all fields properly!' mod='buckaroo3'}
            </div>

            <form name="booSepaDirectdebitForm" id="booSepaDirectdebitForm"
                  action="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'sepadirectdebit'])|escape:'quotes':'UTF-8'}"
                  method="post">
                {l s='Please enter these fields as they appear on your bank account.' mod='buckaroo3'}<br/><br/>

                <div class="row row-padding">
                    <div class="col-xs-3"><label class="required">{l s='Bank account holder' mod='buckaroo3'}:</label>
                    </div>
                    <div class="col-xs-9"><input name="bpe_sepadirectdebit_bank_account_holder"
                                                 id="bpe_sepadirectdebit_bank_account_holder" value="{$customer_name|escape:'html':'UTF-8'}"
                                                 type="text" class="form-control"/></div>
                </div>
                <div class="row row-padding">
                    <div class="col-xs-3"><label class="required">{l s='IBAN' mod='buckaroo3'}:</label></div>
                    <div class="col-xs-9"><input name="bpe_sepadirectdebit_iban" id="bpe_sepadirectdebit_iban" value=""
                                                 type="text" class="form-control" autocomplete="off"/></div>
                </div>
                <div class="row row-padding">
                    <div class="col-xs-3"><label>{l s='BIC' mod='buckaroo3'}:</label></div>
                    <div class="col-xs-9"><input name="bpe_sepadirectdebit_bic" id="bpe_sepadirectdebit_bic" value=""
                                                 type="text" class="form-control" autocomplete="off"/></div>
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

                <div class="row row-padding">
                    <button id="booSepaDirectdebitSendBtn" type="button" name="processCarrier"
                            class="button btn btn-default standard-checkout button-medium pull-right padding-right-button">
                <span>
                    {l s='I confirm my order' mod='buckaroo3'}
                    <i class="icon-chevron-right right"></i>
                </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
{/if}

{************iDeal*************}
{if $ideal_enabled}
    <div class="row">
        <div class="col-xs-12 col-md-6">
            <p class="payment_module">
                <a id="ideal_enabled" style="background-image: url({$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/buckaroo_ideal.png)"
                   class="buckaroo_paylink" onclick="paymentMethodValidation.init(this); return false;" rel="booAnimLnk"
                   href="#"
                   title="{l s='Pay by iDEAL' mod='buckaroo3'}">
                    {l s='Pay by iDEAL' mod='buckaroo3'}
                </a>
            </p>
        </div>
    </div>
    <div rel="booAnimBl" id="booBankLinks" class="booBlAnimCont row">
        <div class="col-xs-12 col-md-6">
            <div id="booIdealErr" class="booBlAnimError">
                {*l s='You have to choose a bank first!' mod='buckarooideal'*}
            </div>

            <form id="booIdealForm" action="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'ideal'])|escape:'quotes':'UTF-8'}"
                  method="post">
                {l s='Choose your bank' mod='buckaroo3'}<br/><br/>

                <div rel="booRow" class="pointer bankRadioBtn"><input name="BPE_Issuer" value="ABNAMRO" type="radio"
                                                                      class="middle" checked/> <img
                            src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/ideal/logo_abn_s.gif" class="middle"
                            style="height: 15px;"/> {l s='ABN AMRO' mod='buckaroo3'}</div>
                <div rel="booRow" class="pointer bankRadioBtn"><input name="BPE_Issuer" value="ASNBANK" type="radio"
                                                                      class="middle"/> <img
                            src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/ideal/logo_asn.gif" class="middle"
                            style="height: 15px;"/> {l s='ASN Bank' mod='buckaroo3'}</div>
                <div rel="booRow" class="pointer bankRadioBtn"><input name="BPE_Issuer" value="INGBANK" type="radio"
                                                                      class="middle"/> <img
                            src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/ideal/logo_ing_s.gif" class="middle"
                            style="height: 15px;"/> {l s='ING' mod='buckaroo3'}</div>
                <div rel="booRow" class="pointer bankRadioBtn"><input name="BPE_Issuer" value="RABOBANK" type="radio"
                                                                      class="middle"/> <img
                            src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/ideal/logo_rabo_s.gif" class="middle"
                            style="height: 15px;"/> {l s='Rabobank' mod='buckaroo3'}</div>
                <div rel="booRow" class="pointer bankRadioBtn"><input name="BPE_Issuer" value="SNSBANK" type="radio"
                                                                      class="middle"/> <img
                            src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/ideal/logo_sns_s.gif" class="middle"
                            style="height: 15px;"/> {l s='SNS Bank' mod='buckaroo3'}</div>
                <div rel="booRow" class="pointer bankRadioBtn"><input name="BPE_Issuer" value="SNSREGIO" type="radio"
                                                                      class="middle"/> <img
                            src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/ideal/logo_sns_s.gif" class="middle"
                            style="height: 15px;"/> {l s='RegioBank' mod='buckaroo3'}</div>
                <div rel="booRow" class="pointer bankRadioBtn"><input name="BPE_Issuer" value="TRIODOS" type="radio"
                                                                      class="middle"/> <img
                            src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/ideal/logo_triodos.gif" class="middle"
                            style="height: 15px;"/> {l s='Triodos Bank' mod='buckaroo3'}</div>
                <div rel="booRow" class="pointer bankRadioBtn"><input name="BPE_Issuer" value="LANSCHOT" type="radio"
                                                                      class="middle"/> <img
                            src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/ideal/logo_lanschot.gif" class="middle"
                            style="height: 15px;"/> {l s='Van Lanschot' mod='buckaroo3'}</div>
                <div rel="booRow" class="pointer bankRadioBtn"><input name="BPE_Issuer" value="KNAB" type="radio"
                                                                      class="middle"/> <img
                            src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/ideal/logo_knab_s.gif" class="middle"
                            style="height: 15px;"/> {l s='Knab' mod='buckaroo3'}</div>
                <div rel="booRow" class="pointer bankRadioBtn"><input name="BPE_Issuer" value="BUNQ" type="radio"
                                                                      class="middle"/> <img
                            src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/ideal/logo_bunq.png" class="middle"
                            style="height: 15px;"/> {l s='Bunq' mod='buckaroo3'}</div>
                <div rel="booRow" class="pointer bankRadioBtn"><input name="BPE_Issuer" value="MOYONL21" type="radio"
                                                                      class="middle"/> <img
                            src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/ideal/MOYONL21.png" class="middle"
                            style="height: 15px;"/> {l s='Moneyou' mod='buckaroo3'}</div>
                <div rel="booRow" class="pointer bankRadioBtn"><input name="BPE_Issuer" value="HANDNL2A" type="radio"
                                                                      class="middle"/> <img
                            src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/ideal/HANDNL2A.png" class="middle"
                            style="height: 15px;"/> {l s='Handelsbanken' mod='buckaroo3'}</div>
                <div rel="booRow" class="pointer bankRadioBtn"><input name="BPE_Issuer" value="REVOLT21" type="radio"
                                                                      class="middle"/> <img
                            src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/ideal/REVOLT21.png" class="middle"
                            style="height: 18px;"/> {l s='Revolut' mod='buckaroo3'}</div>
                <br/>

                <div class="row row-padding">
                    <button type="button" id="booIdealSendBtn" name="processCarrier"
                            class="button btn btn-default standard-checkout button-medium pull-right padding-right-button">
                <span>
                    {l s='I confirm my order' mod='buckaroo3'}
                    <i class="icon-chevron-right right"></i>
                </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
{/if}


{if $afterpay_enabled}
    <div class="row">
        <div class="col-xs-12 col-md-6">
            <p class="payment_module">
                <a id="afterpay_enabled_digi"
                   style="background-image: url({$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/logo_afterpay.jpg)"
                   class="buckaroo_paylink" onclick="paymentMethodValidation.init(this); return false;" rel="booAnimLnk"
                   href="#" title="{l s='Afterpay' mod='buckaroo3'}">
                    {l s='Afterpay' mod='buckaroo3'}
                </a>
            </p>
        </div>
    </div>
    <div rel="booAnimBl" class="booBlAnimCont row" style="display: none;">
        <div class="col-xs-12 col-md-6">
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
                {l s='Please provide additional data for AfterPay.' mod='buckaroo3'}<br/><br/>
                <input type="hidden" id="phone_afterpay_billing_digi" name="phone_afterpay_billing"
                       value="{$phone_afterpay_billing|escape:'quotes':'UTF-8'}"/>

                <div class="row row-padding">
                    <div class="col-xs-12"><label class="required">{l s='Invoice person gender' mod='buckaroo3'}
                            :</label></div>
                    <div class="col-xs-12"><select name="bpe_afterpay_invoice_person_gender"
                                                   id="bpe_afterpay_invoice_person_gender_digi"
                                                   class="required-entry form-control">
                            <option value="1">{l s='Mr.' mod='buckaroo3'}</option>
                            <option value="2">{l s='Mrs.' mod='buckaroo3'}</option>
                        </select></div>
                </div>
                <div class="row row-padding">
                    <div class="col-xs-12"><label
                                class="required">{l s='Invoice person date of birth' mod='buckaroo3'}</label></div>
                    <div class="col-xs-12">
                        <input title="Day" name="customerbirthdate_d_billing" id="customerbirthdate_d_billing_digi"
                               type="text" value="{$customer_birthday[2]|escape:'html':'UTF-8'}" class="form-control form-control-small"
                               autocomplete="off"/>
                        {l s='DD' mod='buckaroo3'}
                        <input title="Month" name="customerbirthdate_m_billing" id="customerbirthdate_m_billing_digi"
                               type="text" value="{$customer_birthday[1]|escape:'html':'UTF-8'}" class="form-control form-control-small"
                               autocomplete="off"/>
                        {l s='MM' mod='buckaroo3'}
                        <input title="Year" name="customerbirthdate_y_billing" id="customerbirthdate_y_billing_digi"
                               type="text" value="{$customer_birthday[0]|escape:'html':'UTF-8'}" class="form-control form-control-small"
                               autocomplete="off"/>
                        {l s='YYYY' mod='buckaroo3'}
                    </div>
                </div>
                {if $address_differ == 1}
                    <input type="hidden" id="phone_afterpay_shipping_digi" name="phone_afterpay_shipping"
                           value="{$phone_afterpay_shipping|escape:'html':'UTF-8'}"/>
                    <div class="row row-padding">
                        <div class="col-xs-12"><label class="required">{l s='Shipping person gender' mod='buckaroo3'}
                                :</label></div>
                        <div class="col-xs-12"><select name="bpe_afterpay_shipping_person_gender"
                                                       id="bpe_afterpay_shipping_person_gender_digi"
                                                       class="required-entry form-control">
                                <option value="1">{l s='Mr.' mod='buckaroo3'}</option>
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

                <div class="row row-padding" style="padding: 25px 0 0 0">
                    <div class="col-xs-1" style="width: 1%"><input name="bpe_afterpay_accept"
                                                                   id="bpe_afterpay_accept_digi" value="ON"
                                                                   type="checkbox" class="form-control"/></div>
                    <div class="col-xs-11"><label class="required" for="bpe_afterpay_accept" style="display: inline"><a
                                    href="https://www.afterpay.nl/nl/klantenservice/betalingsvoorwaarden/"
                                    target="_blank"
                                    style="text-decoration: underline">{l s='Ik accepteer de algemene voorwaarden van AfterPay.' mod='buckaroo3'}</a></label>
                    </div>
                </div>


                <br/>

                <div class="row row-padding">
                    <button id="booAfterPaySendBtn_digi" type="button" name="processCarrier"
                            class="button btn btn-default standard-checkout button-medium pull-right padding-right-button">
                <span>
                    {l s='I confirm my order' mod='buckaroo3'}
                    <i class="icon-chevron-right right"></i>
                </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
{/if}

{***********GiroPay************}
{if $giropay_enabled}
    <div class="row">
        <div class="col-xs-12 col-md-6">
            <p class="payment_module">
                <a id="giropay_enabled" style="background-image: url({$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/buckaroo_giropay.png)"
                   class="buckaroo_paylink" onclick="paymentMethodValidation.init(this); return false;" rel="booAnimLnk"
                   href="#"
                   title="{l s='Pay by GiroPay' mod='buckaroo3'}">
                    {l s='Pay by Giropay' mod='buckaroo3'}
                </a>
            </p>
        </div>
    </div>
    <div rel="booAnimBl" class="booBlAnimCont row" style="display: none;">
        <div id="booGiropayErr" class="booBlAnimError">
            {l s='Need to fill in Bic' mod='buckaroo3'}
        </div>
        <div class="col-xs-12 col-md-6">
            <form name="booGiropayForm" id="booGiropayForm"
                  action="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'giropay'])|escape:'quotes':'UTF-8'}" method="post">

                <div class="row row-padding">
                    <div class="col-xs-3"><label class="required">{l s='BIC' mod='buckaroo3'}:</label></div>
                    <div class="col-xs-9"><input name="BPE_Bic" id="BPE_Bic" value="" autocomplete="off" type="text"
                                                 maxlength="11" class="form-control"/></div>
                </div>

                <br/>

                <div class="row row-padding">
                    <button id="booGiropaySendBtn" type="button" name="processCarrier"
                            class="button btn btn-default standard-checkout button-medium pull-right padding-right-button">
                <span>
                    {l s='I confirm my order' mod='buckaroo3'}
                    <i class="icon-chevron-right right"></i>
                </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
{/if}


{***********KBC************}
{if $kbc_enabled}
    <div class="row">
        <div class="col-xs-12 col-md-6">
            <p class="payment_module">
                <a id="kbc_enabled" style="background-image: url({$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/kbc.png)"
                   class="buckaroo_paylink" onclick="paymentMethodValidation.init(this); return false;" rel="booAnimLnk"
                   href="#"
                   title="{l s='Pay by KBC' mod='buckaroo3'}">
                    {l s='Pay by KBC' mod='buckaroo3'}
                </a>
            </p>
        </div>
    </div>
    <div rel="booAnimBl" class="booBlAnimCont row" style="display: none;">
        <div id="booKbcErr" class="booBlAnimError">
            {l s='Need to fill in Bic' mod='buckaroo3'}
        </div>
        <div class="col-xs-12 col-md-6">
            <form name="booKbcForm" id="booKbcForm"
                  action="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'kbc'])|escape:'quotes':'UTF-8'}" method="post">

                <div class="row row-padding">
                    <button id="booKbcSendBtn" type="button" name="processCarrier"
                            class="button btn btn-default standard-checkout button-medium pull-right padding-right-button">
                <span>
                    {l s='I confirm my order' mod='buckaroo3'}
                    <i class="icon-chevron-right right"></i>
                </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
{/if}

{***********MisterCash*************}
{if $mistercash_enabled}
    <div class="row">
        <div class="col-xs-12 col-md-6">
            <p class="payment_module">
                <a id="mistercash_enabled"
                   style="background-image: url({$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/mistercash.png)"
                   class="buckaroo_paylink" onclick="paymentMethodValidation.init(this); return false;"
                   href="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'bancontactmrcash'])|escape:'quotes':'UTF-8'}"
                   title="{l s='Pay by Bancontact / Mister Cash' mod='buckaroo3'}">
                    {l s='Pay by Bancontact / Mister Cash' mod='buckaroo3'}
                </a>
            </p>
        </div>
    </div>
{/if}

{***********GiftCard*************}
{if $giftcard_enabled}
    <div class="row">
        <div class="col-xs-12 col-md-6">
            <p class="payment_module">
                <a id="giftcard_enabled"
                   style="background-image: url({$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/giftcard.png)"
                   class="buckaroo_paylink" onclick="paymentMethodValidation.init(this); return false;"
                   href="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'giftcard'])|escape:'quotes':'UTF-8'}"
                   title="{l s='Pay by Buckaroo Giftcards' mod='buckaroo3'}">
                    {l s='Pay by Giftcards' mod='buckaroo3'}
                </a>
            </p>
        </div>
    </div>
{/if}

{***********CreditCard*************}
{if $creditcard_enabled}
    <div class="row">
        <div class="col-xs-12 col-md-6">
            <p class="payment_module">
                <a id="creditcard_enabled" style="background-image: url({$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/cc.png)"
                   class="buckaroo_paylink" onclick="paymentMethodValidation.init(this); return false;"
                   href="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'creditcard'])|escape:'quotes':'UTF-8'}"
                   title="{l s='Pay by Buckaroo Creditcards' mod='buckaroo3'}">
                    {l s='Pay by Creditcards' mod='buckaroo3'}
                </a>
            </p>
        </div>
    </div>
{/if}

{***********Sofortbanking*************}
{if $sofortbanking_enabled}
    <div class="row">
        <div class="col-xs-12 col-md-6">
            <p class="payment_module">
                <a id="sofortbanking_enabled"
                   style="background-image: url({$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/sofort.png)"
                   class="buckaroo_paylink" onclick="paymentMethodValidation.init(this); return false;"
                   href="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'sofortueberweisung'])|escape:'quotes':'UTF-8'}"
                   title="{l s='Pay by Sofortbanking' mod='buckaroo3'}">
                    {l s='Pay by Sofortbanking' mod='buckaroo3'}
                </a>
            </p>
        </div>
    </div>
{/if}

{***********Transfer*************}
{if $transfer_enabled}
    <div class="row">
        <div class="col-xs-12 col-md-6">
            <p class="payment_module">
                <a id="transfer_enabled"
                   style="background-image: url({$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/transfer.png)"
                   class="buckaroo_paylink" onclick="paymentMethodValidation.init(this); return false;"
                   href="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'transfer'])|escape:'quotes':'UTF-8'}"
                   title="{l s='Pay by Bank Transfer' mod='buckaroo3'}">
                    {l s='Pay by Bank Transfer' mod='buckaroo3'}
                </a>
            </p>
        </div>
    </div>
{/if}

{***********ApplePay*************}
{if $applepay_enabled}
    <div class="row">
        <div class="col-xs-12 col-md-6">
            <p class="payment_module">
                <a id="applepay_enabled"
                   style="background-image: url({$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/applepay.png)"
                   class="buckaroo_paylink" onclick="paymentMethodValidation.init(this); return false;"
                   href="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'applepay'])|escape:'quotes':'UTF-8'}"
                   title="{l s='Apple Pay' mod='buckaroo3'}">
                    {l s='Apple Pay' mod='buckaroo3'}
                </a>
            </p>
        </div>
    </div>
{/if}