/*
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * It is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * @author Buckaroo.nl <plugins@buckaroo.nl>
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

/*
 * Apple Pay express button (product, cart and checkout page).
 *
 * The button is shown once Apple's Apple Pay JS SDK is available (Safari:
 * native sheet; other browsers: QR code for an iPhone). The sheet opens with a provisional total from the
 * page; Apple Pay then reports the shopper's address, and the shop calculates
 * the express cart, carriers and total for it. After the shopper authorized
 * the payment, the token is posted to the Buckaroo request controller, which
 * creates the order and the payment like any other Buckaroo payment.
 *
 * Plain ES5 on purpose: the script also loads in browsers without Apple Pay
 * and must never break the page there.
 */
(function () {
    'use strict';

    var config = window.buckarooApplePayExpress;
    var supportCheck = null;

    if (!config || typeof window.Promise === 'undefined') {
        return;
    }

    function sdkAvailable() {
        return typeof window.BuckarooSdk !== 'undefined'
            && window.BuckarooSdk.ApplePay
            && typeof window.BuckarooSdk.ApplePay.ApplePayPayment === 'function'
            && typeof window.jQuery !== 'undefined'
            && window.isSecureContext !== false;
    }

    function hasApplePaySession() {
        return 'ApplePaySession' in window && typeof window.ApplePaySession !== 'undefined';
    }

    /*
     * Apple's Apple Pay JS SDK provides ApplePaySession in every browser: Safari
     * opens the native sheet, other browsers (Chrome, Edge, Firefox, also on
     * Windows and Android) show a QR code to complete the payment on an iPhone.
     * So the button is shown whenever that SDK is available on a secure page,
     * not only when the device itself has a card in Wallet.
     */
    function isApplePaySupported() {
        if (supportCheck === null) {
            supportCheck = new Promise(function (resolve) {
                var applePay;
                if (!sdkAvailable()) {
                    resolve(false);
                    return;
                }
                applePay = window.BuckarooSdk.ApplePay;
                try {
                    if (typeof applePay.loadApplePaySdk === 'function') {
                        Promise.resolve(applePay.loadApplePaySdk())
                            .then(function () { resolve(hasApplePaySession()); })
                            .catch(function () { resolve(hasApplePaySession()); });
                        return;
                    }
                    // Older Buckaroo SDK without the Apple Pay JS SDK loader.
                    Promise.resolve(applePay.checkApplePaySupport(config.merchantGuid))
                        .then(function (supported) { resolve(supported === true || hasApplePaySession()); })
                        .catch(function () { resolve(hasApplePaySession()); });
                } catch (e) {
                    resolve(hasApplePaySession());
                }
            });
        }

        return supportCheck;
    }

    /* Apple Pay objects expose their data through getters; copy them into plain JSON. */
    function toPlainObject(value, depth) {
        var plain;
        var key;
        depth = depth || 0;
        if (value === null || typeof value !== 'object' || depth > 8) {
            return value;
        }
        if (Object.prototype.toString.call(value) === '[object Array]') {
            plain = [];
            for (key = 0; key < value.length; key++) {
                plain.push(toPlainObject(value[key], depth + 1));
            }
            return plain;
        }
        plain = {};
        for (key in value) { // eslint-disable-line guard-for-in
            try {
                if (typeof value[key] !== 'function') {
                    plain[key] = toPlainObject(value[key], depth + 1);
                }
            } catch (e) {
                // ignore unreadable properties
            }
        }
        return plain;
    }

    function post(params) {
        var body = new URLSearchParams();
        var key;
        var i;
        body.append('token', config.token);
        for (key in params) {
            if (Object.prototype.hasOwnProperty.call(params, key)) {
                if (Object.prototype.toString.call(params[key]) === '[object Array]') {
                    for (i = 0; i < params[key].length; i++) {
                        body.append(params[key][i][0], params[key][i][1]);
                    }
                } else {
                    body.append(key, params[key]);
                }
            }
        }

        return fetch(config.url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        }).then(function (response) {
            return response.json();
        }).catch(function () {
            return { status: 'error', message: config.messages.error, field: '' };
        });
    }

    function parseJson(text) {
        try {
            return JSON.parse(text);
        } catch (e) {
            return null;
        }
    }

    function formatAmount(amount) {
        var value = parseFloat(amount);
        return isNaN(value) || value < 0 ? '0.00' : value.toFixed(2);
    }

    function ExpressButton(container) {
        this.container = container;
        this.host = container.querySelector('.buckaroo-applepay-express__button');
        this.message = container.querySelector('.buckaroo-applepay-express__message');
        this.placement = container.getAttribute('data-placement');
        this.payment = null;
        this.reset();
    }

    ExpressButton.prototype.reset = function () {
        this.started = false;
        this.summary = null;
    };

    ExpressButton.prototype.mount = function () {
        var self = this;
        var options = new window.BuckarooSdk.ApplePay.ApplePayOptions(
            config.storeName,
            config.countryCode,
            config.currencyCode,
            config.cultureCode,
            config.merchantGuid,
            [],
            this.provisionalTotal(),
            'shipping',
            [],
            function (payment) { return self.onAuthorized(payment); },
            function (method) { return self.onShippingMethod(method); },
            function (contact) { return self.onShippingContact(contact); },
            ['name', 'postalAddress'],
            ['name', 'email', 'phone', 'postalAddress'],
            function () { self.reset(); }
        );

        this.payment = new window.BuckarooSdk.ApplePay.ApplePayPayment(this.host, options);
        this.payment.showPayButton(config.buttonStyle, config.buttonType);

        // Runs before the SDK's click handler opens the sheet.
        this.host.addEventListener('click', function () {
            self.reset();
            self.hideMessage();
            self.payment.options.lineItems = [];
            self.payment.options.shippingMethods = [];
            self.payment.options.totalLineItem = self.provisionalTotal();
        }, true);

        this.container.hidden = false;
    };

    /* Amount shown while the sheet waits for the shop's calculation. */
    ExpressButton.prototype.provisionalTotal = function () {
        var amount = 0;
        var details;
        var quantity;

        if (this.placement === 'product') {
            details = document.getElementById('product-details');
            details = details ? parseJson(details.getAttribute('data-product')) : null;
            quantity = document.getElementById('quantity_wanted');
            quantity = quantity ? parseInt(quantity.value, 10) : 1;
            if (details && details.price_amount) {
                amount = parseFloat(details.price_amount) * (isNaN(quantity) || quantity < 1 ? 1 : quantity);
            }
        } else if (window.prestashop && prestashop.cart && prestashop.cart.totals && prestashop.cart.totals.total) {
            amount = prestashop.cart.totals.total.amount;
        }

        return { label: config.storeName, amount: formatAmount(amount), type: 'pending' };
    };

    /* The product, combination and quantity selected in the product form. */
    ExpressButton.prototype.productParams = function () {
        var form = document.getElementById('add-to-cart-or-refresh');
        var details = document.getElementById('product-details');
        var entries = [];
        var params = {
            id_product: this.container.getAttribute('data-id-product'),
            qty: 1
        };
        var elements;
        var i;

        details = details ? parseJson(details.getAttribute('data-product')) : null;
        if (details && details.id_product_attribute) {
            params.id_product_attribute = details.id_product_attribute;
        }

        if (form) {
            elements = form.querySelectorAll('[name]');
            for (i = 0; i < elements.length; i++) {
                var element = elements[i];
                if ((element.type === 'radio' || element.type === 'checkbox') && !element.checked) {
                    continue;
                }
                if (element.name === 'qty') {
                    params.qty = element.value;
                } else if (element.name === 'id_product') {
                    params.id_product = element.value;
                } else if (element.name.indexOf('group[') === 0) {
                    entries.push([element.name, element.value]);
                }
            }
        }
        params.groups = entries;

        return params;
    };

    ExpressButton.prototype.update = function (response) {
        this.summary = response;

        return {
            newTotal: response.total,
            newLineItems: response.lineItems,
            newShippingMethods: response.shippingMethods
        };
    };

    ExpressButton.prototype.onShippingContact = function (contact) {
        var self = this;
        var params = { action: 'contact', contact: JSON.stringify(toPlainObject(contact)) };
        var product;

        if (!this.started) {
            params.action = 'start';
            params.source = this.placement;
            if (this.placement === 'product') {
                product = this.productParams();
                params.id_product = product.id_product;
                params.qty = product.qty;
                if (product.id_product_attribute) {
                    params.id_product_attribute = product.id_product_attribute;
                }
                params.groups = product.groups;
            }
        }

        return post(params).then(function (response) {
            if (response.status === 'success') {
                self.started = true;
                return self.update(response);
            }

            if (!self.started && !response.started) {
                // The express cart could not be prepared (stock, availability): close the sheet.
                self.payment.abortSession();
                self.reset();
                self.showMessage(response.message);
                return null;
            }

            self.started = true;

            return {
                newTotal: self.summary ? self.summary.total : self.provisionalTotal(),
                newLineItems: self.summary ? self.summary.lineItems : [],
                newShippingMethods: [],
                errors: [new window.ApplePayError('shippingContactInvalid', response.field || 'postalAddress', response.message)]
            };
        });
    };

    ExpressButton.prototype.onShippingMethod = function (method) {
        var self = this;

        return post({ action: 'shipping', identifier: method.identifier }).then(function (response) {
            if (response.status === 'success') {
                self.summary = response;
            }

            return { newTotal: self.summary.total, newLineItems: self.summary.lineItems };
        });
    };

    ExpressButton.prototype.onAuthorized = function (payment) {
        var self = this;
        var plain = toPlainObject(payment) || {};
        var token = plain.token || toPlainObject(payment.token);

        if (!this.summary || !token || !token.paymentData) {
            return Promise.resolve({ status: window.ApplePaySession.STATUS_FAILURE, errors: [] });
        }

        return post({
            action: 'authorize',
            payment: JSON.stringify({ billingContact: plain.billingContact, shippingContact: plain.shippingContact }),
            identifier: this.summary.selectedShipping || '',
            amount: this.summary.total.amount
        }).then(function (response) {
            if (response.status !== 'success') {
                return {
                    status: window.ApplePaySession.STATUS_FAILURE,
                    errors: [response.field
                        ? new window.ApplePayError('shippingContactInvalid', response.field, response.message)
                        : new window.ApplePayError('unknown', undefined, response.message)]
                };
            }

            // Let the sheet close first, then hand over to the request controller.
            window.setTimeout(function () { self.submit(response, token); }, 0);

            return { status: window.ApplePaySession.STATUS_SUCCESS, errors: [] };
        });
    };

    ExpressButton.prototype.submit = function (response, token) {
        var form = document.createElement('form');
        var fields = response.fields || {};
        var key;

        fields.applepay_token = JSON.stringify(token);
        form.method = 'post';
        form.action = response.action;
        form.style.display = 'none';

        for (key in fields) {
            if (Object.prototype.hasOwnProperty.call(fields, key)) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = fields[key];
                form.appendChild(input);
            }
        }

        document.body.appendChild(form);
        form.submit();
    };

    ExpressButton.prototype.showMessage = function (text) {
        if (this.message) {
            this.message.textContent = text || config.messages.error;
            this.message.hidden = false;
        }
    };

    ExpressButton.prototype.hideMessage = function () {
        if (this.message) {
            this.message.hidden = true;
        }
    };

    function initButtons() {
        var containers = document.querySelectorAll('.buckaroo-applepay-express:not([data-buckaroo-ready])');
        var i;

        if (!containers.length) {
            return;
        }

        for (i = 0; i < containers.length; i++) {
            containers[i].setAttribute('data-buckaroo-ready', '1');
        }

        isApplePaySupported().then(function (supported) {
            var j;
            for (j = 0; j < containers.length; j++) {
                if (!supported) {
                    containers[j].parentNode && containers[j].parentNode.removeChild(containers[j]);
                    continue;
                }
                try {
                    new ExpressButton(containers[j]).mount();
                } catch (e) {
                    containers[j].parentNode && containers[j].parentNode.removeChild(containers[j]);
                }
            }
        });
    }

    function onReady() {
        initButtons();

        // The theme re-renders the product details and cart blocks after a change.
        if (window.prestashop && typeof prestashop.on === 'function') {
            prestashop.on('updatedProduct', initButtons);
            prestashop.on('updatedCart', initButtons);
            prestashop.on('updateCart', function () { window.setTimeout(initButtons, 500); });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', onReady);
    } else {
        onReady();
    }
}());
