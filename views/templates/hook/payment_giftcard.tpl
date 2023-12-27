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
    <form id="bk-giftcard-form" action="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'giftcard'])|escape:'quotes':'UTF-8'}" method="post">
        <p> {l s='Choose Giftcard to apply' mod='buckaroo3'}</p>
        <fieldset>
            <label for="gc_name">Giftcards</label>
            <select name="name" id="gc_name" class="form-control">
                {foreach from=$activeGiftcards item=giftcard}
                    <option value="{$giftcard->getCode()}">{$giftcard->getName()}</option>
                {/foreach}
            </select>
        </fieldset>
        <div class="row row-padding">
            <div class="col-xs-5">
                <label class="required" for="gc_card_number">
                    {l s='Card Number' mod='buckaroo3'}:
                </label>
            </div>
            <div class="col-xs-7">
                <input type="text" id="gc_card_number" name="gc_card_number" class="form-control bk-form-control-large">
            </div>
        </div>
        <div class="row row-padding">
            <div class="col-xs-5">
                <label class="required" for="gc_pin">
                    {l s='PIN / Security Code' mod='buckaroo3'}:
                </label>
            </div>
            <div class="col-xs-7">
                <input type="text" id="gc_pin" name="gc_pin" class="form-control bk-form-control-large">
            </div>
        </div>
        <button id="apply_giftcard_btn" class="btn btn-primary">Apply</button>
    </form>
    <br>
</section>
