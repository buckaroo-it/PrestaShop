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
    <input type="hidden" name="buckarooKey" value="billink">
    <form id="booIdealForm" action="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'billink'])|escape:'quotes':'UTF-8'}" method="post" class="mb-2">
        {if $billink_show_coc}
        <div class="row row-padding">
                <div class="col-xs-5">
                    <label class="required">{l s='CoC-number' mod='buckaroo3'}:</label>
                </div>
                <div class="col-xs-7">
                    <input title="customerbillink-coc" name="customerbillink-coc"
                           id="customerbillink-coc" type="text" value="" required
                           class="form-control bk-form-control-large" autocomplete="off"/>
                </div>
            </div>
        {/if}
    </form>
</section>
