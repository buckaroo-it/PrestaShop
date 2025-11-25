/*
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
 */

class BuckarooFeeManager {
    init() {
        this.$cartSubtotalBuckarooFee = $('#cart-subtotal-buckarooFee');
        this.$cartSubtotalBuckarooFeeTax = $('#cart-subtotal-buckarooFeeTax');
        this.$cartSummarySubtotalsContainer = $('.cart-summary-subtotals-container');
    }

    handlePaymentOptionChange($paymentOption) {

        let paymentOptionId = $paymentOption.attr('id');
        let $paymentOptionContainer = $paymentOption.closest('.payment-option');

        let buckarooKey = null;

        buckarooKey = $paymentOptionContainer.find('input[name="buckarooKey"]').val();

        if (!buckarooKey) {
            let $nextSibling = $paymentOptionContainer.next();
            if ($nextSibling.length) {
                buckarooKey = $nextSibling.find('input[name="buckarooKey"]').val();
            }
        }

        if (!buckarooKey) {
            let $formContainer = $paymentOptionContainer.find('.js-payment-option-form, .payment-option-form, .additional-information');
            if ($formContainer.length) {
                buckarooKey = $formContainer.find('input[name="buckarooKey"]').val();
            }
        }

        if (!buckarooKey) {
            let $form = $paymentOptionContainer.find('form');
            if (!$form.length) {
                $form = $paymentOptionContainer.next().find('form');
            }
            if ($form.length) {
                let formAction = $form.attr('action') || '';
                let methodMatch = formAction.match(/[?&]method=([^&]+)/i);
                if (methodMatch && methodMatch[1]) {
                    buckarooKey = decodeURIComponent(methodMatch[1]).toLowerCase();
                    // Remove any query parameters after the method name
                    if (buckarooKey.includes('&')) {
                        buckarooKey = buckarooKey.split('&')[0];
                    }
                }
            }
        }

        if (!buckarooKey && paymentOptionId) {
            let moduleName = $paymentOptionContainer.find('[data-module-name]').attr('data-module-name');
            if (moduleName) {
                buckarooKey = moduleName.toLowerCase();
            } else {
                let idParts = paymentOptionId.split('-');
                let knownMethods = ['ideal', 'creditcard', 'paypal', 'afterpay', 'billink', 'klarna', 'paybybank', 'sepadirectdebit', 'giftcard', 'in3', 'afterpay', 'bancontact', 'belfius', 'eps', 'mbway', 'multibanco', 'payconiq'];
                for (let method of knownMethods) {
                    if (paymentOptionId.toLowerCase().indexOf(method) !== -1) {
                        buckarooKey = method;
                        break;
                    }
                }
            }
        }

        if (!buckarooKey) {
            let $allBuckarooInputs = $paymentOptionContainer.closest('form, .payment-options, .payment-methods').find('input[name="buckarooKey"]');
            if ($allBuckarooInputs.length === 1) {
                buckarooKey = $allBuckarooInputs.val();
            }
        }
        
        let paymentFee = null;

        if (buckarooKey && typeof buckarooFees !== 'undefined' && buckarooFees && buckarooFees[buckarooKey] !== undefined) {
            paymentFee = buckarooFees[buckarooKey]['buckarooFee'];
        }

        if (!paymentFee) {
            let $feeInput = $paymentOptionContainer.find('input[name="payment-fee-price"]');
            if (!$feeInput.length) {
                $feeInput = $paymentOptionContainer.next().find('input[name="payment-fee-price"]');
            }
            if (!$feeInput.length) {
                $feeInput = $paymentOptionContainer.find('.additional-information input[name="payment-fee-price"]');
            }
            if ($feeInput.length) {
                paymentFee = $feeInput.val();
            }
        }

        // Handle empty or null values
        if (!paymentFee || paymentFee === '' || paymentFee === 0 || paymentFee === '0' || paymentFee === null || paymentFee === undefined) {
            this.removePaymentFee();
            this.updateCartSummary('');
        } else {
            this.updateCartSummary(String(paymentFee).trim());
        }
    }

    updateCartSummary(paymentFee) {
        
        if (!buckarooAjaxUrl) {
            console.error('buckarooAjaxUrl is not defined!');
            return;
        }
        
        $.ajax({
            url: buckarooAjaxUrl,
            method: 'GET',
            data: {'paymentFee': paymentFee, ajax: 1, action: 'getTotalCartPrice'},
            dataType: 'json',
            success: (response) => {
                this.handleCartUpdate(response);
            },
            error: function(xhr, status, error) {
                console.error('Error updating cart summary:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error
                });
                if (xhr.responseText) {
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        console.error('Error response:', errorResponse);
                    } catch (e) {
                        console.error('Response is not valid JSON:', xhr.responseText);
                    }
                }
            }
        });
    }

    handleCartUpdate(response) {
        if (!response || typeof response !== 'object') {
            console.error('Invalid response format - expected object, got:', typeof response, response);
            return;
        }

        if (response.error) {
            console.error('Error in response:', response.message || 'Unknown error');
            return;
        }

        const { cart_summary_totals, paymentFee, paymentFeeTax, includedTaxes } = response;
        const $cartSummaryTotals = $('.card-block.cart-summary-totals');

        if (!$cartSummaryTotals.length) {
            console.warn('Cart summary totals container not found');
            return;
        }

        if (cart_summary_totals) {
            const $newCartSummaryTotals = $(cart_summary_totals);
            $cartSummaryTotals.replaceWith($newCartSummaryTotals);
        }

        if (paymentFee) {
            this.updatePaymentFeeDisplay(paymentFee, paymentFeeTax, includedTaxes);
        } else {
            this.removePaymentFee();
        }
    }

    updatePaymentFeeDisplay(paymentFee, paymentFeeTax, includedTaxes) {
        const paymentFeeHtml = `<div class="cart-summary-line cart-summary-subtotals" id="cart-subtotal-buckarooFee">
                                    <span class="label">${paymentFeeLabel}</span>
                                    <span class="value">${paymentFee}</span>
                                </div>`;

        const paymentFeeTaxHtml = `<div class="cart-summary-line cart-summary-subtotals" id="cart-subtotal-buckarooFeeTax">
                                    <span class="label">${paymentFeeLabel} Tax</span>
                                    <span class="value">${paymentFeeTax}</span>
                                </div>`;

        if (includedTaxes) {
            this.updateIncludedTaxes(includedTaxes);
        }

        if (this.$cartSubtotalBuckarooFee.length === 0) {
            this.$cartSummarySubtotalsContainer.append(paymentFeeHtml);
        } else {
            this.$cartSubtotalBuckarooFee.html(paymentFeeHtml);
        }

        if (this.$cartSubtotalBuckarooFeeTax.length === 0) {
            this.$cartSummarySubtotalsContainer.append(paymentFeeTaxHtml);
        } else {
            this.$cartSubtotalBuckarooFeeTax.html(paymentFeeTaxHtml);
        }
    }

    updateIncludedTaxes(includedTaxes) {
        const includedTaxesHtml = `<div class="cart-summary-line included-taxes">
                                    <span class="label sub">${includedTaxes.label}</span>
                                    <span class="value sub">${includedTaxes.value}</span>
                                </div>`;

        const $includedTaxesElement = $('.cart-summary-totals .included-taxes');
        if ($includedTaxesElement.length) {
            $includedTaxesElement.replaceWith(includedTaxesHtml);
        } else {
            // Locate the original "Included taxes" element and replace it
            const $originalIncludedTaxesElement = $('.cart-summary-totals .cart-summary-line').filter(function() {
                return $(this).find('.label.sub').text().includes(includedTaxes.label);
            });

            if ($originalIncludedTaxesElement.length) {
                $originalIncludedTaxesElement.replaceWith(includedTaxesHtml);
            }
        }
    }

    removePaymentFee() {
        $('#cart-subtotal-buckarooFee').remove();
        $('#cart-subtotal-buckarooFeeTax').remove();
    }
}

// Run when DOM is ready
$(document).ready(function() {
    buckaroo();
});

function buckaroo() {
    const buckarooFeeManager = new BuckarooFeeManager();
    buckarooFeeManager.init();

    $(document).on('click', 'input[name="payment-option"]', function() {
        methodValidator.setMethod($(this).attr('id'));
        setTimeout(() => {
            buckarooFeeManager.handlePaymentOptionChange($(this));
        }, 100);
    });

    $(document).on('change', 'input[name="payment-option"]', function() {
        buckarooFeeManager.handlePaymentOptionChange($(this));
    });

    $('#payment-confirmation button').on('click', (e) => {
        methodValidator.init(e);
    });

    const $selectedOption = $('input[name="payment-option"]:checked');
    if ($selectedOption.length) {
        setTimeout(() => {
            buckarooFeeManager.handlePaymentOptionChange($selectedOption);
        }, 500);
    }

    const methodValidator = {
        formPointer: null,
        methodSelector: null,
        valid: true,
        setMethod: (id) => {
            methodValidator.formPointer = $('#pay-with-' + id + '-form form');
            methodValidator.methodSelector = methodValidator.formPointer.attr('action').split('method=')[1];
        }, requiredAll: () => {
            methodValidator.formPointer.find('label.required').parent().nextAll().find('input').not('.buckaroo-validation-message').each(function () {
                let invalid = !validateRequired($(this).val());
                if (invalid === true) {
                    methodValidator.valid = false;
                }
                methodValidator.displayMessage($(this), buckarooMessages.validation.required, !invalid);
            });
        }, displayMessage: (element, message, valid) => {
            element.toggleClass("error", !valid);
            let parent = element.parent();
            let messageDiv = parent.find('> .buckaroo-validation-message');
            if (!valid) {
                if (messageDiv.length) {
                    messageDiv.text(message);
                    return;
                }
                parent.append(`<div class="buckaroo-validation-message">${message}</div>`);
            }
        }, afterpayDigiTrigger: () => {
            // we check the date as whole
            if ($("#customerbirthdate_d_billing_digi").val()) {
                let dateInvalid = !isValidDate($("#customerbirthdate_d_billing_digi").val() + $("#customerbirthdate_m_billing_digi").val() + $("#customerbirthdate_y_billing_digi").val());
                methodValidator.displayMessage($("#customerbirthdate_d_billing_digi"), buckarooMessages.validation.date, !dateInvalid);

                if (dateInvalid === true) {
                    methodValidator.valid = false;
                } else {
                    let day = $("#customerbirthdate_d_billing_digi").val();
                    let month = $("#customerbirthdate_m_billing_digi").val() - 1; // months are 0-based in JavaScript
                    let year = $("#customerbirthdate_y_billing_digi").val();

                    let inputDate = new Date(year, month, day); // create a date object from input
                    let now = new Date();
                    let eighteenYearsAgo = new Date(now.getFullYear() - 18, now.getMonth(), now.getDate());

                    let ageInvalid = inputDate > eighteenYearsAgo;
                    methodValidator.displayMessage($("#customerbirthdate_y_billing_digi"), buckarooMessages.validation.age, !ageInvalid);

                    if (ageInvalid === true) {
                        methodValidator.valid = false;
                    }
                }
            }

            if ($("#customerbirthdate_d_shipping_digi").val()) {
                let dateInvalidShipping = !isValidDate($("#customerbirthdate_d_shipping_digi").val() + $("#customerbirthdate_m_shipping_digi").val() + $("#customerbirthdate_y_shipping_digi").val());
                methodValidator.displayMessage($("#customerbirthdate_d_shipping_digi"), buckarooMessages.validation.date, !dateInvalidShipping);

                if (dateInvalidShipping === true) {
                    methodValidator.valid = false;
                }
            }
            // we check is the agreement checkbox is checked
            let invalid = !$("#bpe_afterpay_accept_digi").is(':checked');
            methodValidator.displayMessage($("#bpe_afterpay_accept_digi").closest('.row'), buckarooMessages.validation.agreement, !invalid);

            if (invalid) {
                methodValidator.valid = false;
            }
        }, sepaDirectdebitTrigger: () => {
            let invalid = !validateIBAN($("#bpe_sepadirectdebit_iban").val());
            methodValidator.displayMessage($("#bpe_sepadirectdebit_iban"), buckarooMessages.validation.iban, !invalid);
            if (invalid) {
                methodValidator.valid = false;
            }
        }, billinkTrigger: () => {
            if ($("#customerbirthdate_d_billing_billink").val()) {
                let dateInvalid = !isValidDate($("#customerbirthdate_d_billing_billink").val() + $("#customerbirthdate_m_billing_billink").val() + $("#customerbirthdate_y_billing_billink").val());
                methodValidator.displayMessage($("#customerbirthdate_d_billing_billink"), buckarooMessages.validation.date, !dateInvalid);

                if (dateInvalid === true) {
                    methodValidator.valid = false;
                }
            }
        }, payPerEmailTrigger: () => {
            if ($("#customerbirthdate_d_billing_payperemail").val()) {
                let dateInvalid = !isValidDate($("#customerbirthdate_d_billing_payperemail").val() + $("#customerbirthdate_m_billing_payperemail").val() + $("#customerbirthdate_y_billing_payperemail").val());
                methodValidator.displayMessage($("#customerbirthdate_d_billing_payperemail"), buckarooMessages.validation.date, !dateInvalid);

                if (dateInvalid === true) {
                    methodValidator.valid = false;
                }
            }
        }, requiredRadioSelection: (element, errorLabel) => {
            if ($(`.${element}:input[type="radio"]:checked`).length === 0) {
                methodValidator.valid = false;
                methodValidator.displayMessage($(errorLabel), buckarooMessages.validation.bank, false);
            }
        }, requiredDropDownSelection: (element, errorLabel) => {
            if ($(`.${element} option:selected`).length === 0 || $(`.${element} option:selected`).val() === '0') {
                methodValidator.valid = false;
                methodValidator.displayMessage($(errorLabel), buckarooMessages.validation.bank, false);
            }
        }, init: (e) => {
            methodValidator.valid = true;
            $('.buckaroo-validation-message').remove();
            // we validate all at the required fields pertaining to a selected method/form
            methodValidator.requiredAll();
            // we validate based on the selected method
            switch (methodValidator.methodSelector) {
                case 'sepadirectdebit':
                    methodValidator.sepaDirectdebitTrigger();
                    break;
                case 'afterpay&service=digi':
                    methodValidator.afterpayDigiTrigger();
                    break;
                case 'billink':
                    methodValidator.billinkTrigger();
                    break;
                case 'payperemail':
                    methodValidator.payPerEmailTrigger();
                    break;
                case 'paybybank':
                    if ($('.paybybank_radio').length > 0) {
                        methodValidator.requiredRadioSelection('paybybank_issuer', '#booPayByBankErr');
                    } else {
                        methodValidator.requiredDropDownSelection('paybybank_issuer', '#booPayByBankErr');
                    }
                    break;
                case 'creditcard':
                    if ($('.creditcard_radio').length > 0) {
                        methodValidator.requiredRadioSelection('creditcard_banks', '#booCreditCardErr');
                    } else {
                        methodValidator.requiredDropDownSelection('creditcard_banks', '#booCreditCardErr');
                    }
                    break;
                default:
            }
            if (methodValidator.valid) {
                return true;
            } else {
                e.stopPropagation();
                return false;
            }
        }
    }

    function validateIBAN(iban) {
        let newIban = iban.toUpperCase(), modulo = function (divident, divisor) {
            let m = 0;
            for (let i = 0; i < divident.length; ++i) m = (m * 10 + parseInt(divident.charAt(i))) % divisor;
            return m;
        };

        if (newIban.search(/^[A-Z]{2}/gi) < 0) {
            return false;
        }

        newIban = newIban.substring(4) + newIban.substring(0, 4);

        newIban = newIban.replace(/[A-Z]/g, function (match) {
            return match.charCodeAt(0) - 55;
        });

        return parseInt(modulo(newIban, 97), 10) === 1;
    }

    function validateRequired(value) {
        return value.trim().length;
    }

    function isValidDate(date) {
        let valid = true;

        let day = parseInt(date.substring(0, 2), 10);
        let month = parseInt(date.substring(2, 4), 10);
        let year = parseInt(date.substring(4, 8), 10);

        if (isNaN(day) || isNaN(month) || isNaN(year)) valid = false; else if ((month < 1) || (month > 12)) valid = false; else if ((day < 1) || (day > 31)) valid = false; else if ((year < 1850) || (year > 4000)) valid = false; else if (((month == 4) || (month == 6) || (month == 9) || (month == 11)) && (day > 30)) valid = false; else if ((month == 2) && (((year % 400) == 0) || ((year % 4) == 0)) && ((year % 100) != 0) && (day > 29)) valid = false; else if ((month == 2) && ((year % 100) == 0) && (day > 29)) valid = false; else if ((month == 2) && (day > 28)) valid = false;

        return valid;
    }

    class BuckarooCheckout {
        static MOBILE_WIDTH = 768;
        static SHOW_MORE_BANKS = 5;

        listen() {
            this.toggleMethods();
        }

        toggleMethods() {
            this.initMethod();
            $('body').on('click', '.bk-toggle-wrap', (event) => this.handleToggle(event));
            $(window).on('resize', this.showAllIssuers.bind(this));
        }

        handleToggle(event) {
            const toggleWrap = $(event.currentTarget);
            const parentSelector = toggleWrap.closest('.additional-information').find('.bk-method-selector');

            const toggle = toggleWrap.find('.bk-toggle');
            const isDown = toggle.hasClass('bk-toggle-down');
            const textElement = toggleWrap.find('.bk-toggle-text');

            if (isDown) {
                textElement.text(textElement.attr('text-less'));
                parentSelector.children().show();
            } else {
                textElement.text(textElement.attr('text-more'));
                this.hideExcessIssuers(parentSelector);
            }

            toggle.toggleClass('bk-toggle-down bk-toggle-up');
        }

        hideExcessIssuers(selector) {
            const isPayByBank = selector.hasClass('bk-paybybank-selector');
            const selectedIssuer = isPayByBank ? selector.find('input:checked') : null;
            if (isPayByBank) {
                if (selectedIssuer && selectedIssuer.length) {
                    selector.children().not(selectedIssuer.closest('.bk-method-issuer')).hide();
                } else {
                    selector.children(`:nth-child(n+${BuckarooCheckout.SHOW_MORE_BANKS})`).hide();
                }
            }
        }

        initMethod() {
            $('.bk-method-selector').each((_, elem) => {
                const selector = $(elem);
                this.hideExcessIssuers(selector);
            });

            this.showAllIssuers();
        }

        showAllIssuers = () => {
            if ($(window).width() < BuckarooCheckout.MOBILE_WIDTH) {
                $('.bk-toggle-wrap').hide();
                if ($('.bk-toggle-down').length) {
                    $('.bk-toggle-down').addClass('bk-toggle-up').removeClass('bk-toggle-down');
                    $('.bk-method-selector').children().show();
                    $('.bk-toggle-text').text($('.bk-toggle-text').attr('text-less'));
                } else {
                    $('.bk-toggle-wrap').show();
                }
            }
        }
    }
    new BuckarooCheckout().listen();

    class BuckarooPayByBank {
        isMobile = $(window).width() < BuckarooCheckout.MOBILE_WIDTH;

        init() {
            this.showInput();
            this.startListeners();
        }

        startListeners() {
            $(window).on('resize', this.toggleInputToShow.bind(this));
            $('.bk-paybybank-mobile select').on('change', this.syncWithRadioGroup.bind(this));
            $('.bk-paybybank-not-mobile input').on('change', this.syncWithSelect.bind(this));
        }

        toggleInputToShow() {
            let isMobile = $(window).width() < BuckarooCheckout.MOBILE_WIDTH;

            if (this.isMobile !== isMobile) {
                this.isMobile = isMobile;
                this.showInput();
            }
        }

        showInput() {
            $('.bk-paybybank-mobile').toggle(this.isMobile);
            $('.bk-paybybank-not-mobile').toggle(!this.isMobile);
        }

        syncWithRadioGroup() {
            const value = $('.bk-paybybank-mobile select').val();
            if (value === "0") {
                return;
            }
            const radioWithValue = $(`.bk-paybybank-selector input[value="${value}"]`);
            radioWithValue.prop('checked', true);
            this.changeIcon(value);
        }

        syncWithSelect() {
            const value = $('.bk-paybybank-not-mobile input:checked').val();
            $('.bk-paybybank-mobile select').val(value);
            this.changeIcon(value);
        }

        changeIcon(issuer) {
            const img = $(`.bk-paybybank-not-mobile #paybybank_issuer_${issuer}`)
                .closest('.bk-method-issuer')
                .find('img').attr('src');

            $('[data-module-name="PAYBYBANK"]')
                .closest('.payment-option')
                .find('img').attr('src', img)
        }
    }

    new BuckarooPayByBank().init();

    class BuckarooApplePay {
        get isApplePayAvailable() {
            return !!(window.ApplePaySession && ApplePaySession.canMakePayments());
        }

        init() {
            this.togglePayment(this.isApplePayAvailable)
        }

        togglePayment(value = false) {
            $('[data-module-name="applepay"]').closest('.payment-option').toggle(value);
        }
    }

    new BuckarooApplePay().init();
}