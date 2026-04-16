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
    {if $giftCardDisplayMode === 'separate'}
        {if $buckarooGiftcardApplied > 0}
            <div class="alert alert-success" style="margin-bottom:0;">
                <strong>{l s='Giftcard applied' mod='buckaroo3'}</strong><br>
                {l s='Deducted' mod='buckaroo3'}: &minus;{$buckarooGiftcardApplied|string_format:"%.2f"} {$currency->sign|escape:'html':'UTF-8'}<br>
                {if $buckarooGiftcardRemainder > 0}
                    {l s='Remaining to pay' mod='buckaroo3'}: {$buckarooGiftcardRemainder|string_format:"%.2f"} {$currency->sign|escape:'html':'UTF-8'}<br>
                    <em>{l s='Please select another payment method below to pay the remaining amount.' mod='buckaroo3'}</em>
                {/if}
            </div>
        {else}
            <input type="hidden" name="cardCode" value="{$cardCode|escape:'html':'UTF-8'}">
            <div class="row row-padding">
                <div class="col-sm-5">
                    <label for="giftcard_card_number" class="required">
                        {l s='Card Number' mod='buckaroo3'}:
                    </label>
                </div>
                <div class="col-sm-7">
                    <input type="text"
                           class="form-control bk-form-control-large"
                           id="giftcard_card_number"
                           name="giftcard_card_number"
                    >
                </div>
            </div>
            <div class="row row-padding">
                <div class="col-sm-5">
                    <label for="giftcard_security_code" class="required">
                        {l s='PIN / Security code' mod='buckaroo3'}:
                    </label>
                </div>
                <div class="col-sm-7">
                    <input type="text"
                           class="form-control bk-form-control-large"
                           id="giftcard_security_code"
                           name="giftcard_security_code"
                    >
                </div>
            </div>
            <p class="text-muted" style="margin-top:8px;">
                {l s='Please make sure all fields are filled in correctly before proceeding.' mod='buckaroo3'}
            </p>
        {/if}
    {/if}
</section>
