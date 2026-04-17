{*
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
{if isset($buckarooAlreadyPaid) && $buckarooAlreadyPaid > 0}
<div id="buckaroo-already-paid-block" class="bk-already-paid-block">

    {* One line per gift card that was applied *}
    {if isset($buckarooGiftcardItems) && $buckarooGiftcardItems|@count > 0}
        {foreach $buckarooGiftcardItems as $item}
        <div class="cart-summary-line bk-giftcard-line">
            <span class="label">{$item.label|escape:'html':'UTF-8'}</span>
            <span class="value bk-giftcard-deduction">
                &minus;{$item.amount|string_format:"%.2f"}&nbsp;{$currency->sign|escape:'html':'UTF-8'}
            </span>
        </div>
        {/foreach}
    {else}
        <div class="cart-summary-line bk-giftcard-line">
            <span class="label">{l s='Paid with Giftcard' mod='buckaroo3'}</span>
            <span class="value bk-giftcard-deduction">
                &minus;{$buckarooAlreadyPaid|string_format:"%.2f"}&nbsp;{$currency->sign|escape:'html':'UTF-8'}
            </span>
        </div>
    {/if}

    {* Remaining amount line — shown prominently so the customer knows what to pay next *}
    {if isset($buckarooRemainingAmount) && $buckarooRemainingAmount > 0}
    <div class="cart-summary-line bk-remaining-amount-line">
        <span class="label"><strong>{l s='Remaining Amount' mod='buckaroo3'}</strong></span>
        <span class="value bk-remaining-amount">
            <strong>{$buckarooRemainingAmount|string_format:"%.2f"}&nbsp;{$currency->sign|escape:'html':'UTF-8'}</strong>
        </span>
    </div>
    {/if}

</div>
{/if}
