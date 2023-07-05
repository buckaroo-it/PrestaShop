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
    <div id="booCapayableErr" class="booBlAnimError">
        {l s='Phone number is required' mod='buckaroo3'}
    </div>
        <input type="hidden" name="buckarooKey" value="CAPAYABLE">
        <form name="booCapayableForm" id="booCapayableForm"
              action="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'capayable'])|escape:'quotes':'UTF-8'}" method="post">

            <div class="row row-padding">
                <div class="col-xs-3">
                    <label class="required">{l s='Phone number' mod='buckaroo3'}:</label>
                </div>
                <div class="col-xs-9">
                    <input name="customer_phone" id="customer_phone" value="{$phone|escape:'html':'UTF-8'}" type="text" class="form-control"/>
                </div>
            </div>

            <br/>
        </form>
</section>
