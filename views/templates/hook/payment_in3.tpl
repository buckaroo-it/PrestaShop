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
    <input type="hidden" name="buckarooKey" value="in3">
    <form name="booIn3Form" id="booIn3Form"
          action="{$link->getModuleLink('buckaroo3', 'request', ['method' => {$in3Method|escape:'html':'UTF-8'}])|escape:'quotes':'UTF-8'}" method="post" class="mb-2">

        {if empty($phone) && empty($phone_mobile)}
            <div id="booIn3Err" class="booBlAnimError">
                {l s='Phone number is required' mod='buckaroo3'}
            </div>

            <div class="row row-padding">
                <div class="col-xs-3">
                    <label class="required">{l s='Phone number' mod='buckaroo3'}:</label>
                </div>
                <div class="col-xs-9">
                    <input name="customer_phone" id="customer_phone" value="{$phone|escape:'html':'UTF-8'}" type="text" class="form-control bk-form-control-large"/>
                </div>
            </div>
        {else}
            {* Phone number is already available in the address information.
               Provide it as a hidden field so the checkout logic can still use it without asking again. *}
            <input type="hidden"
                   name="customer_phone"
                   id="customer_phone"
                   value="{if !empty($phone)}{$phone|escape:'html':'UTF-8'}{else}{$phone_mobile|escape:'html':'UTF-8'}{/if}"/>
        {/if}
            {if ($country == 'NL' && $methodsWithFinancialWarning['in3']) }
                <p class="small">
                    {l s=$methodsWithFinancialWarning['warningText'] sprintf=['in3'] mod='buckaroo3'}
                </p>
            {/if}
    </form>
</section>
