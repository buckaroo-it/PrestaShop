import { afterEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import { mount } from '@vue/test-utils';
import Menu from '@/components/Menu.vue';
import { mountApp } from '../helpers';

const mountMenu = (view = 'settings', hasUnsavedChanges = false) => {
    const provide = { view: ref(view), hasUnsavedChanges: ref(hasUnsavedChanges) };
    const wrapper = mountApp(Menu, { provide });
    return { wrapper, ...provide };
};
const tabs = (wrapper) => wrapper.findAll('[role="tab"]');

describe('Menu', () => {
    afterEach(() => vi.unstubAllGlobals());

    it('renders the four tabs with translated labels', () => {
        const { wrapper } = mountMenu();

        expect(tabs(wrapper).map((t) => t.text()))
            .toEqual(['Settings', 'Payment methods', 'Verification methods', 'Order payment methods'].map((s) => expect.stringMatching(new RegExp(s, 'i'))));
        expect(wrapper.find('[role="tablist"]').exists()).toBe(true);
    });

    it('marks the current view as selected', () => {
        const { wrapper } = mountMenu('verification_methods');

        expect(tabs(wrapper).map((t) => t.attributes('aria-selected'))).toEqual(['false', 'false', 'true', 'false']);
        expect(tabs(wrapper)[2].classes()).toContain('font-semibold');
        expect(tabs(wrapper)[2].find('i').classes()).toContain('text-primary');
        expect(tabs(wrapper)[0].find('i').classes()).toContain('text-white/50');
    });

    it('changes the view and emits changedView on click', async () => {
        const { wrapper, view } = mountMenu();

        await tabs(wrapper)[1].trigger('click');

        expect(view.value).toBe('payment_methods');
        expect(wrapper.emitted('changedView')).toEqual([['payment_methods']]);
        expect(tabs(wrapper)[1].attributes('aria-selected')).toBe('true');
    });

    it('can be operated with Enter and Space', async () => {
        const { wrapper, view } = mountMenu();

        await tabs(wrapper)[3].trigger('keydown', { key: 'Enter' });
        expect(view.value).toBe('order_payment_methods');

        await tabs(wrapper)[2].trigger('keydown', { key: ' ' });
        expect(view.value).toBe('verification_methods');

        expect(wrapper.emitted('changedView')).toEqual([['order_payment_methods'], ['verification_methods']]);
    });

    it('asks before leaving unsaved changes and stays when cancelled', async () => {
        const confirm = vi.fn(() => false);
        vi.stubGlobal('confirm', confirm);
        const { wrapper, view, hasUnsavedChanges } = mountMenu('settings', true);

        await tabs(wrapper)[1].trigger('click');

        expect(confirm).toHaveBeenCalledWith(expect.any(String));
        expect(confirm.mock.calls[0][0]).not.toBe('dashboard.common.leave_unsaved_confirm');
        expect(view.value).toBe('settings');
        expect(hasUnsavedChanges.value).toBe(true);
        expect(wrapper.emitted('changedView')).toBeUndefined();
    });

    it('leaves and resets the unsaved flag when confirmed', async () => {
        vi.stubGlobal('confirm', vi.fn(() => true));
        const { wrapper, view, hasUnsavedChanges } = mountMenu('settings', true);

        await tabs(wrapper)[1].trigger('click');

        expect(view.value).toBe('payment_methods');
        expect(hasUnsavedChanges.value).toBe(false);
        expect(wrapper.emitted('changedView')).toEqual([['payment_methods']]);
    });

    it('does not ask without unsaved changes', async () => {
        const confirm = vi.fn();
        vi.stubGlobal('confirm', confirm);
        const { wrapper } = mountMenu();

        await tabs(wrapper)[1].trigger('click');

        expect(confirm).not.toHaveBeenCalled();
    });

    it('works without a provided unsaved-changes flag', async () => {
        const view = ref('settings');
        const wrapper = mount(Menu, { global: { provide: { view } } });

        await tabs(wrapper)[1].trigger('click');

        expect(view.value).toBe('payment_methods');
    });

    it('renders the language selector twice (mobile and sidebar, the latter dropping up)', () => {
        const { wrapper } = mountMenu();
        const selectors = wrapper.findAllComponents({ name: 'LanguageSelector' });

        expect(selectors).toHaveLength(2);
        expect(selectors[0].props('dropUp')).toBe(false);
        expect(selectors[1].props('dropUp')).toBe(true);
    });
});
