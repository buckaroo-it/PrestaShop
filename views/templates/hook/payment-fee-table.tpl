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
                <td>{$buckaroo_fee.buckaroo_fee_tax_excl|price_format:$currency->sign}</td>
            </tr>
            <tr>
                <td>{l s='Fee tax' mod='buckaroo3'}</td>
                <td>{$buckaroo_fee.buckaroo_fee_tax|price_format:$currency->sign}</td>
            </tr>
            <tr>
                <td>{l s='Fee (incl. tax)' mod='buckaroo3'}</td>
                <td>{$buckaroo_fee.buckaroo_fee_tax_incl|price_format:$currency->sign}</td>
            </tr>
            </tbody>
        </table>
    </div>
</div>
