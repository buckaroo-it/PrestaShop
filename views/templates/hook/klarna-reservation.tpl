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
<div id="bk-klarna-reservation" class="card mt-2">
    <div class="card-header">
        <h3 class="card-header-title">
            {l s='Klarna reservation' mod='buckaroo3'}
        </h3>
    </div>
    <div class="card-body bk-klarna-reservation-body">
        <p>
            {l s='Reserve key' mod='buckaroo3'}:
            <strong>{$klarnaReservationKey|escape:'html':'UTF-8'}</strong>
        </p>
        {if $klarnaCancelSent}
            <div class="alert alert-info">
                {l s='Cancel reservation was sent. The order status updates when Buckaroo confirms it.' mod='buckaroo3'}
            </div>
        {else}
            <button type="button" class="btn btn-danger" id="bk-klarna-cancel">
                {l s='Cancel reservation' mod='buckaroo3'}
            </button>
            <button type="button" class="btn btn-outline-primary ml-1" id="bk-klarna-extend">
                {l s='Extend reservation' mod='buckaroo3'}
            </button>
        {/if}
        <input type="hidden" id="bk-klarna-order-id" value="{$klarnaOrderId|intval}" />
    </div>
</div>

<script>
    $(function() {
        $('#bk-klarna-cancel').click(function() {
            if (!confirm('{l s='Cancel this Klarna reservation?' mod='buckaroo3' js=1}')) {
                return;
            }
            buckarooKlarnaRequest(
                '{$klarnaCancelUrl|escape:'javascript':'UTF-8'}',
                '#bk-klarna-cancel'
            );
        });

        $('#bk-klarna-extend').click(function() {
            if (!confirm('{l s='Extend this Klarna reservation?' mod='buckaroo3' js=1}')) {
                return;
            }
            buckarooKlarnaRequest(
                '{$klarnaExtendUrl|escape:'javascript':'UTF-8'}',
                '#bk-klarna-extend'
            );
        });

        function buckarooKlarnaRequest(url, button) {
            $(button).prop('disabled', true);
            $.ajax({
                type: 'POST',
                url: url,
                data: {
                    orderId: $('#bk-klarna-order-id').val()
                },
                success: function(response) {
                    buckarooKlarnaAlert(response.message, response.error === true ? 'danger' : 'success');
                    location.replace(location.href);
                },
                error: function() {
                    buckarooKlarnaAlert('{l s='The Klarna request could not be sent.' mod='buckaroo3' js=1}');
                    $(button).prop('disabled', false);
                }
            });
        }

        function buckarooKlarnaAlert(message, type) {
            type = type || 'danger';
            $('.bk-klarna-alert-message').remove();
            if (message && message.length > 0) {
                $('<div>', {
                    'class': 'alert alert-' + type + ' bk-klarna-alert-message',
                    text: message
                }).prependTo('.bk-klarna-reservation-body');
            }
        }
    });
</script>
