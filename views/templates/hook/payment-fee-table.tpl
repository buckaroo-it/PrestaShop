<div class="card mt-2">
    <div class="card-header">
        <h3 class="card-header-title">
            {l s='Payment Fee Details' mod='buckaroo3'}
        </h3>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
            <tr>
                <th>{l s='Description' mod='buckaroo3'}</th>
                <th>{l s='Amount' mod='buckaroo3'}</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>{l s='Fee (excl. tax)' mod='buckaroo3'}</td>
                <td>{$buckaroo_fee.buckaroo_fee_tax_excl|number_format:2:'.':','} {$currency->sign}</td>
            </tr>
            <tr>
                <td>{l s='Fee tax' mod='buckaroo3'}</td>
                <td>{$buckaroo_fee.buckaroo_fee_tax|number_format:2:'.':','} {$currency->sign}</td>
            </tr>
            <tr>
                <td>{l s='Fee (incl. tax)' mod='buckaroo3'}</td>
                <td>{$buckaroo_fee.buckaroo_fee_tax_incl|number_format:2:'.':','} {$currency->sign}</td>
            </tr>
            </tbody>
        </table>

        {if $buckaroo_fee.buckaroo_fee_tax_incl > 0}
        <div class="mt-3">
            {if $buckaroo_fee_refunded}
                <span class="badge badge-success">{l s='Payment fee has been refunded' mod='buckaroo3'}</span>
            {else}
                <div class="custom-control custom-checkbox">
                    <input type="checkbox"
                           class="custom-control-input"
                           id="bk-include-fee-refund"
                           {if $buckaroo_fee_flag_set}checked="checked"{/if}>
                    <label class="custom-control-label" for="bk-include-fee-refund">
                        {l s='Include payment fee' mod='buckaroo3'}
                        ({$buckaroo_fee.buckaroo_fee_tax_incl|number_format:2:'.':','} {$currency->sign})
                        {l s='in next partial refund' mod='buckaroo3'}
                    </label>
                </div>
                <small class="text-muted d-block mt-1">
                    {l s='When checked, the payment fee will be added to the amount sent to Buckaroo when you submit a partial refund via PrestaShop.' mod='buckaroo3'}
                </small>
            {/if}
        </div>

        <script>
            $(function () {
                $('#bk-include-fee-refund').on('change', function () {
                    var include = $(this).is(':checked') ? 1 : 0;
                    $.post(
                        '{$buckaroo_set_fee_flag_url|escape:'html':'UTF-8'}',
                        {
                            orderId: {$orderId|intval},
                            include: include
                        }
                    );
                });
            });
        </script>
        {/if}
    </div>
</div>
