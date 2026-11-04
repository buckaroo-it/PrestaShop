import { afterEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import { enableAutoUnmount } from '@vue/test-utils';
import VerificationMethods from '@/pages/VerificationMethods.vue';
import VerificationMethodBlock from '@/components/VerificationMethodBlock.vue';
import Loading from '@/components/Loading.vue';
import { mountApp, mountAndSettle, flushPromises } from '../../helpers';
import { respond, callsTo } from '../../mocks/api';

vi.mock('@/lib/api', () => import('../../mocks/api'));

enableAutoUnmount(afterEach);

// The detail form has its own spec; here we only check which method it receives.
const DefaultVerificationConfig = {
    name: 'DefaultVerificationConfig',
    props: ['payment'],
    template: '<div class="config-stub">config for {{ payment.name }}</div>',
};

// VerificationMethods controller response
const payments = [
    { id: 1, name: 'idin', icon: 'iDIN.svg', mode: 'test' },
    { id: 2, name: 'kyc', icon: '', mode: 'off' },
];

const mountPage = (provide = {}) => {
    respond('buckaroo_config_verificationMethods', { status: true, payments }, 'get');

    return mountAndSettle(VerificationMethods, {
        provide: { view: ref('verification_methods'), ...provide },
        global: { stubs: { DefaultVerificationConfig } },
    });
};

const backButton = (wrapper) => wrapper.find('button[aria-label="Back"]');

describe('VerificationMethods page', () => {
    it('lists every verification method as a block under the page header', async () => {
        const wrapper = await mountPage();

        expect(callsTo('buckaroo_config_verificationMethods', 'get')).toHaveLength(1);
        expect(wrapper.text()).toContain('Configure your verification method settings');
        expect(wrapper.findAllComponents(VerificationMethodBlock).map((b) => b.props('payment').name)).toEqual(['idin', 'kyc']);
        expect(wrapper.find('.config-stub').exists()).toBe(false);
        expect(backButton(wrapper).exists()).toBe(false);
    });

    it('shows a spinner while the methods load', async () => {
        respond('buckaroo_config_verificationMethods', { status: true, payments }, 'get');
        const wrapper = mountApp(VerificationMethods, { global: { stubs: { DefaultVerificationConfig } } });

        expect(wrapper.findComponent(Loading).exists()).toBe(true);
        await flushPromises();
        expect(wrapper.findComponent(Loading).exists()).toBe(false);
    });

    it('shows no methods when the list cannot be loaded', async () => {
        respond('buckaroo_config_verificationMethods', { status: false }, 'get');
        const wrapper = await mountAndSettle(VerificationMethods, { global: { stubs: { DefaultVerificationConfig } } });

        expect(wrapper.findAllComponents(VerificationMethodBlock)).toHaveLength(0);
    });

    it('opens the configuration of a method and puts it in the URL', async () => {
        const wrapper = await mountPage();

        await wrapper.findComponent(VerificationMethodBlock).vm.$emit('selectPayment');

        expect(wrapper.get('h1').text()).toBe('iDIN');
        expect(wrapper.text()).toContain('Configure your iDIN settings');
        expect(wrapper.get('img').attributes('src'))
            .toBe('https://shop.test/modules/buckaroo3/views/img/buckaroo/Identification methods/SVG/iDIN.svg');
        expect(wrapper.get('.config-stub').text()).toBe('config for idin');
        expect(wrapper.findAllComponents(VerificationMethodBlock)).toHaveLength(0);
        expect(window.location.hash).toBe('#verification_methods/idin');
    });

    it('leaves out the header icon when the method has none', async () => {
        const wrapper = await mountPage();

        await wrapper.findAllComponents(VerificationMethodBlock)[1].vm.$emit('selectPayment');

        expect(wrapper.find('h1').exists()).toBe(true);
        expect(wrapper.find('img').exists()).toBe(false);
    });

    it('goes back to the list with the Back button and updates the URL', async () => {
        const wrapper = await mountPage();
        await wrapper.findComponent(VerificationMethodBlock).vm.$emit('selectPayment');

        await backButton(wrapper).trigger('click');

        expect(wrapper.find('.config-stub').exists()).toBe(false);
        expect(wrapper.findAllComponents(VerificationMethodBlock)).toHaveLength(2);
        expect(window.location.hash).toBe('#verification_methods');
    });

    it('asks before going back with unsaved changes and stays when the merchant cancels', async () => {
        const hasUnsavedChanges = ref(false);
        const wrapper = await mountPage({ hasUnsavedChanges });
        await wrapper.findComponent(VerificationMethodBlock).vm.$emit('selectPayment');
        hasUnsavedChanges.value = true;
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);

        await backButton(wrapper).trigger('click');

        expect(confirm).toHaveBeenCalledWith('You have unsaved changes. Leave without saving?');
        expect(wrapper.find('.config-stub').exists()).toBe(true);
        expect(hasUnsavedChanges.value).toBe(true);
    });

    it('drops the unsaved changes when the merchant confirms leaving', async () => {
        const hasUnsavedChanges = ref(false);
        const wrapper = await mountPage({ hasUnsavedChanges });
        await wrapper.findComponent(VerificationMethodBlock).vm.$emit('selectPayment');
        hasUnsavedChanges.value = true;
        vi.spyOn(window, 'confirm').mockReturnValue(true);

        await backButton(wrapper).trigger('click');

        expect(wrapper.find('.config-stub').exists()).toBe(false);
        expect(hasUnsavedChanges.value).toBe(false);
    });

    it('does not ask when there is nothing unsaved', async () => {
        const wrapper = await mountPage();
        await wrapper.findComponent(VerificationMethodBlock).vm.$emit('selectPayment');
        const confirm = vi.spyOn(window, 'confirm');

        await backButton(wrapper).trigger('click');

        expect(confirm).not.toHaveBeenCalled();
    });

    it('reopens the method named in the URL after a refresh', async () => {
        const initialRouteDetail = ref('idin');
        const wrapper = await mountPage({ initialRouteDetail });

        expect(wrapper.get('.config-stub').text()).toBe('config for idin');
        expect(initialRouteDetail.value).toBeNull();
    });

    it('shows the list for an unknown method in the URL', async () => {
        const initialRouteDetail = ref('does-not-exist');
        const wrapper = await mountPage({ initialRouteDetail });

        expect(wrapper.find('.config-stub').exists()).toBe(false);
        expect(wrapper.findAllComponents(VerificationMethodBlock)).toHaveLength(2);
        expect(initialRouteDetail.value).toBeNull();
    });
});
