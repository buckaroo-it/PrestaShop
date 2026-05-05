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
 *     checkout order summary.
 *  2. After every Ajax cart-totals update (payment method switch): re-render
 *     those lines so they survive the DOM replacement done by BuckarooFeeManager.
 */

/* global $, buckarooAjaxUrl, buckarooAlreadyPaidData */

(function ($) {
    'use strict';

    var BuckarooAlreadyPaid = {

        /** Populated either from inline data (buckarooAlreadyPaidData) or Ajax. */
        data: {
            alreadyPaid: 0,
            remainingAmount: 0,
            giftcardItems: [],
            currencySign: ''
        },

        init: function () {
            // If the server already injected initial data via Media::addJsDef, use it.
            if (typeof buckarooAlreadyPaidData !== 'undefined' && buckarooAlreadyPaidData) {
                this.data = $.extend(this.data, buckarooAlreadyPaidData);
                this.render();
            } else {
                // Otherwise fetch from the same Ajax endpoint used for fee updates.
                this.fetchAndRender();
            }

            // Patch BuckarooFeeManager so we get giftcard data in every cart update.
            this.patchFeeManager();
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
        },

        /**
         * Monkey-patch BuckarooFeeManager.handleCartUpdate so we receive the
         * full Ajax response (including alreadyPaid / remainingAmount) after
         * every payment-method switch, without modifying buckaroo.js.
         */
        patchFeeManager: function () {
            var self = this;
            var maxAttempts = 20;
            var attempts = 0;
            var timer = setInterval(function () {
                attempts++;
                if (typeof BuckarooFeeManager !== 'undefined' && BuckarooFeeManager.handleCartUpdate) {
                    var original = BuckarooFeeManager.handleCartUpdate.bind(BuckarooFeeManager);
                    BuckarooFeeManager.handleCartUpdate = function (response) {
                        original(response);
                        self.updateFromResponse(response);
                        self.render();
                    };
                    clearInterval(timer);
                } else if (attempts >= maxAttempts) {
                    clearInterval(timer);
                }
            }, 150);
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
                    '<span class="label">' + (buckarooAlreadyPaidLabels ? buckarooAlreadyPaidLabels.paidWith : 'Paid with Giftcard') + '</span>' +
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

            // Remove any existing block first (handles re-renders after Ajax).
            $('#buckaroo-already-paid-block').remove();

            if (!html) {
                return;
            }

            // Preferred: insert before the grand-total / cart-total line.
            var $target = $('.cart-summary-totals .cart-total').first();
            if (!$target.length) {
                $target = $('.cart-summary-totals .cart-summary-line').last();
            }
            if ($target.length) {
                $target.before(html);
            } else {
                // Fallback: append to the whole cart-summary-totals block.
                var $totals = $('.card-block.cart-summary-totals, .cart-summary-totals').first();
                if ($totals.length) {
                    $totals.append(html);
                }
            }
        },

        escapeHtml: function (str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }
    };

    $(document).ready(function () {
        // Only run on the checkout page.
        if (!$('.checkout-process, #checkout, [data-view-name="checkout"]').length &&
            window.location.href.indexOf('order') === -1) {
            return;
        }
        BuckarooAlreadyPaid.init();
    });

})(jQuery);
