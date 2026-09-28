document.addEventListener('DOMContentLoaded', () => {
    if (!window.buckaroo_error_msg) return;

    const wrapper = document.createElement('div');
    wrapper.className = 'js-buckaroo-payment-error';

    wrapper.innerHTML = `
    <article class="alert alert-danger" role="alert" data-alert="danger">
      <ul id="buckaroo-notifications">
        <li>${buckaroo_error_msg}</li>
      </ul>
    </article>
  `;

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
