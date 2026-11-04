import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive, ref } from 'vue';
import { enableAutoUnmount } from '@vue/test-utils';
import DefaultVerificationConfig from '@/components/verifications/DefaultVerificationConfig.vue';
import Loading from '@/components/Loading.vue';
import { mountApp, mountAndSettle, flushPromises } from '../../helpers';
import { respond, fail, callsTo } from '../../mocks/api';

vi.mock('@/lib/api', () => import('../../mocks/api'));

const { toastr } = vi.hoisted(() => ({ toastr: { success: vi.fn(), error: vi.fn() } }));
vi.mock('@/lib/toastr', () => ({ useToastr: () => ({ toastr }) }));

enableAutoUnmount(afterEach);

// Stand-in for the country picker: shows the count and lets a test pick a country.
const CountryAvailability = {
    name: 'CountryAvailability',
    props: ['modelValue'],
    emits: ['update:modelValue'],
    template: `<div class="country-stub">{{ modelValue.length }} countries
        <button type="button" class="add-nl" @click="$emit('update:modelValue', [...modelValue, { id: 13, name: 'Netherlands', iso_code_2: 'NL' }])">add</button></div>`,
};

// ConfigService::getConfigArrayForMethod('idin')
const storedConfig = (overrides = {}) => ({
    mode: 'test',
    display_type: 'dropdown',
    frontend_label: 'Verify age',
    display_mode: 'global',
    countries: [{ id: 3, name: 'Belgium', iso_code_2: 'BE' }],
    ...overrides,
});

const mountConfig = async ({ config = storedConfig(), payment = reactive({ name: 'idin', mode: 'test' }), provide = {}, slots } = {}) => {
    respond('buckaroo_config_paymentMethod', { status: true, config: config === null ? null : { value: config } }, 'get');
    respond('buckaroo_config_paymentMethod', { status: true }, 'post');

    const wrapper = await mountAndSettle(DefaultVerificationConfig, {
        props: { payment },
        provide,
        slots,
        global: { components: { Loading }, stubs: { CountryAvailability } },
    });

    return { wrapper, payment };
};

const modeButtons = (wrapper) => wrapper.findAll('[role="group"] button');
const saveButton = (wrapper) => wrapper.findAll('button').find((b) => b.text() === 'Save');
const discardButton = (wrapper) => wrapper.findAll('button').find((b) => b.text() === 'Discard');
const unsavedStatus = (wrapper) => wrapper.find('[role="status"]');

beforeEach(() => {
    toastr.success.mockClear();
    toastr.error.mockClear();
});

describe('DefaultVerificationConfig – loading', () => {
    it('loads the configuration of the given method', async () => {
        await mountConfig();

        expect(callsTo('buckaroo_config_paymentMethod', 'get')).toHaveLength(1);
        expect(callsTo('buckaroo_config_paymentMethod', 'get')[0].query).toEqual({ paymentName: 'idin' });
    });

    it('shows a spinner until the configuration has loaded', async () => {
        respond('buckaroo_config_paymentMethod', { status: true, config: { value: storedConfig() } }, 'get');
        const wrapper = mountApp(DefaultVerificationConfig, {
            props: { payment: { name: 'idin' } },
            global: { components: { Loading }, stubs: { CountryAvailability } },
        });

        expect(wrapper.findComponent(Loading).exists()).toBe(true);
        expect(wrapper.find('#verification_frontend_label').exists()).toBe(false);

        await flushPromises();
        expect(wrapper.findComponent(Loading).exists()).toBe(false);
        expect(wrapper.find('#verification_frontend_label').exists()).toBe(true);
    });

    it('fills in the stored values', async () => {
        const { wrapper } = await mountConfig();

        expect(modeButtons(wrapper).map((b) => b.attributes('aria-pressed'))).toEqual(['false', 'true', 'false']);
        expect(wrapper.get('#verification_frontend_label').element.value).toBe('Verify age');
        expect(wrapper.get('#verification_display_mode').element.value).toBe('global');
        expect(wrapper.get('.country-stub').text()).toContain('1 countries');
    });

    it('falls back to an empty "off" configuration for a method that was never saved', async () => {
        const { wrapper } = await mountConfig({ config: null });

        expect(modeButtons(wrapper)[2].attributes('aria-pressed')).toBe('true');
        expect(wrapper.get('#verification_frontend_label').element.value).toBe('');
        expect(wrapper.get('.country-stub').text()).toContain('0 countries');
        expect(unsavedStatus(wrapper).exists()).toBe(false);
    });

    it('keeps the default form, without a save baseline, when loading is refused', async () => {
        respond('buckaroo_config_paymentMethod', { status: false }, 'get');
        const wrapper = await mountAndSettle(DefaultVerificationConfig, {
            props: { payment: { name: 'idin' } },
            global: { components: { Loading }, stubs: { CountryAvailability } },
        });

        await modeButtons(wrapper)[0].trigger('click');

        expect(unsavedStatus(wrapper).exists()).toBe(false);
        expect(saveButton(wrapper).attributes('disabled')).toBeDefined();
    });

    it('explains the default checkout name using the translated method name', async () => {
        const { wrapper } = await mountConfig();

        expect(wrapper.text()).toContain('Leave empty to show “iDIN”.');
        expect(wrapper.get('label[for="verification_frontend_label"].absolute').text()).toBe('iDIN');
    });

    it('offers product-specific and global display modes', async () => {
        const { wrapper } = await mountConfig();

        expect(wrapper.findAll('#verification_display_mode option').map((o) => [o.attributes('value'), o.text()]))
            .toEqual([['product', 'Specific products'], ['global', 'Global']]);
    });

    it('renders extra settings passed in the default slot', async () => {
        const { wrapper } = await mountConfig({ slots: { default: '<section class="extra">Extra iDIN setting</section>' } });

        expect(wrapper.get('.extra').text()).toBe('Extra iDIN setting');
    });
});

describe('DefaultVerificationConfig – editing', () => {
    it('switches the mode with an aria-pressed Live/Test/Off group', async () => {
        const { wrapper } = await mountConfig();
        const group = wrapper.get('[role="group"]');

        expect(group.attributes('aria-label')).toBe('Mode');
        expect(modeButtons(wrapper).map((b) => b.text())).toEqual(['Live', 'Test', 'Off']);

        await modeButtons(wrapper)[0].trigger('click');

        expect(modeButtons(wrapper).map((b) => b.attributes('aria-pressed'))).toEqual(['true', 'false', 'false']);
        expect(modeButtons(wrapper)[0].classes()).toContain('text-fifthly');
        expect(modeButtons(wrapper)[1].classes()).toContain('text-gray-500');
        modeButtons(wrapper).forEach((b) => expect(b.attributes('type')).toBe('button'));
    });

    it('marks the form dirty, enables Save and tells the dashboard', async () => {
        const hasUnsavedChanges = ref(false);
        const { wrapper } = await mountConfig({ provide: { hasUnsavedChanges } });

        expect(saveButton(wrapper).attributes('disabled')).toBeDefined();
        expect(discardButton(wrapper)).toBeUndefined();

        await wrapper.get('#verification_display_mode').setValue('product');

        expect(unsavedStatus(wrapper).text()).toBe('You have unsaved changes');
        expect(saveButton(wrapper).attributes('disabled')).toBeUndefined();
        expect(hasUnsavedChanges.value).toBe(true);
    });

    it('takes the countries chosen in the availability picker', async () => {
        const { wrapper } = await mountConfig();

        await wrapper.get('.add-nl').trigger('click');

        expect(wrapper.get('.country-stub').text()).toContain('2 countries');
        expect(unsavedStatus(wrapper).exists()).toBe(true);
    });

    it('Discard restores the loaded configuration', async () => {
        const hasUnsavedChanges = ref(false);
        const { wrapper } = await mountConfig({ provide: { hasUnsavedChanges } });

        await wrapper.get('#verification_frontend_label').setValue('Something else');
        await modeButtons(wrapper)[2].trigger('click');
        await discardButton(wrapper).trigger('click');

        expect(wrapper.get('#verification_frontend_label').element.value).toBe('Verify age');
        expect(modeButtons(wrapper)[1].attributes('aria-pressed')).toBe('true');
        expect(unsavedStatus(wrapper).exists()).toBe(false);
        expect(hasUnsavedChanges.value).toBe(false);
    });

    it('warns before leaving the page only with unsaved changes', async () => {
        const { wrapper } = await mountConfig();
        const leave = () => {
            const event = new Event('beforeunload', { cancelable: true });
            window.dispatchEvent(event);
            return event;
        };

        expect(leave().defaultPrevented).toBe(false);
        await wrapper.get('#verification_frontend_label').setValue('Changed');
        expect(leave().defaultPrevented).toBe(true);
    });

    it('stops warning and clears the dashboard flag when it is closed', async () => {
        const hasUnsavedChanges = ref(false);
        const { wrapper } = await mountConfig({ provide: { hasUnsavedChanges } });
        await wrapper.get('#verification_frontend_label').setValue('Changed');

        wrapper.unmount();

        expect(hasUnsavedChanges.value).toBe(false);
        const event = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(event);
        expect(event.defaultPrevented).toBe(false);
    });
});

describe('DefaultVerificationConfig – saving', () => {
    it('posts the whole configuration for the method', async () => {
        const { wrapper } = await mountConfig();

        await modeButtons(wrapper)[0].trigger('click');
        await wrapper.get('#verification_frontend_label').setValue('Verify with iDIN');
        await wrapper.get('.add-nl').trigger('click');
        await saveButton(wrapper).trigger('click');
        await flushPromises();

        const [call] = callsTo('buckaroo_config_paymentMethod', 'post');
        expect(call.query).toEqual({ paymentName: 'idin' });
        expect(call.payload).toEqual(storedConfig({
            mode: 'live',
            frontend_label: 'Verify with iDIN',
            countries: [
                { id: 3, name: 'Belgium', iso_code_2: 'BE' },
                { id: 13, name: 'Netherlands', iso_code_2: 'NL' },
            ],
        }));
    });

    it('confirms the save, updates the method tile mode and clears the dirty state', async () => {
        const hasUnsavedChanges = ref(false);
        const { wrapper, payment } = await mountConfig({ provide: { hasUnsavedChanges } });

        await modeButtons(wrapper)[0].trigger('click');
        await saveButton(wrapper).trigger('click');
        await flushPromises();

        expect(toastr.success).toHaveBeenCalledWith('Settings successfully updated.');
        expect(payment.mode).toBe('live');
        expect(unsavedStatus(wrapper).exists()).toBe(false);
        expect(saveButton(wrapper).attributes('disabled')).toBeDefined();
        expect(hasUnsavedChanges.value).toBe(false);
    });

    it('replaces the form with a spinner while the save request is running', async () => {
        // get and post share one useApi() instance, so its loading flag also hides the form while saving.
        const { wrapper } = await mountConfig();
        await modeButtons(wrapper)[0].trigger('click');

        wrapper.vm.updateConfig();
        await wrapper.vm.$nextTick();
        expect(wrapper.vm.saving).toBe(true);
        expect(wrapper.findComponent(Loading).exists()).toBe(true);
        expect(saveButton(wrapper)).toBeUndefined();

        await flushPromises();
        expect(wrapper.vm.saving).toBe(false);
        expect(saveButton(wrapper).attributes('disabled')).toBeDefined();
    });

    it('shows an error and keeps the changes when the server refuses them', async () => {
        const { wrapper, payment } = await mountConfig();
        respond('buckaroo_config_paymentMethod', { status: false }, 'post');

        await modeButtons(wrapper)[0].trigger('click');
        await saveButton(wrapper).trigger('click');
        await flushPromises();

        expect(toastr.error).toHaveBeenCalledWith('Something went wrong.');
        expect(toastr.success).not.toHaveBeenCalled();
        expect(payment.mode).toBe('test');
        expect(unsavedStatus(wrapper).exists()).toBe(true);
        expect(saveButton(wrapper).attributes('disabled')).toBeUndefined();
    });

    it('shows an error and keeps the changes when the save request fails', async () => {
        const { wrapper, payment } = await mountConfig();
        fail('buckaroo_config_paymentMethod', new Error('Network down'), 'post');

        await modeButtons(wrapper)[0].trigger('click');
        await saveButton(wrapper).trigger('click');
        await flushPromises();

        expect(toastr.error).toHaveBeenCalledWith('Something went wrong.');
        expect(toastr.success).not.toHaveBeenCalled();
        expect(wrapper.vm.saving).toBe(false);
        expect(payment.mode).toBe('test');
        expect(unsavedStatus(wrapper).exists()).toBe(true);
        expect(saveButton(wrapper).attributes('disabled')).toBeUndefined();
    });
});
