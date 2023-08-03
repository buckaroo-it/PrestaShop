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
<div id="formAddPaymentPanel" class="card mt-2">
    <div class="card-header">
        <h3 class="card-header-title">
            {l s='Buckaroo refunds' mod='buckaroo3'}
        </h3>
    </div>

    <div class="card-body bk-refund-body">
        <label for="bk-refund-amount">{l s='Refund amount' mod='buckaroo3'}</label>
        <div class="input-group mb-3">
            <input type="number" name="refund-amount" id="bk-refund-amount" class="form-control"
                placeholder="{$maxAvailableAmount|escape:'html':'UTF-8'}">
            <div class="input-group-append">
                <button type="button" class="btn btn-primary" id="bk-btn-refund">{l s='Refund' mod='buckaroo3'}</button>
            </div>
        </div>
        <input type="hidden" id="bk-order-id" value="{$orderId|escape:'html':'UTF-8'}" />
        <small>{l s='Max amount available' mod='buckaroo3'} <strong>{$maxAvailableAmount|escape:'html':'UTF-8'}</strong></small>
        <hr>

        <h4>{l s='Previous Refunds' mod='buckaroo3'}</h4>
        <table class="table">
            <thead>
                <tr>
                    <th><span class="title_box ">{l s='Transaction ID' mod='buckaroo3'}</span></th>
                    <th class="text-center"><span class="title_box ">{l s='Status' mod='buckaroo3'}</span></th>
                    <th class="text-right"><span class="title_box ">{l s='Amount' mod='buckaroo3'}</span></th>
                    <th class="text-right"><span class="title_box ">{l s='Date' mod='buckaroo3'}</span></th>
                </tr>
            </thead>
            <tbody>
                {foreach from=$refunds item=refund}
                    <tr>

                        <td><a href="https://plaza.buckaroo.nl/Transaction/Transactions/Details?transactionKey={$refund->getKey()|escape:'html':'UTF-8'}"
                                target="_blank">{$refund->getKey()|escape:'html':'UTF-8'}<a></td>
                        <td class="text-center">
                            <div
                                class="badge{if ($refund->getStatus() === 'success')} badge-success {else} badge-danger {/if}">
                                {$refund->getStatus()|escape:'html':'UTF-8'}
                            </div>
                        </td>
                        <td class="text-right">
                            {Tools::getContextLocale(Context::getContext())->formatPrice($refund->getAmount(), Currency::getIsoCodeById($currencyId))|escape:'html':'UTF-8'}
                        </td>
                        <td class="text-right">{dateFormat date=$refund->getCreatedAt()->format('Y-m-d H:i:s') full=true}
                        </td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
</div>

<script>
    $(function() {
        $('#bk-btn-refund').click(function() {
            var refundAmount = Number($('#bk-refund-amount').val());
            if (refundAmount === 0) {
                alert('{l s="A refund amount grater than 0 is required" mod="buckaroo3"}');
                return;
            }
            if (
                confirm('{l s="Are you sure you want to do this refund?" mod="buckaroo3"}')

            ) {
                buckarooToggleRefundButton(true);
                $.ajax({
                    type: 'POST',
                    url: '{$ajaxUrl|escape:'html':'UTF-8'}',
                    data: {
                        orderId: $('#bk-order-id').val(),
                        refundAmount: refundAmount
                    },
                    success: function(response) {
                        buckarooAlert(response.message, response.error === true ? 'danger' :
                            'success');
                        buckarooToggleRefundButton(false);
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        buckarooAlert('{l s="An ajax error occured while refunding" mod="buckaroo3"}');
                        buckarooToggleRefundButton(false);
                    }
                })
            }

        });

        function buckarooToggleRefundButton(disabled) {
            $('#bk-btn-refund').prop('disabled', disabled);
        }

        function buckarooAlert(message, type = 'danger') {
            $('.bk-refund-alert-message').remove();
            if (message && message.length > 0) {
                const parent = $('.bk-refund-body');
                parent.prepend(
                    '<div class="alert alert-' + type + ' bk-refund-alert-message">' + message + '</div>'
                );
            }
        }
    })
</script>