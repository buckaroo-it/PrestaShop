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
    <form id="bk-ideal-form" action="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'ideal'])|escape:'quotes':'UTF-8'}" method="post">
       <p> {l s='Choose your bank' mod='buckaroo3'}</p>

        {foreach $idealIssuers as $key => $issuer}
            <div rel="booRow" class="bk-ideal-issuer">
                <input 
                    name="BPE_Issuer"
                    id="ideal_issuer_{$issuer['id']}"
                    value="{$issuer['id']}"
                    type="radio"
                    {if $key == 0} checked="checked" {/if}
                />
                <label for="ideal_issuer_{$issuer['id']}" class="bk-issuer-label">
                    {if isset($issuer['logo']) && $issuer['logo'] !== null}
                        <img
                        class="bk-issuer-logo"
                        src="{$this_path|escape:'quotes':'UTF-8'}views/img/buckaroo_images/ideal/{$issuer['logo']}"
                        />
                    {/if}
                    {l s=$issuer['name'] mod='buckaroo3'}
                </label>
            </div>
        {/foreach}

    </form>
</section>