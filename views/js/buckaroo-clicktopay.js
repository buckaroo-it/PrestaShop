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

/**
 * Renders the Buckaroo Click to Pay Drop-in UI on the checkout payment step.
 *
 * The SDK authenticates the shopper against Click to Pay in the browser and
 * hands back an identifier plus an encrypted transient token. Those are stored
 * on the payment form and submitted to the `request` front controller, which
 * completes the payment with a server-to-server call.
 */
class BuckarooClickToPay {
    static BUTTON_SELECTOR = '#bk-clicktopay-button';
    static SCREEN_SELECTOR = '#bk-clicktopay-screen';
    static SDK_POLL_INTERVAL = 100;
    static SDK_POLL_TIMEOUT = 10000;

    constructor(config) {
        this.config = config;
        this.initialized = false;
        this.accessTokenCache = null;
    }

    init() {
        if (!this.config || !this.config.merchantIdentifier || !$(BuckarooClickToPay.BUTTON_SELECTOR).length) {
            return;
        }

        $(document).on('change', 'input[name="payment-option"]', () => this.onPaymentOptionChange());
        this.onPaymentOptionChange();
    }

    onPaymentOptionChange() {
        if (!this.isSelected() || this.initialized) {
            return;
        }

        this.initialized = true;
        this.renderDropInUI();
    }

    /**
     * Click to Pay is the selected option when its payment form (the one
     * holding our hidden token fields) is inside the checked option's block.
     */
    isSelected() {
        const $selected = $('input[name="payment-option"]:checked');
        if (!$selected.length) {
            return false;
        }

        return $('#pay-with-' + $selected.attr('id') + '-form').find('#booClickToPayForm').length > 0;
    }

    renderDropInUI() {
        this.clearError();

        this.waitForSdk()
            .then(() => this.fetchAccessToken())
            .then((accessToken) => this.generateCaptureContext(accessToken))
            .then((context) => this.initiateDropInUI(context))
            .catch((error) => {
                // Allow another attempt when the shopper re-selects the method.
                this.initialized = false;
                this.showError();
                console.error('[Buckaroo Click to Pay] Initialization failed:', error);
            });
    }

    /**
     * The Buckaroo SDK is loaded as a remote script, so it may not have been
     * evaluated yet when the shopper reaches the payment step.
     */
    waitForSdk() {
        return new Promise((resolve, reject) => {
            const deadline = Date.now() + BuckarooClickToPay.SDK_POLL_TIMEOUT;
            const poll = () => {
                if (typeof BuckarooSdk !== 'undefined' && BuckarooSdk.ClickToPay) {
                    resolve();
                    return;
                }
                if (Date.now() > deadline) {
                    reject(new Error('Buckaroo SDK was not loaded in time.'));
                    return;
                }
                setTimeout(poll, BuckarooClickToPay.SDK_POLL_INTERVAL);
            };
            poll();
        });
    }

    fetchAccessToken() {
        if (this.accessTokenCache && this.accessTokenCache.expiresAt > Date.now()) {
            return Promise.resolve(this.accessTokenCache.token);
        }

        return Promise.resolve(
            $.ajax({
                url: this.config.tokenUrl,
                method: 'POST',
                dataType: 'json',
                data: { ajax: 1, token: this.config.token },
            })
        ).then((response) => {
            if (!response || !response.access_token) {
                throw new Error((response && response.error) || 'No access token received.');
            }

            const expiresIn = parseInt(response.expires_in, 10) || 0;
            if (expiresIn > 0) {
                this.accessTokenCache = {
                    token: response.access_token,
                    expiresAt: Date.now() + expiresIn * 1000,
                };
            }

            return response.access_token;
        });
    }

    buildCaptureContextOptions() {
        return new BuckarooSdk.ClickToPay.CaptureContextOptions(
            this.config.merchantIdentifier,
            this.config.targetOrigins,
            this.config.country,
            this.config.locale,
            {
                currency: this.config.currency,
                totalAmount: this.config.totalAmount,
            },
            (paymentData) => this.onPaymentAuthenticated(paymentData)
        );
    }

    generateCaptureContext(accessToken) {
        this.captureContextOptions = this.buildCaptureContextOptions();

        const captureContext = new BuckarooSdk.ClickToPay.CaptureContext(
            BuckarooClickToPay.BUTTON_SELECTOR,
            BuckarooClickToPay.SCREEN_SELECTOR,
            this.captureContextOptions
        );

        return Promise.resolve(captureContext.generateCaptureContext(accessToken))
            .then((response) => BuckarooClickToPay.toCamelCaseKeys(response));
    }

    initiateDropInUI(context) {
        if (!context || !context.successful || !context.scriptUrl || !context.jwt) {
            throw new Error((context && context.errorReason) || 'Capture context response was not successful.');
        }

        $(BuckarooClickToPay.BUTTON_SELECTOR).empty();
        $(BuckarooClickToPay.SCREEN_SELECTOR).empty();

        BuckarooSdk.ClickToPay.initiateClickToPayDropInUI(
            context.identifier,
            context.scriptUrl,
            context.jwt,
            BuckarooClickToPay.BUTTON_SELECTOR,
            BuckarooClickToPay.SCREEN_SELECTOR,
            this.captureContextOptions.processPaymentCallback
        );

        this.clearError();
    }

    /**
     * Called by the SDK once the shopper approved the payment. Store the token
     * on the payment form and hand over to PrestaShop's own order confirmation,
     * so its terms-and-conditions validation still applies.
     */
    onPaymentAuthenticated(paymentData) {
        $('#bk_clicktopay_identifier').val((paymentData && paymentData.identifier) || '');
        $('#bk_clicktopay_transient_token').val((paymentData && paymentData.transientToken) || '');

        const $confirmButton = $('#payment-confirmation button').first();

        if ($confirmButton.length && !$confirmButton.is(':disabled')) {
            $confirmButton.trigger('click');
        } else {
            this.showError(this.config.messages.acceptTerms);
        }

        return Promise.resolve();
    }

    showError(message) {
        $('#bk-clicktopay-error').text(message || this.config.messages.initError).show();
    }

    clearError() {
        $('#bk-clicktopay-error').text('').hide();
    }

    /**
     * The capture context API answers in PascalCase (Successful, ScriptUrl,
     * Jwt, ...) while the SDK reads camelCase. Lower-casing the first letter is
     * a no-op for responses that are already camelCase.
     */
    static toCamelCaseKeys(response) {
        if (!response || typeof response !== 'object') {
            return response;
        }

        return Object.keys(response).reduce((accumulator, key) => {
            accumulator[key.charAt(0).toLowerCase() + key.slice(1)] = response[key];
            return accumulator;
        }, {});
    }
}

$(document).ready(function () {
    if (typeof buckarooClickToPayConfig === 'undefined' || !buckarooClickToPayConfig) {
        return;
    }

    new BuckarooClickToPay(buckarooClickToPayConfig).init();
});
