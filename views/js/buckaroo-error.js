document.addEventListener('DOMContentLoaded', () => {
    if (!window.buckaroo_error_msg) return;

    const wrapper = document.createElement('div');
    wrapper.className = 'container js-buckaroo-payment-error my-3';

    wrapper.innerHTML = `
    <article class="alert alert-danger" role="alert" data-alert="danger">
      <ul id="buckaroo-notifications">
        <li>${buckaroo_error_msg}</li>
      </ul>
    </article>
  `;

    const target =
        document.querySelector('.cart-grid-body');

    target.prepend(wrapper);
});
