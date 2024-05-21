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

buckaroo()

function buckaroo() {

    $(document).on('click', 'input[name="payment-option"]', function() {
        methodValidator.setMethod($(this).attr('id'));
    });

    $('#payment-confirmation button').on('click', (e) => {
        methodValidator.init(e);
    });

    $('input[name="payment-option"]').on('change', function() {
        let $nextDiv = $(this).closest('.payment-option').parent().next();
        let paymentFee;
        let buckarooKey = $nextDiv.find('input[name="buckarooKey"]').val();

        if ($nextDiv.hasClass('js-payment-option-form') && buckarooKey && buckarooFees[buckarooKey] !== undefined) {
            paymentFee = buckarooFees[buckarooKey]['buckarooFee'];
        } else {
            paymentFee = $nextDiv.next().find('input[name="payment-fee-price"]').val();
        }

        if (!paymentFee) {
            $('#cart-subtotal-buckarooFee').remove();
        } else {
            updateCartSummary(paymentFee);
        }
    });

    const updateCartSummary = (paymentFee) => {
        $.ajax({
            url: buckarooAjaxUrl,
            method: 'GET',
            data: {'paymentFee': paymentFee, ajax: 1, action: 'getTotalCartPrice'},
            success: handleCartUpdate,
            error: function(err) {
                console.error('Error updating cart summary:', err);
            }
        })
    };

    function handleCartUpdate(response) {
        const { cart_summary_totals, paymentFee } = $.parseJSON(response);
        const $cartSummaryTotals = $('.card-block.cart-summary-totals');

        const $newCartSummaryTotals = $(cart_summary_totals);
        $cartSummaryTotals.replaceWith($newCartSummaryTotals);

        updatePaymentFeeDisplay(paymentFee);
    }

    const $cartSubtotalBuckarooFee = $('#cart-subtotal-buckarooFee');
    const $cartSummarySubtotalsContainer = $('.cart-summary-subtotals-container');

    function updatePaymentFeeDisplay(paymentFee) {
        const paymentFeeHtml = `<div class="cart-summary-line cart-summary-subtotals" id="cart-subtotal-shipping">
                                <span class="label">${paymentFeeLabel}</span>
                                <span class="value">${paymentFee}</span>
                            </div>`;

        if ($cartSubtotalBuckarooFee.length === 0) {
            $cartSummarySubtotalsContainer.append(`<div id="cart-subtotal-buckarooFee">${paymentFeeHtml}</div>`);
        } else {
            $cartSubtotalBuckarooFee.html(paymentFeeHtml);
        }
    }


    const methodValidator = {
        formPointer: null, // selected method notation from 'action' attribute
        methodSelector: null,// JS form object pointer
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
                case 'ideal':
                    if ($('.noIdealIssuers').length === 0) {
                        if ($('.ideal_radio').length > 0) {
                            methodValidator.requiredRadioSelection('ideal_issuer', '#booIdealErr');
                        } else {
                            methodValidator.requiredDropDownSelection('ideal_issuer', '#booIdealErr');
                        }
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