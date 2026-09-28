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
                    <small class="text-muted d-block">
                        {l s='The payment fee can be refunded from the partial refund form in the Products section above.' mod='buckaroo3'}
                        {l s='Still refundable:' mod='buckaroo3'}
                        {$buckaroo_fee_refundable|number_format:2:'.':','} {$currency->sign}
                    </small>
                {/if}
            </div>
        {/if}
    </div>
</div>

{if $buckaroo_fee_refundable > 0}
<script>
    (function () {
        var config = {
            fieldName: '{$buckaroo_fee_field_name|escape:'javascript':'UTF-8'}',
            max: {$buckaroo_fee_refundable|string_format:"%.2f"},
            label: '{l s='Payment fee' mod='buckaroo3'}',
            maxLabel: '{l s='Max' mod='buckaroo3'}',
            currency: '{$currency->sign|escape:'javascript':'UTF-8'}'
        };

        var ROW_ID = 'bk-fee-refund-row';
        var INPUT_ID = 'bk-fee-refund-amount';
        var MIRROR_ID = 'bk-fee-refund-amount-mirror';

        /**
         * PrestaShop renders the refund inputs inside the products table and only
         * reveals them once the merchant enables partial refund mode. Anchoring on
         * one of those inputs keeps this working across 1.7, 8 and 9, where the
         * surrounding markup differs.
         *
         * The amount input is the one to anchor on: quantity comes first in the
         * row, and the shipping input lives outside the product rows entirely.
         */
        function findAnchor() {
            var inputs = document.querySelectorAll('input[name^="cancel_product"]');
            var fallback = null;

            for (var i = 0; i < inputs.length; i++) {
                var name = (inputs[i].name || '').toLowerCase();

                if (name.indexOf('shipping') !== -1) {
                    continue;
                }

                if (name.indexOf('amount') !== -1) {
                    return inputs[i];
                }

                if (!fallback) {
                    fallback = inputs[i];
                }
            }

            return fallback;
        }

        function isVisible(element) {
            return !!element && element.offsetParent !== null;
        }

        /**
         * The value must reach the server with the native partial refund POST. The
         * input normally sits inside that form already; when the table is rendered
         * outside of it a hidden mirror is appended to the form instead.
         */
        function syncMirror(input, form) {
            if (input.form === form) {
                return;
            }

            var mirror = document.getElementById(MIRROR_ID);

            if (!mirror) {
                mirror = document.createElement('input');
                mirror.type = 'hidden';
                mirror.id = MIRROR_ID;
                mirror.name = config.fieldName;
                form.appendChild(mirror);
            }

            mirror.value = input.value;
        }

        function buildRow(anchor) {
            var sampleRow = anchor.closest('tr');
            var amountCell = anchor.closest('td');

            if (!sampleRow || !amountCell) {
                return null;
            }

            var cells = sampleRow.children;
            var amountIndex = Array.prototype.indexOf.call(cells, amountCell);
            var row = document.createElement('tr');

            row.id = ROW_ID;

            // One cell spanning every column left of the refund input. Counting
            // colspan matters: the product columns are merged, so a cell per child
            // would push the input out of the Partial refund column.
            var leadingSpan = 0;

            for (var i = 0; i < amountIndex; i++) {
                leadingSpan += cells[i].colSpan || 1;
            }

            if (leadingSpan > 0) {
                var labelCell = document.createElement('td');

                if (leadingSpan > 1) {
                    labelCell.colSpan = leadingSpan;
                }

                labelCell.textContent = config.label;
                row.appendChild(labelCell);
            }

            row.appendChild(buildAmountCell(amountCell));

            for (var j = amountIndex + 1; j < cells.length; j++) {
                var filler = document.createElement('td');
                var fillerSpan = cells[j].colSpan || 1;

                if (fillerSpan > 1) {
                    filler.colSpan = fillerSpan;
                }

                row.appendChild(filler);
            }

            return row;
        }

        /**
         * Copies the product row's own amount cell so the fee input lines up with
         * it exactly, then swaps in our input and our own maximum. Ids and js-*
         * hooks are dropped from the copy, otherwise PrestaShop's refund script
         * would treat the fee input as one of its product inputs.
         */
        function buildAmountCell(source) {
            var cell = source.cloneNode(true);
            var native = cell.querySelector('input');

            var input = document.createElement('input');
            input.type = 'number';
            input.id = INPUT_ID;
            input.name = config.fieldName;
            input.className = 'form-control';
            input.step = '0.01';
            input.min = '0';
            input.max = config.max.toFixed(2);
            input.value = '0.00';

            if (native) {
                native.parentNode.replaceChild(input, native);
            } else {
                cell.appendChild(input);
            }

            stripHooks(cell, input);

            // Everything following the input group is the native "(Max ...)" note.
            // Anchor on the input itself when it has no group, so the walk never
            // escapes the cell and starts removing neighbouring columns.
            var group = input.parentNode === cell ? input : input.parentNode;
            var hintClass = 'text-muted d-block';
            var sibling = group.nextElementSibling;

            while (sibling) {
                var next = sibling.nextElementSibling;
                hintClass = sibling.className || hintClass;
                sibling.parentNode.removeChild(sibling);
                sibling = next;
            }

            var hint = document.createElement('small');
            hint.className = hintClass;
            hint.textContent = '(' + config.maxLabel + ' ' + config.currency + config.max.toFixed(2) + ')';
            cell.appendChild(hint);

            return cell;
        }

        function stripHooks(cell, keep) {
            cell.removeAttribute('id');

            Array.prototype.forEach.call(cell.querySelectorAll('*'), function (element) {
                if (element === keep) {
                    return;
                }

                element.removeAttribute('id');
                element.removeAttribute('name');

                if (!element.className || typeof element.className !== 'string') {
                    return;
                }

                element.className = element.className.split(/\s+/).filter(function (name) {
                    return name.indexOf('js-') !== 0;
                }).join(' ');
            });
        }

        function clamp(input) {
            var value = parseFloat(input.value.replace(',', '.'));

            if (isNaN(value) || value < 0) {
                value = 0;
            }

            if (value > config.max) {
                value = config.max;
                input.value = value.toFixed(2);
            }
        }

        function ensureRow() {
            var anchor = findAnchor();

            if (!anchor) {
                return;
            }

            var existing = document.getElementById(ROW_ID);

            if (!existing) {
                var row = buildRow(anchor);
                var table = anchor.closest('table');
                var body = table ? table.querySelector('tbody') : null;

                if (!row || !body) {
                    return;
                }

                body.appendChild(row);
                existing = row;

                var input = document.getElementById(INPUT_ID);
                var form = anchor.form;

                input.addEventListener('input', function () {
                    clamp(input);

                    if (form) {
                        syncMirror(input, form);
                    }
                });

                if (form) {
                    form.addEventListener('submit', function () {
                        clamp(input);
                        syncMirror(input, form);
                    });
                }
            }

            // Follow the native refund inputs, which stay hidden until the merchant
            // switches the products table into partial refund mode.
            existing.style.display = isVisible(anchor.closest('td')) ? '' : 'none';
        }

        function start() {
            ensureRow();

            var anchor = findAnchor();
            var target = document.querySelector('#orderProductsPanel')
                || (anchor && anchor.closest('.card'))
                || document.body;

            new MutationObserver(function () {
                ensureRow();
            }).observe(target, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['class', 'style']
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', start);
        } else {
            start();
        }
    })();
</script>
{/if}
