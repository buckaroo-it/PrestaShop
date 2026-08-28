/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * It is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * @author    Buckaroo.nl <plugins@buckaroo.nl>
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *
 * BuckarooAlreadyPaid
 * -------------------
 * Mirrors Magento 2's already-paid.js KnockoutJS component for PrestaShop.
 *
 * Responsibilities:
 *  1. On page load: fetch giftcard partial-payment state from the server and
 *     inject "Paid with X Giftcard" / "Remaining Amount" lines into the
 *     checkout order summary (and update the visible grand total).
 *  2. After every Ajax cart-totals update (payment method switch): re-render
 *     those lines so they survive the DOM replacement done by BuckarooFeeManager.
 */

/* global $, buckarooAjaxUrl, buckarooAlreadyPaidData, buckarooAlreadyPaidLabels, prestashop */

(function ($) {
    'use strict';

    var BuckarooAlreadyPaid = {

        /** Populated either from inline data (buckarooAlreadyPaidData) or Ajax. */
        data: {
            alreadyPaid: 0,
            remainingAmount: 0,
            giftcardItems: [],
            currencySign: '',
            cartTotal: 0
        },

        init: function () {
            // Prefer server-injected data, then always refresh from Ajax so
            // cookie/DB state after applygiftcard redirect is never stale.
            if (typeof buckarooAlreadyPaidData !== 'undefined' && buckarooAlreadyPaidData) {
                this.data = $.extend(this.data, buckarooAlreadyPaidData);
                this.render();
            }

            this.fetchAndRender();
            this.bindEvents();
        },

        handleCartUpdate: function (response) {
            this.updateFromResponse(response);
            this.render();
        },

        bindEvents: function () {
            var self = this;
            if (typeof prestashop !== 'undefined' && prestashop.on) {
                prestashop.on('updatedCart', function () {
                    self.fetchAndRender();
                });
                prestashop.on('updatedDeliveryForm', function () {
                    self.fetchAndRender();
                });
            }

            // Fallback when payment option changes before buckaroo.js finishes loading.
            $(document).on('change', 'input[name="payment-option"]', function () {
                setTimeout(function () {
                    self.fetchAndRender();
                }, 250);
            });

            // After returning from applygiftcard, re-fetch once more shortly
            // after DOM/checkout widgets finish painting.
            if (window.location.search.indexOf('buckaroo_giftcard_applied') !== -1) {
                setTimeout(function () {
                    self.fetchAndRender();
                }, 400);
            }
        },

        /**
         * Fetch current giftcard totals and render.
         * Uses the existing getTotalCartPrice action; no extra endpoint needed.
         */
        fetchAndRender: function () {
            if (typeof buckarooAjaxUrl === 'undefined' || !buckarooAjaxUrl) {
                return;
            }
            var self = this;
            $.ajax({
                url: buckarooAjaxUrl,
                method: 'GET',
                data: { ajax: 1, action: 'getTotalCartPrice' },
                dataType: 'json',
                success: function (response) {
                    self.updateFromResponse(response);
                    self.render();
                }
            });
        },

        /**
         * Extract giftcard fields from the cart-update Ajax response and re-render.
         */
        updateFromResponse: function (response) {
            if (!response) return;
            if (typeof response.alreadyPaid !== 'undefined') {
                this.data.alreadyPaid = parseFloat(response.alreadyPaid) || 0;
            }
            if (typeof response.remainingAmount !== 'undefined') {
                this.data.remainingAmount = parseFloat(response.remainingAmount) || 0;
            }
            if (response.giftcardItems && $.isArray(response.giftcardItems)) {
                this.data.giftcardItems = response.giftcardItems;
            }
            if (response.currencySign) {
                this.data.currencySign = response.currencySign;
            }
        },

        /**
         * Build the HTML for the "Paid with X" and "Remaining Amount" lines.
         */
        buildHtml: function () {
            if (this.data.alreadyPaid <= 0) {
                return '';
            }

            var sign = this.data.currencySign || '';
            var html = '<div id="buckaroo-already-paid-block" class="bk-already-paid-block">';

            if (this.data.giftcardItems && this.data.giftcardItems.length > 0) {
                for (var i = 0; i < this.data.giftcardItems.length; i++) {
                    var item = this.data.giftcardItems[i];
                    html += '<div class="cart-summary-line bk-giftcard-line">' +
                        '<span class="label">' + this.escapeHtml(item.label) + '</span>' +
                        '<span class="value bk-giftcard-deduction">' +
                        '&minus;' + parseFloat(item.amount).toFixed(2) + '&nbsp;' + sign +
                        '</span>' +
                        '</div>';
                }
            } else {
                html += '<div class="cart-summary-line bk-giftcard-line">' +
                    '<span class="label">' + (typeof buckarooAlreadyPaidLabels !== 'undefined' ? buckarooAlreadyPaidLabels.paidWith : 'Paid with Giftcard') + '</span>' +
                    '<span class="value bk-giftcard-deduction">' +
                    '&minus;' + this.data.alreadyPaid.toFixed(2) + '&nbsp;' + sign +
                    '</span>' +
                    '</div>';
            }

            if (this.data.remainingAmount > 0) {
                html += '<div class="cart-summary-line bk-remaining-amount-line">' +
                    '<span class="label"><strong>' +
                    (typeof buckarooAlreadyPaidLabels !== 'undefined' ? buckarooAlreadyPaidLabels.remaining : 'Remaining Amount') +
                    '</strong></span>' +
                    '<span class="value bk-remaining-amount"><strong>' +
                    this.data.remainingAmount.toFixed(2) + '&nbsp;' + sign +
                    '</strong></span>' +
                    '</div>';
            }

            html += '</div>';
            return html;
        },

        /**
         * Inject or refresh the already-paid block in the checkout summary.
         * Targets the cart-summary-totals area and inserts before the grand-total line.
         */
        render: function () {
            var html = this.buildHtml();

            // Keep server-rendered payment-top banner; only refresh summary clones.
            $('.cart-summary-totals #buckaroo-already-paid-block, #js-checkout-summary #buckaroo-already-paid-block').remove();
            $('.js-cart-summary-totals #buckaroo-already-paid-block').remove();
            if (!$('#checkout-payment-step #buckaroo-already-paid-block').length
                && !$('.payment-options #buckaroo-already-paid-block').length) {
                $('#buckaroo-already-paid-block').filter(function () {
                    return $(this).closest('.cart-summary-totals, #js-checkout-summary, .js-cart-summary-totals, .cart-summary__total').length > 0;
                }).remove();
            }

            if (html) {
                var $target = this.findSummaryInjectionTarget();
                if ($target && $target.length) {
                    $target.before(html);
                }
            }

            this.updateGrandTotalDisplay();
        },

        /**
         * Locate where to inject giftcard summary lines (theme-aware).
         */
        findSummaryInjectionTarget: function () {
            var selectors = [
                '.js-cart-summary-totals .cart-summary__line--bold',
                '.cart-summary__total .cart-summary__line--bold',
                '.cart-summary-totals .cart-total',
                '#js-checkout-summary .cart-summary-totals .cart-summary-line',
                '.cart-summary-totals .cart-summary-line'
            ];

            for (var i = 0; i < selectors.length; i++) {
                var $target = $(selectors[i]).first();
                if ($target.length) {
                    return $target;
                }
            }

            return $('.card-block.cart-summary-totals, .cart-body.cart-summary-totals, .cart-summary-totals, .js-cart-summary-totals').first();
        },

        /**
         * Find grand-total value nodes across Classic and Hummingbird checkout themes.
         */
        findGrandTotalValueElements: function () {
            var $result = $();
            var $containers = $('.js-cart-summary-totals, .cart-summary__total, .cart-summary-totals, #js-checkout-summary .cart-summary-totals');

            $containers.each(function () {
                var $container = $(this);
                var $match = $();

                $container.find('.cart-summary__line--bold, .cart-summary-line.cart-total').each(function () {
                    var $line = $(this);
                    var label = $line.find('.cart-summary__label, .label').text().toLowerCase();

                    if (label.indexOf('total') !== -1
                        && (label.indexOf('incl') !== -1
                            || label.indexOf('included') !== -1
                            || label.indexOf('ttc') !== -1)) {
                        $match = $line.find('.cart-summary__value, .value').first();
                        return false;
                    }
                });

                if (!$match.length) {
                    $match = $container.find('.cart-summary__line--bold, .cart-summary-line.cart-total').first()
                        .find('.cart-summary__value, .value').first();
                }

                if ($match.length) {
                    $result = $result.add($match);
                }
            });

            if (!$result.length) {
                $result = $('.cart-summary-totals .cart-total .value, .cart-summary-totals .cart-total span.value');
            }

            return $result;
        },

        formatRemainingTotal: function (amount, sign) {
            if (typeof prestashop !== 'undefined'
                && prestashop.currency
                && typeof prestashop.formatCurrency === 'function') {
                try {
                    return prestashop.formatCurrency(amount, prestashop.currency.iso_code);
                } catch (e) {
                    // Fall back to manual formatting below.
                }
            }

            return amount.toFixed(2) + '&nbsp;' + this.escapeHtml(sign || '');
        },

        /**
         * Replace the visible checkout grand total with the remaining amount
         * after giftcard deductions (keeps the original amount in a data attr).
         */
        updateGrandTotalDisplay: function (retries) {
            retries = typeof retries === 'number' ? retries : 0;

            var self = this;
            var $values = this.findGrandTotalValueElements();

            if (!$values.length) {
                if (retries < 12) {
                    setTimeout(function () {
                        self.updateGrandTotalDisplay(retries + 1);
                    }, 200);
                }
                return;
            }

            $values.each(function () {
                var $value = $(this);

                if (!$value.attr('data-buckaroo-original-total')) {
                    $value.attr('data-buckaroo-original-total', $value.html());
                }

                if (self.data.alreadyPaid > 0 && self.data.remainingAmount >= 0) {
                    $value.html(self.formatRemainingTotal(self.data.remainingAmount, self.data.currencySign || ''));
                    $value.attr('data-buckaroo-remaining', '1');
                } else {
                    $value.html($value.attr('data-buckaroo-original-total'));
                    $value.removeAttr('data-buckaroo-remaining');
                }
            });
        },

        escapeHtml: function (str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }
    };

    window.BuckarooAlreadyPaid = BuckarooAlreadyPaid;

    $(document).ready(function () {
        // Only run on the checkout page.
        if (!$('.checkout-process, #checkout, [data-view-name="checkout"]').length &&
            window.location.href.indexOf('order') === -1) {
            return;
        }
        BuckarooAlreadyPaid.init();
    });

})(jQuery);
