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
<div class="panel buckaroo-error-reporting-prompt" id="buckaroo-error-reporting-prompt">
    <div class="panel-heading">
        <i class="icon-bug"></i> {l s='Share error reports with Buckaroo?' mod='buckaroo3'}
    </div>
    <form method="post">
        <input type="hidden" name="submitBuckarooErrorReporting" value="1"/>
        <p>{l s='Help us improve the Buckaroo plugin by automatically sending a report to Buckaroo when the plugin runs into an error.' mod='buckaroo3'}</p>
        <p>{l s='Only plugin exceptions are sent: the exception class, message, file and line, the stack trace (without arguments), the plugin, PrestaShop and PHP versions, your shop URL, your Buckaroo website key and the test/live mode.' mod='buckaroo3'}</p>
        <p><strong>{l s='No customer personal data or card data is sent.' mod='buckaroo3'}</strong></p>
        <p class="help-block">{l s='You can change this choice at any time under Settings > Advanced settings.' mod='buckaroo3'}</p>
        <div class="panel-footer">
            <button type="submit" name="buckaroo_error_reporting" value="1" class="btn btn-primary pull-right">
                {l s='Yes, share error reports' mod='buckaroo3'}
            </button>
            <button type="submit" name="buckaroo_error_reporting" value="0" class="btn btn-default">
                {l s='No, thanks' mod='buckaroo3'}
            </button>
        </div>
    </form>
</div>
