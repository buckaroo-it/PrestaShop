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
            {l s='Buckaroo payments & refunds' mod='buckaroo3'} ({$order->getOrderPayments()|@count|escape:'quotes':'UTF-8'})
        </h3>
    </div>
    {if $messages != ''}
        <script>
            $(".bootstrap").after(function () {
                {if $messageStatus == 0}
                return "<div class='alert alert-danger'>{$messages|escape:'html':'UTF-8'}</div>";
                {else}
                return "<div class='alert alert-success'>{$messages|escape:'html':'UTF-8'}</div>";
                {/if}
            });

        </script>
    {/if}
    <div class="card-body">
        <table class="table">
            <thead>
            <tr>
                <th><span class="title_box ">{l s='Date' mod='buckaroo3'}</span></th>
                <th><span class="title_box ">{l s='Payment method' mod='buckaroo3'}</span></th>
                <th><span class="title_box ">{l s='Transaction ID' mod='buckaroo3'}</span></th>
                <th><span class="title_box ">{l s='Amount' mod='buckaroo3'}</span></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            {foreach from=$payments item=payment}
                <tr>
                    <td>{dateFormat date=$payment->date_add full=true}</td>
                    <td>{$payment->payment_method|escape:'html':'UTF-8'}</td>
                    <td>{$payment->transaction_id|escape:'html':'UTF-8'}</td>
                    <td><input class="buckaroo_part_refund_amount" {if $payment->amount <  0} disabled="disabled" {/if} type="number" step="0.01" max="{$paymentInfo[$payment->id]['available_amount']|escape:'html':'UTF-8'}" value="{$paymentInfo[$payment->id]['available_amount']|escape:'html':'UTF-8'}"></td>
                    <td class="actions">
                        {if $payment->payment_method == 'Group transaction'}
                            Group transaction
                        {elseif $payment->amount > 0 && $paymentInfo[$payment->id]['available_amount'] == $payment->amount}
                            <button class="btn btn-sm btn-outline-secondary open_payment_information">
                                {l s='Details' mod='buckaroo3'}
                            </button>
                        {elseif $payment->amount > 0 && $paymentInfo[$payment->id]['available_amount'] === 0}
                            Fully refunded
                        {elseif $payment->amount > 0 && $paymentInfo[$payment->id]['available_amount'] > 0}
                            <button class="btn btn-sm btn-outline-secondary open_payment_information">
                                {l s='Partially refunded' mod='buckaroo3'}
                            </button>
                        {else}
                            {l s='Refund transaction' mod='buckaroo3'}
                            
                        {/if}
                    </td>
                </tr>
                <tr class="payment_information" style="display: none;">
                    <td colspan="4">
                        {if $payment->amount > 0 && $payment->transaction_id}
                            <button style="width: 190px"
                               type="button"
                               class="btn btn-primary btn-block buckaroo_part_refund_link"
                               data-max-amount="{$paymentInfo[$payment->id]['available_amount']|escape:'html':'UTF-8'}"
                               data-trxid = "{$payment->transaction_id|escape:'html':'UTF-8'}"
                            >
                            {l s='Refund' mod='buckaroo3'}
                            </button>
                        {else}
                            {l s='Transaction can\'t be refunded' mod='buckaroo3'}
                        {/if}
                    </td>
                </tr>
                {foreachelse}
                <tr>
                    <td class="list-empty hidden-print" colspan="5">
                        <div class="list-empty-msg">
                            <i class="icon-warning-sign list-empty-icon"></i>
                            {l s='No payment methods are available' mod='buckaroo3'}
                        </div>
                    </td>
                </tr>
            {/foreach}
            </tbody>
        </table>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        $('#formAddPaymentPanel .open_payment_information').on('click',function(){
            $(this).closest('tr').next('tr.payment_information').toggle();
        });
        $('.buckaroo_part_refund_link').on('click', function() {
            let amount = $(this)
            .closest('tr')
            .prev('tr')
            .find('.buckaroo_part_refund_amount')
            .val();
            let max_amount = $(this).data('max-amount');
            let transaction_id = $(this).data('trxid');
            if (amount > max_amount) {
                amount = max_amount;
            }

            let link = "{$refundLink|escape:'html':'UTF-8'}&action=refund&transaction_id=" +transaction_id + "&id_order={$order->id|escape:'html':'UTF-8'}&refund_amount=" + amount;
            let confirmMessage = "{l s='Are you sure want to refund amount ?' mod='buckaroo3'}".replace('amount', amount)
           
            if (confirm(confirmMessage)) {
                $(this).prop('disabled', true);
                window.location = link;
            }
        })
        {if $buckarooFee != ''}
            $('#total_order').before('<tr><td class=text-right>Buckaroo Fee</td><td class="amount text-right nowrap">{$buckarooFee|escape:'html':'UTF-8'}</td></tr>');
        {/if}
    });
</script> 
