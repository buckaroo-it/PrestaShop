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
    <input type="hidden" name="buckarooKey" value="clicktopay">
    <form id="booClickToPayForm" action="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'clicktopay'])|escape:'quotes':'UTF-8'}" method="post">
        <input type="hidden" name="clicktopay_identifier" id="bk_clicktopay_identifier" value="">
        <input type="hidden" name="clicktopay_transient_token" id="bk_clicktopay_transient_token" value="">

        {if !$clickToPayConfigured}
            <div class="bk-clicktopay-message">
                {l s='Click to Pay is not available at the moment. Please choose another payment method.' mod='buckaroo3'}
            </div>
        {else}
            <div id="booClickToPayErr" class="booBlAnimError">
                {l s='Please complete the Click to Pay checkout before continuing with payment.' mod='buckaroo3'}
            </div>

            <div id="bk-clicktopay-error" class="bk-clicktopay-message" style="display: none;"></div>

            {* The Buckaroo SDK renders the Click to Pay button and payment screen into these containers. *}
            <div id="bk-clicktopay-button" class="bk-clicktopay-button"></div>
            <div id="bk-clicktopay-screen" class="bk-clicktopay-screen"></div>
        {/if}
    </form>
</section>
