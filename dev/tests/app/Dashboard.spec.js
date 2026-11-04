import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { defineComponent, h, inject, nextTick } from 'vue';
import Dashboard from '@/Dashboard.vue';

// Captures what Dashboard provides to its pages.
let provided;
const Probe = (name, extra = {}) => defineComponent({
    name,
    ...extra,
    setup() {
        provided = {
            view: inject('view'),
            hasUnsavedChanges: inject('hasUnsavedChanges'),
            csrfToken: inject('csrfToken'),
            baseUrl: inject('baseUrl'),
            adminUrl: inject('adminUrl'),
            initialRouteDetail: inject('initialRouteDetail'),
        };
    },
    render() {
        return h('div', { 'data-page': name });
    },
});

const stubs = {
    Settings: Probe('Settings'),
    PaymentMethods: Probe('PaymentMethods', { data: () => ({ selectedPayment: 'ideal' }) }),
    VerificationMethods: Probe('VerificationMethods', { data: () => ({ selectedVerification: 'idin' }) }),
    OrderPaymentMethods: Probe('OrderPaymentMethods'),
};

const mountDashboard = (hash = '') => {
    window.history.replaceState(null, '', `/admin?token=x${hash}`);

    return mount(Dashboard, {
        props: { token: 'csrf', baseUrl: 'https://shop.test', adminUrl: 'https://shop.test/admin-dev/' },
        global: { stubs },
    });
};

const shownPages = (wrapper) => wrapper.findAll('[data-page]').map((el) => el.attributes('data-page'));
const tab = (wrapper, index) => wrapper.findAll('[role="tab"]')[index];

describe('Dashboard', () => {
    it('shows the settings page without a hash and writes #settings', () => {
        const wrapper = mountDashboard();

        expect(shownPages(wrapper)).toEqual(['Settings']);
        expect(window.location.hash).toBe('#settings');
        expect(window.location.search).toBe('?token=x');
    });

    it.each([
        ['#settings', 'Settings'],
        ['#payment_methods', 'PaymentMethods'],
        ['#verification_methods', 'VerificationMethods'],
        ['#order_payment_methods', 'OrderPaymentMethods'],
    ])('opens the view from the hash %s', (hash, page) => {
        const wrapper = mountDashboard(hash);

        expect(shownPages(wrapper)).toEqual([page]);
        expect(window.location.hash).toBe(hash);
    });

    it('falls back to settings for an unknown view and rewrites the hash', () => {
        const wrapper = mountDashboard('#unknown');

        expect(shownPages(wrapper)).toEqual(['Settings']);
        expect(window.location.hash).toBe('#settings');
    });

    it('provides the detail from the hash once and keeps it in the hash', () => {
        mountDashboard('#payment_methods/ideal');

        expect(provided.initialRouteDetail.value).toBe('ideal');
        expect(window.location.hash).toBe('#payment_methods/ideal');
    });

    it('provides the props, the view and the unsaved-changes flag', () => {
        mountDashboard();

        expect(provided.csrfToken).toBe('csrf');
        expect(provided.baseUrl).toBe('https://shop.test');
        expect(provided.adminUrl).toBe('https://shop.test/admin-dev/');
        expect(provided.view.value).toBe('settings');
        expect(provided.hasUnsavedChanges.value).toBe(false);
        expect(provided.initialRouteDetail.value).toBeNull();
    });

    it('switches page and hash when a menu tab is clicked, without history entries', async () => {
        const wrapper = mountDashboard();
        const push = vi.spyOn(window.history, 'pushState');

        await tab(wrapper, 3).trigger('click');
        expect(shownPages(wrapper)).toEqual(['OrderPaymentMethods']);
        expect(window.location.hash).toBe('#order_payment_methods');

        await tab(wrapper, 2).trigger('click');
        expect(shownPages(wrapper)).toEqual(['VerificationMethods']);
        expect(window.location.hash).toBe('#verification_methods');

        expect(push).not.toHaveBeenCalled();
    });

    it('drops the detail from the hash when the view changes', async () => {
        const wrapper = mountDashboard('#payment_methods/ideal');

        await tab(wrapper, 0).trigger('click');

        expect(window.location.hash).toBe('#settings');
    });

    it('closes the open payment method when its tab is clicked again', async () => {
        const wrapper = mountDashboard('#payment_methods');
        const page = wrapper.findComponent({ name: 'PaymentMethods' });
        expect(page.vm.selectedPayment).toBe('ideal');

        await tab(wrapper, 1).trigger('click');

        expect(page.vm.selectedPayment).toBeNull();
    });

    it('closes the open verification method when its tab is clicked again', async () => {
        const wrapper = mountDashboard('#verification_methods');
        const page = wrapper.findComponent({ name: 'VerificationMethods' });

        await tab(wrapper, 2).trigger('click');

        expect(page.vm.selectedVerification).toBeNull();
    });

    it('ignores the reset when the method page is not mounted', async () => {
        const wrapper = mountDashboard();

        // Calls changedView while paymentMethodsRef is still null
        wrapper.vm.changedView('payment_methods');
        wrapper.vm.changedView('verification_methods');
        await nextTick();

        expect(shownPages(wrapper)).toEqual(['Settings']);
    });
});
