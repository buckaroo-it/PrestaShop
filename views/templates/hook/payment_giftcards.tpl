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
    <input type="hidden" name="buckarooKey" value="giftcard">
    {if $giftCardDisplayMode === 'separate'}
        <form id="booGiftcardsForm-{$cardCode|escape:'html':'UTF-8'}"
              action="{if isset($giftcardFormAction)}{$giftcardFormAction|escape:'quotes':'UTF-8'}{else}{$link->getModuleLink('buckaroo3', 'applygiftcard', ['cardCode' => $cardCode])|escape:'quotes':'UTF-8'}{/if}"
              method="post">
            <input type="hidden" name="cardCode" value="{$cardCode|escape:'html':'UTF-8'}">
            <div class="row row-padding">
                <div class="col-sm-5">
                    <label for="giftcard_card_number_{$cardCode|escape:'html':'UTF-8'}" class="required">
                        {l s='Card Number' mod='buckaroo3'}:
                    </label>
                </div>
                <div class="col-sm-7">
                    <input type="text"
                           class="form-control bk-form-control-large"
                           id="giftcard_card_number_{$cardCode|escape:'html':'UTF-8'}"
                           name="giftcard_card_number"
                    >
                </div>
            </div>
            <div class="row row-padding">
                <div class="col-sm-5">
                    <label for="giftcard_security_code_{$cardCode|escape:'html':'UTF-8'}" class="required">
                        {l s='PIN / Security code' mod='buckaroo3'}:
                    </label>
                </div>
                <div class="col-sm-7">
                    <input type="text"
                           class="form-control bk-form-control-large"
                           id="giftcard_security_code_{$cardCode|escape:'html':'UTF-8'}"
                           name="giftcard_security_code"
                    >
                </div>
            </div>
            <p class="text-muted" style="margin-top:8px;">
                {l s='Please make sure all fields are filled in correctly before proceeding.' mod='buckaroo3'}
            </p>
        </form>
    {/if}
</section>
