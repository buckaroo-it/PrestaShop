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
 */
document.addEventListener('DOMContentLoaded', () => {
    if (!window.buckaroo_error_msg) return;

    const wrapper = document.createElement('div');
    wrapper.className = 'js-buckaroo-payment-error';

    // The message comes from the URL (buckaroo_error_msg), so it is set as text,
    // never as HTML: a crafted link must not be able to inject markup or script.
    const article = document.createElement('article');
    article.className = 'alert alert-danger';
    article.setAttribute('role', 'alert');
    article.setAttribute('data-alert', 'danger');

    const list = document.createElement('ul');
    list.id = 'buckaroo-notifications';

    const item = document.createElement('li');
    item.textContent = String(window.buckaroo_error_msg);

    list.appendChild(item);
    article.appendChild(list);
    wrapper.appendChild(article);

    // '.cart-grid-body' is the classic multi-step checkout column;
    // '.checkout-grid__content' covers both the Hummingbird checkout and the
    // PrestaShop 9.2 one-page checkout, which do not render '.cart-grid-body'.
    const target = document.querySelector(
        '.cart-grid-body, .checkout-grid__content, .one-page-checkout, #content'
    );

    if (!target) {
        return;
    }

    target.prepend(wrapper);
});
