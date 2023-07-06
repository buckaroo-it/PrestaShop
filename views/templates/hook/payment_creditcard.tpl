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
    <input type="hidden" name="buckarooKey" value="CREDITCARD">
    <form id="booIdealForm" action="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'creditcard'])|escape:'quotes':'UTF-8'}" method="post">
        {l s='Choose your credit or debit card' mod='buckaroo3'}<br/><br/>

        <div rel="booRow" class="pointer bankRadioBtn bk-credit-card"><input name="BPE_CreditCard" value="amex" type="radio"
                                                              class="middle" /> <img
                    src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/creditcard/AmericanExpress.png" class="middle bk-creditcard-logo"
                    /> {l s='American Express' mod='buckaroo3'}</div>
        <div rel="booRow" class="pointer bankRadioBtn bk-credit-card"><input name="BPE_CreditCard" value="cartebancaire" type="radio"
                                                              class="middle" /> <img
                    src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/creditcard/CarteBancaire.png" class="middle bk-creditcard-logo"
                    /> {l s='CarteBancaire' mod='buckaroo3'}</div>
        <div rel="booRow" class="pointer bankRadioBtn bk-credit-card"><input name="BPE_CreditCard" value="cartebleue" type="radio"
                                                              class="middle" /> <img
                    src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/creditcard/CarteBleue.png" class="middle bk-creditcard-logo"
                    /> {l s='CarteBleue' mod='buckaroo3'}</div>
        <div rel="booRow" class="pointer bankRadioBtn bk-credit-card"><input name="BPE_CreditCard" value="dankort" type="radio"
                                                              class="middle" /> <img
                    src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/creditcard/Dankort.png" class="middle bk-creditcard-logo"
                    /> {l s='Dankort' mod='buckaroo3'}</div>
        <div rel="booRow" class="pointer bankRadioBtn bk-credit-card"><input name="BPE_CreditCard" value="maestro" type="radio"
                                                              class="middle" /> <img
                    src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/creditcard/Maestro.png" class="middle bk-creditcard-logo"
                    /> {l s='Maestro' mod='buckaroo3'}</div>
        <div rel="booRow" class="pointer bankRadioBtn bk-credit-card"><input name="BPE_CreditCard" value="mastercard" type="radio"
                                                              class="middle" /> <img
                    src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/creditcard/Mastercard.png" class="middle bk-creditcard-logo"
                    /> {l s='Mastercard' mod='buckaroo3'}</div>
        <div rel="booRow" class="pointer bankRadioBtn bk-credit-card"><input name="BPE_CreditCard" value="nexi" type="radio"
                                                              class="middle" /> <img
                    src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/creditcard/Nexi.png" class="middle bk-creditcard-logo"
                    /> {l s='Nexi' mod='buckaroo3'}</div>
        <div rel="booRow" class="pointer bankRadioBtn bk-credit-card"><input name="BPE_CreditCard" value="postepay" type="radio"
                                                              class="middle" /> <img
                    src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/creditcard/PostePay.png" class="middle bk-creditcard-logo"
                    /> {l s='PostePay' mod='buckaroo3'}</div>
        <div rel="booRow" class="pointer bankRadioBtn bk-credit-card"><input name="BPE_CreditCard" value="visa" type="radio"
                                                              class="middle" /> <img
                    src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/creditcard/VISA.png" class="middle bk-creditcard-logo"
                    /> {l s='VISA' mod='buckaroo3'}</div>
        <div rel="booRow" class="pointer bankRadioBtn bk-credit-card"><input name="BPE_CreditCard" value="visaelectron" type="radio"
                                                              class="middle" /> <img
                    src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/creditcard/VISAelectron.png" class="middle bk-creditcard-logo"
                    /> {l s='VISA Electron' mod='buckaroo3'}</div>
        <div rel="booRow" class="pointer bankRadioBtn bk-credit-card"><input name="BPE_CreditCard" value="vpay" type="radio"
                                                              class="middle" /> <img
                    src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/creditcard/VPAY.png" class="middle bk-creditcard-logo"
                    /> {l s='VPAY' mod='buckaroo3'}</div>        
        <br/>
    </form>
</section>