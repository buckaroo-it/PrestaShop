/**
 * Keeps the open tab (and the open payment/verification method) in the URL hash,
 * e.g. "#payment_methods/ideal", so a page refresh or a shared link reopens it.
 */
export const VIEWS = ['settings', 'payment_methods', 'verification_methods', 'order_payment_methods'];

export const readRoute = (): { view: string; detail: string | null } => {
    const [view, detail] = window.location.hash.replace(/^#/, '').split('/');

    return {
        view: VIEWS.includes(view) ? view : 'settings',
        detail: detail ? decodeURIComponent(detail) : null,
    };
};

export const writeRoute = (view: string, detail: string | null = null): void => {
    const hash = detail ? `#${view}/${encodeURIComponent(detail)}` : `#${view}`;

    if (window.location.hash !== hash) {
        // replaceState keeps PrestaShop's own URL (token etc.) and adds no history entries
        window.history.replaceState(window.history.state, '', hash);
    }
};
