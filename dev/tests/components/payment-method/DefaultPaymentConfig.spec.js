import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { enableAutoUnmount } from '@vue/test-utils';
import { defineComponent, h, inject, reactive, ref } from 'vue';
import DefaultPaymentConfig from '@/components/payments/DefaultPaymentConfig.vue';
import ApplePayPaymentConfig from '@/components/payments/ApplePayPaymentConfig.vue';
import Loading from '@/components/Loading.vue';
import { flushPromises, mountAndSettle, mountApp } from '../../helpers';
import { callsTo, fail, respond } from '../../mocks/api';
import { COUNTRIES, resetCountries, respondCountries } from './countries';

vi.mock('@/lib/api', () => import('../../mocks/api'));

enableAutoUnmount(afterEach);

const toastr = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn() }));
vi.mock('@/lib/toastr', () => ({ useToastr: () => ({ toastr }) }));

const ENDPOINT = 'buckaroo_config_paymentMethod';

const savedConfig = (overrides = {}) => ({
    mode: 'test',
    display_type: 'dropdown',
    frontend_label: 'Pay with PayPal',
    payment_fee: '1.50',
    payment_fee_allowed: true,
    min_order_amount: '10',
    max_order_amount: '500',
    countries: [],
    ...overrides,
});

const respondConfig = (value = savedConfig()) =>
    respond(ENDPOINT, { status: true, config: { value } }, 'get');

// Slot child that edits the injected config, like the per-method config components do.
const ExtraSetting = defineComponent({
    setup() {
        const config = inject('config');
        return () => h('button', { class: 'extra', onClick: () => { config.value.extra = 'yes'; } }, 'extra');
    },
});

const mountConfig = async ({ payment = { name: 'paypal', icon: 'PayPal.svg', mode: 'test' }, hasUnsavedChanges = ref(false), slot } = {}) => {
    const reactivePayment = reactive(payment);
    const wrapper = await mountAndSettle(DefaultPaymentConfig, {
        props: { payment: reactivePayment },
        slots: slot ? { default: slot } : {},
        provide: { hasUnsavedChanges },
        global: { components: { Loading } },
    });

    return { wrapper, payment: reactivePayment, hasUnsavedChanges };
};

const modeButtons = (wrapper) => wrapper.findAll('[role="group"][aria-label="Mode"] button');
const saveButton = (wrapper) => wrapper.findAll('button').find((b) => b.text() === 'Save');
const discardButton = (wrapper) => wrapper.findAll('button').find((b) => b.text() === 'Discard');
const unsavedNotice = (wrapper) => wrapper.find('[role="status"]');

describe('DefaultPaymentConfig', () => {
    beforeEach(() => {
        toastr.success.mockClear();
        toastr.error.mockClear();
        resetCountries();
        respondCountries();
    });

    describe('loading', () => {
        it('shows a spinner until the configuration arrives', async () => {
            respondConfig();
            const wrapper = mountApp(DefaultPaymentConfig, {
                props: { payment: reactive({ name: 'paypal' }) },
                global: { components: { Loading } },
            });

            expect(wrapper.find('svg.animate-spin').exists()).toBe(true);
            expect(wrapper.find('#fee').exists()).toBe(false);

            await flushPromises();
            expect(wrapper.find('svg.animate-spin').exists()).toBe(false);
            expect(wrapper.find('#fee').exists()).toBe(true);
        });

        it('requests the configuration of its payment method', async () => {
            respondConfig();
            await mountConfig();

            expect(callsTo(ENDPOINT, 'get').map((c) => c.query)).toEqual([{ paymentName: 'paypal' }]);
        });

        it('fills the form with the saved values', async () => {
            respondConfig();
            const { wrapper } = await mountConfig();

            expect(modeButtons(wrapper).map((b) => b.attributes('aria-pressed'))).toEqual(['false', 'true', 'false']);
            expect(wrapper.get('#frontend_label').element.value).toBe('Pay with PayPal');
            expect(wrapper.get('#fee').element.value).toBe('1.50');
            expect(wrapper.get('#min_order_amount').element.value).toBe('10');
            expect(wrapper.get('#max_order_amount').element.value).toBe('500');
            expect(wrapper.text()).toContain('Leave empty to show “PayPal”.');
            expect(unsavedNotice(wrapper).exists()).toBe(false);
            expect(saveButton(wrapper).attributes('disabled')).toBeDefined();
        });

        it('falls back to an empty configuration when the server sends none', async () => {
            respond(ENDPOINT, { status: true, config: null }, 'get');
            const { wrapper } = await mountConfig();

            expect(modeButtons(wrapper).map((b) => b.attributes('aria-pressed'))).toEqual(['false', 'false', 'true']);
            expect(wrapper.get('#fee').element.value).toBe('');
            expect(unsavedNotice(wrapper).exists()).toBe(false);
        });

        it('copes with a stored configuration that lacks the newer fields', async () => {
            respondConfig({ mode: 'live' });
            const { wrapper, hasUnsavedChanges } = await mountConfig();

            expect(wrapper.get('#fee').element.value).toBe('');
            expect(wrapper.text()).toContain('shown in all countries');
            expect(unsavedNotice(wrapper).exists()).toBe(false);
            expect(hasUnsavedChanges.value).toBe(false);
        });

        // The numeric clean-up watchers must not rewrite stored values right after loading.
        it('is not dirty after loading numeric amounts', async () => {
            respondConfig(savedConfig({ payment_fee: 1.5, min_order_amount: 10 }));
            const { wrapper, hasUnsavedChanges } = await mountConfig();
            await flushPromises();

            expect(wrapper.get('#fee').element.value).toBe('1.5');
            expect(unsavedNotice(wrapper).exists()).toBe(false);
            expect(hasUnsavedChanges.value).toBe(false);
        });

        it('treats a typed amount equal to a stored number as unchanged', async () => {
            respondConfig(savedConfig({ payment_fee: 1.5, min_order_amount: 10 }));
            const { wrapper, hasUnsavedChanges } = await mountConfig();

            await wrapper.get('#fee').setValue('1,5');
            await wrapper.get('#min_order_amount').setValue('10');
            await flushPromises();

            expect(wrapper.get('#fee').element.value).toBe('1.5');
            expect(unsavedNotice(wrapper).exists()).toBe(false);
            expect(hasUnsavedChanges.value).toBe(false);
        });

        // The shop ignores fees of zero or less, so a negative fee cannot be typed; a stored one
        // is shown unchanged and flagged instead of silently turning into a positive fee.
        it('does not silently change a stored negative fee', async () => {
            respondConfig(savedConfig({ payment_fee: '-1' }));
            const { wrapper, hasUnsavedChanges } = await mountConfig();
            await flushPromises();

            expect(wrapper.get('#fee').element.value).toBe('-1');
            expect(wrapper.get('#fee-error').text()).toBe('Enter an amount such as 1.50 or a percentage such as 3%.');
            expect(unsavedNotice(wrapper).exists()).toBe(false);
            expect(hasUnsavedChanges.value).toBe(false);
        });

        it('keeps the stored values after discarding changes', async () => {
            respondConfig(savedConfig({ payment_fee: 1.5 }));
            const { wrapper, hasUnsavedChanges } = await mountConfig();
            await wrapper.get('#fee').setValue('2');
            await flushPromises();

            await discardButton(wrapper).trigger('click');
            await flushPromises();

            expect(wrapper.get('#fee').element.value).toBe('1.5');
            expect(hasUnsavedChanges.value).toBe(false);
        });

        it('shows an error and an empty form when the request fails', async () => {
            fail(ENDPOINT, new Error('Network Error'), 'get');
            const { wrapper, hasUnsavedChanges } = await mountConfig();

            expect(toastr.error).toHaveBeenCalledWith('Something went wrong.');
            expect(wrapper.find('svg.animate-spin').exists()).toBe(false);
            expect(wrapper.get('#fee').element.value).toBe('');
            expect(unsavedNotice(wrapper).exists()).toBe(false);
            expect(hasUnsavedChanges.value).toBe(false);
        });

        it('keeps the defaults and never reports changes when loading fails', async () => {
            respond(ENDPOINT, { status: false }, 'get');
            const { wrapper, hasUnsavedChanges } = await mountConfig();

            await modeButtons(wrapper)[0].trigger('click');

            expect(unsavedNotice(wrapper).exists()).toBe(false);
            expect(hasUnsavedChanges.value).toBe(false);
            expect(saveButton(wrapper).attributes('disabled')).toBeDefined();
        });
    });

    describe('sections', () => {
        it('shows the method settings slot for methods with extra settings', async () => {
            respondConfig();
            const { wrapper } = await mountConfig({ slot: '<p class="slotted">PayPal extras</p>' });

            expect(wrapper.find('.method-settings .slotted').exists()).toBe(true);
            expect(wrapper.text()).toContain('PayPal settings');
        });

        it.each(['afterpay', 'billink', 'creditcard', 'paypal', 'paybybank', 'payperemail', 'transfer', 'giftcard', 'googlepay', 'applepay', 'clicktopay'])(
            'shows the method settings section for %s',
            async (name) => {
                respondConfig();
                const { wrapper } = await mountConfig({ payment: { name, mode: 'test' }, slot: '<p class="slotted">x</p>' });

                expect(wrapper.find('.method-settings .slotted').exists()).toBe(true);
            },
        );

        it('renders the Apple Pay settings', async () => {
            // Keep Apple's SDK script out of the document (no network).
            const append = document.head.appendChild.bind(document.head);
            vi.spyOn(document.head, 'appendChild').mockImplementation((node) => (node.tagName === 'SCRIPT' ? node : append(node)));
            respondConfig(savedConfig({ merchant_guid: 'guid-123' }));
            const { wrapper } = await mountConfig({ payment: { name: 'applepay', mode: 'test' }, slot: ApplePayPaymentConfig });

            expect(wrapper.text()).toContain('Apple Pay settings');
            expect(wrapper.get('.method-settings #applepay_merchant_guid').element.value).toBe('guid-123');
            vi.restoreAllMocks();
        });

        // In3 and Klarna have no settings of their own (their components render an empty div).
        it.each(['ideal', 'in3', 'klarna'])('hides the method settings section for %s', async (name) => {
            respondConfig();
            const { wrapper } = await mountConfig({ payment: { name, mode: 'live' }, slot: '<p class="slotted">x</p>' });

            expect(wrapper.find('.method-settings').exists()).toBe(false);
        });

        it('hides the method settings section for methods without extra settings', async () => {
            respondConfig();
            const { wrapper } = await mountConfig({ payment: { name: 'ideal', mode: 'live' }, slot: '<p class="slotted">x</p>' });

            expect(wrapper.find('.method-settings').exists()).toBe(false);
            expect(wrapper.text()).toContain('Leave empty to show “iDEAL | Wero”.');
        });

        it('hides the fee section when the method does not allow a fee', async () => {
            respondConfig(savedConfig({ payment_fee_allowed: false }));
            const { wrapper } = await mountConfig();

            expect(wrapper.find('#fee').exists()).toBe(false);
            expect(wrapper.text()).not.toContain('Payment fee');
        });

        it('provides the config to the per-method settings in the slot', async () => {
            respondConfig();
            respond(ENDPOINT, { status: true }, 'post');
            const { wrapper } = await mountConfig({ slot: ExtraSetting });

            await wrapper.get('button.extra').trigger('click');
            expect(unsavedNotice(wrapper).exists()).toBe(true);

            await saveButton(wrapper).trigger('click');
            await flushPromises();
            expect(callsTo(ENDPOINT, 'post')[0].payload.extra).toBe('yes');
        });
    });

    describe('unsaved changes', () => {
        it('marks the form dirty, enables saving and tells the page', async () => {
            respondConfig();
            const { wrapper, hasUnsavedChanges } = await mountConfig();

            await modeButtons(wrapper)[0].trigger('click');

            expect(modeButtons(wrapper)[0].attributes('aria-pressed')).toBe('true');
            expect(unsavedNotice(wrapper).text()).toBe('You have unsaved changes');
            expect(saveButton(wrapper).attributes('disabled')).toBeUndefined();
            expect(hasUnsavedChanges.value).toBe(true);
        });

        it('is clean again after changing a value back', async () => {
            respondConfig();
            const { wrapper, hasUnsavedChanges } = await mountConfig();

            await wrapper.get('#frontend_label').setValue('Other');
            expect(unsavedNotice(wrapper).exists()).toBe(true);
            await wrapper.get('#frontend_label').setValue('Pay with PayPal');

            expect(unsavedNotice(wrapper).exists()).toBe(false);
            expect(hasUnsavedChanges.value).toBe(false);
        });

        it('discards the changes', async () => {
            respondConfig();
            const { wrapper, hasUnsavedChanges } = await mountConfig();
            await wrapper.get('#frontend_label').setValue('Other');
            await modeButtons(wrapper)[2].trigger('click');

            await discardButton(wrapper).trigger('click');

            expect(wrapper.get('#frontend_label').element.value).toBe('Pay with PayPal');
            expect(modeButtons(wrapper)[1].attributes('aria-pressed')).toBe('true');
            expect(discardButton(wrapper)).toBeUndefined();
            expect(hasUnsavedChanges.value).toBe(false);
        });

        it('marks the form dirty when a country is added', async () => {
            respondConfig();
            const { wrapper } = await mountConfig();

            await wrapper.findAll('button').find((b) => b.text() === 'Select all').trigger('click');

            expect(wrapper.text()).toContain(`${COUNTRIES.length} selected`);
            expect(unsavedNotice(wrapper).exists()).toBe(true);
        });

        it('asks the browser to confirm leaving only while there are unsaved changes', async () => {
            respondConfig();
            const { wrapper } = await mountConfig();

            const clean = new Event('beforeunload', { cancelable: true });
            window.dispatchEvent(clean);
            expect(clean.defaultPrevented).toBe(false);

            await modeButtons(wrapper)[0].trigger('click');
            const dirty = new Event('beforeunload', { cancelable: true });
            window.dispatchEvent(dirty);
            expect(dirty.defaultPrevented).toBe(true);
        });

        it('stops warning and resets the page flag when unmounted', async () => {
            respondConfig();
            const { wrapper, hasUnsavedChanges } = await mountConfig();
            await modeButtons(wrapper)[0].trigger('click');
            expect(hasUnsavedChanges.value).toBe(true);

            wrapper.unmount();

            expect(hasUnsavedChanges.value).toBe(false);
            const event = new Event('beforeunload', { cancelable: true });
            window.dispatchEvent(event);
            expect(event.defaultPrevented).toBe(false);
        });
    });

    describe('fee and amount validation', () => {
        it.each([
            ['1,50', '1.50'],
            ['2.5%', '2.5%'],
            ['3,5 %', '3.5%'],
            ['€ 4', '4'],
            ['1.2.3', '1.23'],
            ['abc', ''],
            ['%', ''],
        ])('cleans the fee input %j to %j', async (typed, expected) => {
            respondConfig(savedConfig({ payment_fee: null }));
            const { wrapper } = await mountConfig();

            await wrapper.get('#fee').setValue(typed);
            await flushPromises();

            expect(wrapper.get('#fee').element.value).toBe(expected);
            expect(wrapper.find('#fee-error').exists()).toBe(false);
            expect(wrapper.get('#fee').attributes('aria-invalid')).toBe('false');
        });

        it.each(['1.', '.5'])('rejects the incomplete fee %j and blocks saving', async (typed) => {
            respondConfig();
            const { wrapper } = await mountConfig();

            await wrapper.get('#fee').setValue(typed);
            await flushPromises();

            expect(wrapper.get('#fee-error').text()).toBe('Enter an amount such as 1.50 or a percentage such as 3%.');
            expect(wrapper.get('#fee-error').attributes('role')).toBe('alert');
            expect(wrapper.get('#fee').attributes('aria-invalid')).toBe('true');
            expect(wrapper.get('#fee').classes()).toContain('border-red-400');
            expect(saveButton(wrapper).attributes('disabled')).toBeDefined();
        });

        it('keeps only numbers in the order amounts', async () => {
            respondConfig();
            const { wrapper } = await mountConfig();

            await wrapper.get('#min_order_amount').setValue('5,5x');
            await wrapper.get('#max_order_amount').setValue('1.000,00');
            await flushPromises();

            expect(wrapper.get('#min_order_amount').element.value).toBe('5.5');
            // A thousands separator is not understood: "1.000,00" becomes 1.00000
            expect(wrapper.get('#max_order_amount').element.value).toBe('1.00000');
        });

        it('does not accept a percentage in the order amounts', async () => {
            respondConfig();
            const { wrapper } = await mountConfig();

            await wrapper.get('#min_order_amount').setValue('5%');
            await flushPromises();

            expect(wrapper.get('#min_order_amount').element.value).toBe('5');
        });

        it('rejects a minimum above the maximum and blocks saving', async () => {
            respondConfig();
            const { wrapper } = await mountConfig();

            await wrapper.get('#min_order_amount').setValue('600');
            await flushPromises();

            expect(wrapper.text()).toContain('The minimum order amount cannot be higher than the maximum.');
            expect(wrapper.get('#min_order_amount').classes()).toContain('border-red-400');
            expect(wrapper.get('#max_order_amount').classes()).toContain('border-red-400');
            expect(saveButton(wrapper).attributes('disabled')).toBeDefined();

            wrapper.vm.updateConfig(); // the guard, even if the disabled button is bypassed
            await flushPromises();
            expect(callsTo(ENDPOINT, 'post')).toHaveLength(0);
        });

        it('accepts a minimum without a maximum', async () => {
            respondConfig(savedConfig({ max_order_amount: '' }));
            const { wrapper } = await mountConfig();

            await wrapper.get('#min_order_amount').setValue('600');
            await flushPromises();

            expect(wrapper.text()).not.toContain('cannot be higher');
            expect(saveButton(wrapper).attributes('disabled')).toBeUndefined();
        });
    });

    describe('saving', () => {
        it('posts the configuration, updates the tile mode and confirms', async () => {
            respondConfig();
            respond(ENDPOINT, { status: true }, 'post');
            const { wrapper, payment, hasUnsavedChanges } = await mountConfig();

            await modeButtons(wrapper)[0].trigger('click');
            await wrapper.get('#fee').setValue('2,25');
            await flushPromises();
            await saveButton(wrapper).trigger('click');
            await flushPromises();

            const [call] = callsTo(ENDPOINT, 'post');
            expect(call.query).toEqual({ paymentName: 'paypal' });
            expect(call.payload).toEqual(savedConfig({ mode: 'live', payment_fee: '2.25' }));
            expect(payment.mode).toBe('live');
            expect(toastr.success).toHaveBeenCalledWith('Settings successfully updated.');
            expect(unsavedNotice(wrapper).exists()).toBe(false);
            expect(hasUnsavedChanges.value).toBe(false);
            expect(saveButton(wrapper).attributes('disabled')).toBeDefined();
        });

        it('disables the save button while saving', async () => {
            respondConfig();
            respond(ENDPOINT, { status: true }, 'post');
            const { wrapper } = await mountConfig();
            await modeButtons(wrapper)[0].trigger('click');

            await saveButton(wrapper).trigger('click');
            expect(saveButton(wrapper).attributes('disabled')).toBeDefined();
            await flushPromises();
        });

        it('shows an error and keeps the changes when the server refuses', async () => {
            respondConfig();
            respond(ENDPOINT, { status: false }, 'post');
            const { wrapper, payment } = await mountConfig();

            await modeButtons(wrapper)[0].trigger('click');
            await saveButton(wrapper).trigger('click');
            await flushPromises();

            expect(toastr.error).toHaveBeenCalledWith('Something went wrong.');
            expect(toastr.success).not.toHaveBeenCalled();
            expect(payment.mode).toBe('test');
            expect(unsavedNotice(wrapper).exists()).toBe(true);
            expect(saveButton(wrapper).attributes('disabled')).toBeUndefined();
        });

        it('shows an error, keeps the changes and can save again when the request fails', async () => {
            respondConfig();
            fail(ENDPOINT, new Error('Network Error'), 'post');
            const { wrapper, payment, hasUnsavedChanges } = await mountConfig();

            await modeButtons(wrapper)[0].trigger('click');
            await saveButton(wrapper).trigger('click');
            await flushPromises();

            expect(toastr.error).toHaveBeenCalledWith('Something went wrong.');
            expect(toastr.success).not.toHaveBeenCalled();
            expect(payment.mode).toBe('test');
            expect(unsavedNotice(wrapper).exists()).toBe(true);
            expect(hasUnsavedChanges.value).toBe(true);
            expect(saveButton(wrapper).attributes('disabled')).toBeUndefined();
        });
    });
});
