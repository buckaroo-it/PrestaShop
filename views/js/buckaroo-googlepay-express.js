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
 * Google Pay express button (product, cart and checkout page).
 *
 * Google's Google Pay API (pay.js) is loaded here and the button is only shown
 * when Google Pay confirms it can be used in this browser. The sheet opens with
 * a provisional total from the page; when the shopper picks an address, the
 * shop calculates the express cart, carriers and total for it. After the
 * shopper authorized the payment, the shop checks the cart and the token is
 * posted to the Buckaroo request controller, which creates the order and the
 * payment like any other Buckaroo payment. The card is tokenized for Buckaroo
 * (gateway "buckaroo" with the Buckaroo gateway merchant ID).
 *
 * Plain ES5 on purpose: the script also loads in browsers without Google Pay
 * and must never break the page there.
 */
(function () {
    'use strict';

    var PAY_JS_URL = 'https://pay.google.com/gp/p/js/pay.js';
    var PENDING_SHIPPING_ID = 'buckaroo-pending';
    var CARD_NETWORKS = ['MASTERCARD', 'VISA'];
    var AUTH_METHODS = ['PAN_ONLY', 'CRYPTOGRAM_3DS'];

    var config = window.buckarooGooglePayExpress;
    var payJsLoading = null;
    var readyCheck = null;
    var client = null;
    var activeButton = null;

    if (!config || typeof window.Promise === 'undefined' || typeof window.fetch === 'undefined') {
        return;
    }

    function loadPayJs() {
        if (window.google && window.google.payments && window.google.payments.api) {
            return Promise.resolve(true);
        }
        if (payJsLoading === null) {
            payJsLoading = new Promise(function (resolve) {
                var script = document.createElement('script');
                script.src = PAY_JS_URL;
                script.async = true;
                script.onload = function () {
                    resolve(!!(window.google && window.google.payments && window.google.payments.api));
                };
                script.onerror = function () {
                    payJsLoading = null;
                    resolve(false);
                };
                document.head.appendChild(script);
            });
        }

        return payJsLoading;
    }

    /* The sheet callbacks belong to the client; they are passed on to the button that opened it. */
    function getClient() {
        if (client === null) {
            client = new window.google.payments.api.PaymentsClient({
                environment: config.environment === 'PRODUCTION' ? 'PRODUCTION' : 'TEST',
                paymentDataCallbacks: {
                    onPaymentDataChanged: function (data) {
                        return activeButton ? activeButton.onPaymentDataChanged(data) : Promise.resolve({});
                    },
                    onPaymentAuthorized: function (data) {
                        return activeButton
                            ? activeButton.onPaymentAuthorized(data)
                            : Promise.resolve(authorizationError(config.messages.error, ''));
                    }
                }
            });
        }

        return client;
    }

    function baseCardMethod() {
        return {
            type: 'CARD',
            parameters: {
                allowedAuthMethods: AUTH_METHODS,
                allowedCardNetworks: CARD_NETWORKS
            }
        };
    }

    function isGooglePayReady() {
        if (readyCheck === null) {
            readyCheck = loadPayJs().then(function (loaded) {
                if (!loaded || window.isSecureContext === false) {
                    return false;
                }

                return getClient().isReadyToPay({
                    apiVersion: 2,
                    apiVersionMinor: 0,
                    allowedPaymentMethods: [baseCardMethod()]
                }).then(function (response) {
                    return !!(response && response.result);
                });
            }).catch(function () {
                return false;
            });
        }

        return readyCheck;
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

    /* Product data of the product page: #product-details (Classic) or .js-product-details (Hummingbird). */
    function productDetails() {
        var element = document.getElementById('product-details') || document.querySelector('.js-product-details[data-product]');

        return element ? parseJson(element.getAttribute('data-product')) : null;
    }

    function formatAmount(amount) {
        var value = parseFloat(amount);
        return isNaN(value) || value < 0 ? '0.00' : value.toFixed(2);
    }

    function formatPrice(amount) {
        var value = parseFloat(amount);
        if (isNaN(value)) {
            return '';
        }
        try {
            return new Intl.NumberFormat(config.buttonLocale || undefined, {
                style: 'currency',
                currency: config.currencyCode
            }).format(value);
        } catch (e) {
            return value.toFixed(2) + ' ' + config.currencyCode;
        }
    }

    /* Google Pay error reason for the contact field the shop rejected. */
    function addressReason(field) {
        if (field === 'postalCode' || field === 'locality' || field === 'addressLines' || field === 'name') {
            return 'SHIPPING_ADDRESS_INVALID';
        }

        return 'SHIPPING_ADDRESS_UNSERVICEABLE';
    }

    function authorizationError(message, field) {
        return {
            transactionState: 'ERROR',
            error: {
                intent: 'PAYMENT_AUTHORIZATION',
                reason: field && field !== 'emailAddress' ? addressReason(field) : 'OTHER_ERROR',
                message: message || config.messages.error
            }
        };
    }

    /*
     * A Google Pay address in the contact format the shop also receives from
     * Apple Pay. Google sends the full name in one field.
     */
    function toContact(address, email) {
        var name = String(address.name || '').replace(/\s+/g, ' ').replace(/^\s+|\s+$/g, '');
        var space = name.indexOf(' ');
        var lines = [address.address1, address.address2, address.address3].filter(function (line) {
            return !!line;
        });

        return {
            givenName: space > 0 ? name.substring(0, space) : name,
            familyName: space > 0 ? name.substring(space + 1) : name,
            emailAddress: email || '',
            phoneNumber: address.phoneNumber || '',
            addressLines: lines,
            locality: address.locality || '',
            administrativeArea: address.administrativeArea || '',
            postalCode: address.postalCode || '',
            countryCode: address.countryCode || ''
        };
    }

    function ExpressButton(container) {
        this.container = container;
        this.host = container.querySelector('.buckaroo-googlepay-express__button');
        this.message = container.querySelector('.buckaroo-googlepay-express__message');
        this.placement = container.getAttribute('data-placement');
        this.virtual = container.getAttribute('data-virtual') === '1';
        this.reset();
    }

    ExpressButton.prototype.reset = function () {
        this.started = false;
        this.summary = null;
        this.authorized = null;
        this.failure = '';
    };

    ExpressButton.prototype.mount = function () {
        var self = this;
        var button = getClient().createButton({
            onClick: function () { self.open(); },
            buttonColor: config.buttonColor === 'white' ? 'white' : 'black',
            buttonType: config.buttonType || 'pay',
            buttonLocale: config.buttonLocale || 'en',
            buttonSizeMode: 'fill',
            allowedPaymentMethods: [baseCardMethod()]
        });

        this.host.appendChild(button);
        this.container.hidden = false;
    };

    /* Must run inside the click: Google Pay only opens the sheet for a user gesture. */
    ExpressButton.prototype.open = function () {
        var self = this;

        activeButton = this;
        this.reset();
        this.hideMessage();

        getClient().loadPaymentData(this.paymentDataRequest()).then(function () {
            if (self.authorized) {
                self.submit(self.authorized.response, self.authorized.token);
            }
        }).catch(function (error) {
            if (error && error.statusCode === 'CANCELED') {
                if (self.failure) {
                    self.showMessage(self.failure);
                }
                return;
            }
            self.showMessage(self.failure || config.messages.error);
        });
    };

    ExpressButton.prototype.paymentDataRequest = function () {
        var merchantInfo = { merchantName: config.merchantName };
        var request;

        if (config.googleMerchantId) {
            merchantInfo.merchantId = config.googleMerchantId;
        }

        request = {
            apiVersion: 2,
            apiVersionMinor: 0,
            allowedPaymentMethods: [{
                type: 'CARD',
                parameters: {
                    allowedAuthMethods: AUTH_METHODS,
                    allowedCardNetworks: CARD_NETWORKS,
                    billingAddressRequired: true,
                    billingAddressParameters: { format: 'FULL', phoneNumberRequired: true }
                },
                tokenizationSpecification: {
                    type: 'PAYMENT_GATEWAY',
                    parameters: { gateway: 'buckaroo', gatewayMerchantId: config.gatewayMerchantId }
                }
            }],
            merchantInfo: merchantInfo,
            transactionInfo: {
                totalPriceStatus: 'ESTIMATED',
                totalPrice: this.provisionalTotal(),
                currencyCode: config.currencyCode,
                countryCode: config.countryCode
            },
            emailRequired: true,
            shippingAddressRequired: true,
            shippingAddressParameters: { phoneNumberRequired: true },
            callbackIntents: ['SHIPPING_ADDRESS', 'PAYMENT_AUTHORIZATION']
        };

        if (!this.virtual) {
            // Replaced by the shop's carriers as soon as the shopper's address is known.
            request.shippingOptionRequired = true;
            request.shippingOptionParameters = {
                defaultSelectedOptionId: PENDING_SHIPPING_ID,
                shippingOptions: [{ id: PENDING_SHIPPING_ID, label: config.messages.shipping, description: '' }]
            };
            request.callbackIntents.push('SHIPPING_OPTION');
        }

        return request;
    };

    /* Amount shown until the shop calculated the express cart for the address. */
    ExpressButton.prototype.provisionalTotal = function () {
        var amount = 0;
        var details;
        var quantity;

        if (this.placement === 'product') {
            details = productDetails();
            quantity = document.getElementById('quantity_wanted');
            quantity = quantity ? parseInt(quantity.value, 10) : 1;
            if (details && details.price_amount) {
                amount = parseFloat(details.price_amount) * (isNaN(quantity) || quantity < 1 ? 1 : quantity);
            }
        } else if (window.prestashop && prestashop.cart && prestashop.cart.totals && prestashop.cart.totals.total) {
            amount = prestashop.cart.totals.total.amount;
        }

        return formatAmount(amount);
    };

    /* The product, combination and quantity selected in the product form. */
    ExpressButton.prototype.productParams = function () {
        var form = document.getElementById('add-to-cart-or-refresh');
        var details = productDetails();
        var entries = [];
        var params = {
            id_product: this.container.getAttribute('data-id-product'),
            qty: 1
        };
        var elements;
        var i;

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

    ExpressButton.prototype.transactionInfo = function () {
        var info = {
            totalPriceStatus: this.summary ? 'FINAL' : 'ESTIMATED',
            totalPrice: this.summary ? this.summary.total.amount : this.provisionalTotal(),
            currencyCode: config.currencyCode,
            countryCode: config.countryCode
        };

        if (this.summary && this.summary.displayItems) {
            info.displayItems = this.summary.displayItems.map(function (item) {
                return { label: item.label, type: item.type, price: item.price, status: 'FINAL' };
            });
        }

        return info;
    };

    ExpressButton.prototype.update = function (response) {
        var result;

        this.summary = response;
        result = { newTransactionInfo: this.transactionInfo() };

        if (!this.virtual && response.shippingMethods && response.shippingMethods.length) {
            result.newShippingOptionParameters = {
                defaultSelectedOptionId: response.selectedShipping,
                shippingOptions: response.shippingMethods.map(function (method) {
                    var price = formatPrice(method.amount);
                    return {
                        id: method.identifier,
                        label: price ? method.label + ': ' + price : method.label,
                        description: method.detail || ''
                    };
                })
            };
        }

        return result;
    };

    ExpressButton.prototype.onPaymentDataChanged = function (data) {
        var self = this;
        var trigger = data.callbackTrigger;
        var address = data.shippingAddress;
        var params;
        var product;

        if (trigger === 'SHIPPING_OPTION' && this.started) {
            if (!data.shippingOptionData || data.shippingOptionData.id === PENDING_SHIPPING_ID) {
                return Promise.resolve(this.summary ? this.update(this.summary) : {});
            }

            return post({ action: 'shipping', identifier: data.shippingOptionData.id }).then(function (response) {
                if (response.status === 'success') {
                    return self.update(response);
                }

                return {
                    newTransactionInfo: self.transactionInfo(),
                    error: {
                        reason: 'SHIPPING_OPTION_INVALID',
                        message: response.message || config.messages.error,
                        intent: 'SHIPPING_OPTION'
                    }
                };
            });
        }

        if (!address) {
            return Promise.resolve({});
        }

        params = {
            action: this.started ? 'contact' : 'start',
            contact: JSON.stringify({
                countryCode: address.countryCode || '',
                administrativeArea: address.administrativeArea || '',
                postalCode: address.postalCode || '',
                locality: address.locality || ''
            })
        };

        if (!this.started) {
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
                // The express cart could not be prepared (stock, availability):
                // shown in the sheet, and on the page once the sheet is closed.
                self.failure = response.message || config.messages.error;

                return {
                    newTransactionInfo: self.transactionInfo(),
                    error: { reason: 'OTHER_ERROR', message: self.failure, intent: 'SHIPPING_ADDRESS' }
                };
            }

            self.started = true;

            return {
                newTransactionInfo: self.transactionInfo(),
                error: {
                    reason: addressReason(response.field),
                    message: response.message || config.messages.error,
                    intent: 'SHIPPING_ADDRESS'
                }
            };
        });
    };

    ExpressButton.prototype.onPaymentAuthorized = function (paymentData) {
        var self = this;
        var method = paymentData.paymentMethodData || {};
        var info = method.info || {};
        var token = method.tokenizationData ? method.tokenizationData.token : '';
        var billing = info.billingAddress || paymentData.shippingAddress || {};
        var shipping = paymentData.shippingAddress || billing;
        var identifier = '';

        if (!this.summary || !token) {
            return Promise.resolve(authorizationError(this.failure || config.messages.error, ''));
        }

        if (paymentData.shippingOptionData && paymentData.shippingOptionData.id !== PENDING_SHIPPING_ID) {
            identifier = paymentData.shippingOptionData.id;
        } else if (this.summary.selectedShipping) {
            identifier = this.summary.selectedShipping;
        }

        return post({
            action: 'authorize',
            payment: JSON.stringify({
                billingContact: toContact(billing, paymentData.email),
                shippingContact: toContact(shipping, paymentData.email)
            }),
            identifier: identifier,
            amount: this.summary.total.amount
        }).then(function (response) {
            if (response.status !== 'success') {
                return authorizationError(response.message, response.field);
            }

            // Handed over to the request controller once the sheet has closed.
            self.authorized = { response: response, token: token };

            return { transactionState: 'SUCCESS' };
        });
    };

    ExpressButton.prototype.submit = function (response, token) {
        var form = document.createElement('form');
        var fields = response.fields || {};
        var key;

        fields.googlepay_token = token;
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
        var containers = document.querySelectorAll('.buckaroo-googlepay-express:not([data-buckaroo-ready])');
        var i;

        if (!containers.length) {
            return;
        }

        for (i = 0; i < containers.length; i++) {
            containers[i].setAttribute('data-buckaroo-ready', '1');
        }

        isGooglePayReady().then(function (ready) {
            var j;
            for (j = 0; j < containers.length; j++) {
                if (!ready) {
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
