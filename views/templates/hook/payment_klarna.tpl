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
    <input type="hidden" name="buckarooKey" value="klarna">
    <form id="booIdealForm" action="{$link->getModuleLink('buckaroo3', 'request', ['method' => 'klarna'])|escape:'quotes':'UTF-8'}" method="post">
        <div class="row row-padding">
            <div class="col-xs-5">
                <label class="required">{l s='Please select gender:' mod='buckaroo3'}</label>
            </div>
            <div class="col-xs-7">
                <select name="bpe_klarna_person_gender"
                        id="bpe_klarna_person_gender"
                        class="required-entry form-control form-control-large mb-2">
                    <option value="1" selected="selected" >{l s='He/him' mod='buckaroo3'}</option>
                    <option value="2">{l s='She/her' mod='buckaroo3'}</option>
                </select>
            </div>
        </div>
    </form>
</section>