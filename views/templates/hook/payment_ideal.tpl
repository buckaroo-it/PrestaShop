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
    <input type="hidden" name="buckarooKey" value="IDEAL">
    <form id="booIdealForm" action="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'ideal'])|escape:'quotes':'UTF-8'}" method="post">
        {l s='Choose your bank' mod='buckaroo3'}<br/><br/>

        <div rel="booRow" class="pointer bankRadioBtn"><input name="BPE_Issuer" value="ABNAMRO" type="radio"
                                                              class="middle" checked/> <img
                    src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/ideal/ABNAMRO.png" class="middle"
                    style="width:16px;"/> {l s='ABN AMRO' mod='buckaroo3'}</div>
        <div rel="booRow" class="pointer bankRadioBtn"><input name="BPE_Issuer" value="ASNBANK" type="radio"
                                                              class="middle"/> <img
                    src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/ideal/ASNBANK.png" class="middle"
                    style="width: 16px;"/> {l s='ASN Bank' mod='buckaroo3'}</div>
        <div rel="booRow" class="pointer bankRadioBtn"><input name="BPE_Issuer" value="INGBANK" type="radio"
                                                              class="middle"/> <img
                    src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/ideal/ING.png" class="middle"
                    style="width: 16px;"/> {l s='ING' mod='buckaroo3'}</div>
        <div rel="booRow" class="pointer bankRadioBtn"><input name="BPE_Issuer" value="RABOBANK" type="radio"
                                                              class="middle"/> <img
                    src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/ideal/Rabobank.png" class="middle"
                    style="width: 16px;"/> {l s='Rabobank' mod='buckaroo3'}</div>
        <div rel="booRow" class="pointer bankRadioBtn"><input name="BPE_Issuer" value="SNSBANK" type="radio"
                                                              class="middle"/> <img
                    src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/ideal/SNS.png" class="middle"
                    style="width: 16px;"/> {l s='SNS Bank' mod='buckaroo3'}</div>
        <div rel="booRow" class="pointer bankRadioBtn"><input name="BPE_Issuer" value="SNSREGIO" type="radio"
                                                              class="middle"/> <img
                    src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/ideal/Regiobank.png" class="middle"
                    style="width: 16px;"/> {l s='RegioBank' mod='buckaroo3'}</div>
        <div rel="booRow" class="pointer bankRadioBtn"><input name="BPE_Issuer" value="TRIODOS" type="radio"
                                                              class="middle"/> <img
                    src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/ideal/Triodos.png" class="middle"
                    style="width: 16px;"/> {l s='Triodos Bank' mod='buckaroo3'}</div>
        <div rel="booRow" class="pointer bankRadioBtn"><input name="BPE_Issuer" value="LANSCHOT" type="radio"
                                                              class="middle"/> <img
                    src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/ideal/vanLanschot.png" class="middle"
                    style="width: 16px;"/> {l s='Van Lanschot' mod='buckaroo3'}</div>
        <div rel="booRow" class="pointer bankRadioBtn"><input name="BPE_Issuer" value="KNAB" type="radio"
                                                              class="middle"/> <img
                    src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/ideal/KNAB.png" class="middle"
                    style="width: 16px;"/> {l s='Knab' mod='buckaroo3'}</div>
        <div rel="booRow" class="pointer bankRadioBtn"><input name="BPE_Issuer" value="BUNQ" type="radio"
                                                              class="middle"/> <img
                    src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/ideal/Bunq.png" class="middle"
                    style="width: 16px;"/> {l s='Bunq' mod='buckaroo3'}</div>
        <div rel="booRow" class="pointer bankRadioBtn"><input name="BPE_Issuer" value="REVOLT21" type="radio"
                                                              class="middle"/> <img
                    src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/ideal/Revolut.png" class="middle"
                    style="width: 16px;"/> {l s='Revolut' mod='buckaroo3'}</div>
        <br/>
    </form>
</section>