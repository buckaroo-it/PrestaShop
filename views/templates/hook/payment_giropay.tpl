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
    <div id="booGiropayErr" class="booBlAnimError">
        {l s='Need to fill in Bic' mod='buckaroo3'}
    </div>
        <input type="hidden" name="buckarooKey" value="GIROPAY">
        <form name="booGiropayForm" id="booGiropayForm"
              action="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'giropay'])|escape:'quotes':'UTF-8'}" method="post">

            <div class="row row-padding">
                <div class="col-xs-3"><label class="required">{l s='BIC' mod='buckaroo3'}:</label></div>
                <div class="col-xs-9"><input name="BPE_Bic" id="BPE_Bic" value="" autocomplete="off" type="text"
                                             maxlength="11" class="form-control"/></div>
            </div>

            <br/>
        </form>
</section>
