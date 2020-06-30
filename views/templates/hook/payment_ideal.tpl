<section class="additional-information">
    
    <form id="booIdealForm" action="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'ideal'])|escape:'quotes':'UTF-8'}" method="post">
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
        <br/>
    </form>
</section>