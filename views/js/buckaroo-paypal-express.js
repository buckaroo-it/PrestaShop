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
 * PayPal express button (product, cart and checkout page).
 *
 * PayPal's JS SDK is loaded for Buckaroo's PayPal client and the merchant ID,
 * as the Buckaroo Client SDK (BuckarooSdk.PayPal) does. When the shopper
 * clicks the button, the shop prepares the express cart and Buckaroo creates
 * the PayPal order for its total. Each time the shopper picks an address or
 * a carrier in PayPal, the shop calculates the carriers and the total for it
 * and the PayPal order is updated. Once the shopper approved the order, the
 * payer and address PayPal shares are checked by the shop, which creates the
 * customer and addresses, and the PayPal order ID is posted to the Buckaroo
 * request controller, which creates the order and pays the PayPal order.
 *
 * Plain ES5 on purpose: the script must never break the page.
 */
(function () {
    'use strict';

    var SDK_URL = 'https://www.paypal.com/sdk/js';
    var DISABLED_FUNDING = 'credit,card,bancontact,blik,eps,giropay,ideal,mercadopago,mybank,p24,sepa,sofort,venmo';
    var PURCHASE_UNIT = "/purchase_units/@reference_id=='default'";

    var config = window.buckarooPayPalExpress;
    var sdkLoading = null;

    if (!config || typeof window.Promise === 'undefined' || typeof window.fetch === 'undefined') {
        return;
    }

    function loadSdk() {
        if (window.paypal && typeof window.paypal.Buttons === 'function') {
            return Promise.resolve(true);
        }
        if (sdkLoading === null) {
            sdkLoading = new Promise(function (resolve) {
                var params = [
                    'client-id=' + encodeURIComponent(config.clientId),
                    'currency=' + encodeURIComponent(config.currencyCode),
                    'components=buttons',
                    'disable-funding=' + DISABLED_FUNDING,
                    'enable-funding=paylater'
                ];
                var script = document.createElement('script');

                if (config.merchantId) {
                    params.push('merchant-id=' + encodeURIComponent(config.merchantId));
                }
                if (config.locale) {
                    params.push('locale=' + encodeURIComponent(config.locale));
                }

                script.src = SDK_URL + '?' + params.join('&');
                script.async = true;
                script.setAttribute('data-partner-attribution-id', config.partnerAttributionId);
                script.onload = function () {
                    resolve(!!(window.paypal && typeof window.paypal.Buttons === 'function'));
                };
                script.onerror = function () {
                    sdkLoading = null;
                    resolve(false);
                };
                document.head.appendChild(script);
            });
        }

        return sdkLoading;
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

    function clean(value) {
        return String(value || '').replace(/\s+/g, ' ').replace(/^\s+|\s+$/g, '');
    }

    /* "Jane van Dijk" -> given name "Jane", family name "van Dijk". */
    function splitName(fullName) {
        var name = clean(fullName);
        var space = name.indexOf(' ');

        return {
            givenName: space > 0 ? name.substring(0, space) : name,
            familyName: space > 0 ? name.substring(space + 1) : name
        };
    }

    /*
     * A PayPal address in the contact format the shop also receives from
     * Apple Pay and Google Pay.
     */
    function toContact(address, name, email, phone) {
        address = address || {};

        return {
            givenName: name.givenName,
            familyName: name.familyName,
            emailAddress: email || '',
            phoneNumber: phone || '',
            addressLines: [address.address_line_1, address.address_line_2].filter(function (line) {
                return !!line;
            }),
            locality: address.admin_area_2 || '',
            administrativeArea: address.admin_area_1 || '',
            postalCode: address.postal_code || '',
            countryCode: address.country_code || ''
        };
    }

    function ExpressButton(container) {
        this.container = container;
        this.host = container.querySelector('.buckaroo-paypal-express__button');
        this.message = container.querySelector('.buckaroo-paypal-express__message');
        this.placement = container.getAttribute('data-placement');
        this.virtual = container.getAttribute('data-virtual') === '1';
        this.reset();
    }

    ExpressButton.prototype.reset = function () {
        this.summary = null;
        this.optionsPatched = false;
        this.failure = '';
    };

    ExpressButton.prototype.mount = function () {
        var self = this;
        var buttons = window.paypal.Buttons({
            style: {
                layout: 'vertical',
                color: config.buttonColor || 'gold',
                shape: config.buttonShape === 'pill' ? 'pill' : 'rect',
                label: config.buttonLabel || 'paypal',
                height: 45
            },
            onClick: function () {
                self.reset();
                self.hideMessage();
            },
            createOrder: function () {
                return self.createOrder();
            },
            onShippingChange: function (data, actions) {
                return self.onShippingChange(data, actions);
            },
            onApprove: function (data, actions) {
                return self.onApprove(data, actions);
            },
            onCancel: function () {
                if (self.failure) {
                    self.showMessage(self.failure);
                }
            },
            onError: function () {
                self.showMessage(self.failure || config.messages.error);
            }
        });

        if (typeof buttons.isEligible === 'function' && !buttons.isEligible()) {
            throw new Error('PayPal is not eligible');
        }

        return buttons.render(this.host).then(function () {
            self.container.hidden = false;
        });
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

    ExpressButton.prototype.fail = function (message) {
        this.failure = message || config.messages.error;

        return new Error(this.failure);
    };

    /*
     * Prepare the express cart (for the visitor's country until PayPal shares
     * the shopper's address) and let Buckaroo create the PayPal order.
     */
    ExpressButton.prototype.createOrder = function () {
        var self = this;
        var params = { action: 'start', source: this.placement, contact: '{}' };
        var product;

        if (this.placement === 'product') {
            product = this.productParams();
            params.id_product = product.id_product;
            params.qty = product.qty;
            if (product.id_product_attribute) {
                params.id_product_attribute = product.id_product_attribute;
            }
            params.groups = product.groups;
        }

        return post(params).then(function (response) {
            if (response.status !== 'success') {
                throw self.fail(response.message);
            }
            self.summary = response;

            return fetch(
                config.createOrderUrl
                    + '?buckarooWebsiteKey=' + encodeURIComponent(config.websiteKey)
                    + '&currency=' + encodeURIComponent(config.currencyCode)
                    + '&initialAmount=' + encodeURIComponent(response.total.amount),
                { method: 'post' }
            );
        }).then(function (response) {
            return response.json();
        }).then(function (order) {
            if (!order || !order.PaypalOrderId) {
                throw self.fail('');
            }

            return order.PaypalOrderId;
        });
    };

    /* JSON patch of the PayPal order for the shop's carriers and total. */
    ExpressButton.prototype.patchOperations = function (summary) {
        var amount = summary.paypalAmount;
        var operations = [{ op: 'replace', path: PURCHASE_UNIT + '/amount', value: amount }];
        var options;

        if (!this.virtual && summary.shippingMethods && summary.shippingMethods.length) {
            options = summary.shippingMethods.map(function (method) {
                var selected = method.identifier === summary.selectedShipping;

                return {
                    id: method.identifier,
                    label: method.label,
                    type: 'SHIPPING',
                    selected: selected,
                    amount: {
                        currency_code: amount.currency_code,
                        // The selected option must match the shipping in the amount breakdown.
                        value: selected ? amount.breakdown.shipping.value : method.amount
                    }
                };
            });
            operations.push({
                op: this.optionsPatched ? 'replace' : 'add',
                path: PURCHASE_UNIT + '/shipping/options',
                value: options
            });
            this.optionsPatched = true;
        }

        return operations;
    };

    ExpressButton.prototype.onShippingChange = function (data, actions) {
        var self = this;
        var address = data.shipping_address || {};
        var option = data.selected_shipping_option;

        return post({
            action: 'contact',
            contact: JSON.stringify({
                countryCode: address.country_code || '',
                administrativeArea: address.state || '',
                postalCode: address.postal_code || '',
                locality: address.city || ''
            })
        }).then(function (response) {
            var wanted = option && option.id;

            if (response.status !== 'success' || !wanted || wanted === response.selectedShipping) {
                return response;
            }

            // The carrier the shopper picked in PayPal, when it is available for the address.
            return post({ action: 'shipping', identifier: wanted }).then(function (changed) {
                return changed.status === 'success' ? changed : response;
            });
        }).then(function (response) {
            if (response.status !== 'success') {
                self.failure = response.message || config.messages.error;

                return actions.reject();
            }

            self.summary = response;
            self.failure = '';

            return actions.order.patch(self.patchOperations(response));
        });
    };

    /*
     * The approved PayPal order: payer, shipping address, carrier and amount
     * go to the shop, which checks them and creates the customer and addresses.
     */
    ExpressButton.prototype.onApprove = function (data, actions) {
        var self = this;

        return actions.order.get().then(function (order) {
            var payer = order.payer || {};
            var unit = (order.purchase_units || [])[0] || {};
            var shipping = unit.shipping || {};
            var payerName = payer.name || {};
            var name = { givenName: clean(payerName.given_name), familyName: clean(payerName.surname) };
            var shippingName = shipping.name && shipping.name.full_name ? splitName(shipping.name.full_name) : name;
            var phone = payer.phone && payer.phone.phone_number ? payer.phone.phone_number.national_number : '';
            var billingAddress = payer.address && payer.address.address_line_1 ? payer.address : shipping.address;
            var identifier = '';
            var i;

            for (i = 0; shipping.options && i < shipping.options.length; i++) {
                if (shipping.options[i].selected) {
                    identifier = shipping.options[i].id;
                }
            }
            if (!identifier && self.summary && self.summary.selectedShipping) {
                identifier = self.summary.selectedShipping;
            }

            return post({
                action: 'authorize',
                payment: JSON.stringify({
                    billingContact: toContact(billingAddress, name.givenName ? name : shippingName, payer.email_address, phone),
                    shippingContact: toContact(shipping.address, shippingName, payer.email_address, phone)
                }),
                identifier: identifier,
                amount: unit.amount ? unit.amount.value : ''
            });
        }).then(function (response) {
            if (response.status !== 'success') {
                self.showMessage(response.message);

                return;
            }

            self.submit(response, data.orderID);
        }, function () {
            self.showMessage(config.messages.error);
        });
    };

    ExpressButton.prototype.submit = function (response, orderId) {
        var form = document.createElement('form');
        var fields = response.fields || {};
        var key;

        fields.paypal_order_id = orderId;
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

    function remove(container) {
        if (container.parentNode) {
            container.parentNode.removeChild(container);
        }
    }

    function initButtons() {
        var containers = document.querySelectorAll('.buckaroo-paypal-express:not([data-buckaroo-ready])');
        var i;

        if (!containers.length) {
            return;
        }

        for (i = 0; i < containers.length; i++) {
            containers[i].setAttribute('data-buckaroo-ready', '1');
        }

        loadSdk().then(function (loaded) {
            var j;
            for (j = 0; j < containers.length; j++) {
                if (!loaded) {
                    remove(containers[j]);
                    continue;
                }
                (function (container) {
                    try {
                        new ExpressButton(container).mount().catch(function () {
                            remove(container);
                        });
                    } catch (e) {
                        remove(container);
                    }
                }(containers[j]));
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
