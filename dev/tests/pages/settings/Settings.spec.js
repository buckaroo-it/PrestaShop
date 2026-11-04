import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import { enableAutoUnmount } from '@vue/test-utils';
import Settings from '@/pages/Settings.vue';
import Loading from '@/components/Loading.vue';
import { mountApp, mountAndSettle, flushPromises } from '../../helpers';
import { respond, fail, callsTo } from '../../mocks/api';

vi.mock('@/lib/api', () => import('../../mocks/api'));

enableAutoUnmount(afterEach);

const { toastr } = vi.hoisted(() => ({ toastr: { success: vi.fn(), error: vi.fn() } }));
vi.mock('@/lib/toastr', () => ({ useToastr: () => ({ toastr }) }));

// What BuckarooSettingsService::getSettings() returns
const savedSettings = (overrides = {}) => ({
    is_enabled: 1,
    website_key: 'WEBSITEKEY',
    secret_key: 'SECRETKEY',
    transaction_description: 'Order {order_number}',
    refundconf: true,
    restock: false,
    creditSlip: false,
    voucher: false,
    negativePayment: false,
    payment_fee_mode: 'subtotal',
    payment_fee_frontend_label: 'Payment fee',
    error_reporting: false,
    error_reporting_decided: false,
    disable_unpaid_order_email: false,
    ...overrides,
});

const mountSettings = async (settings = savedSettings(), provide = {}) => {
    respond('buckaroo_config_settings', { status: true, settings }, 'get');
    // The controller answers a save with the freshly stored settings
    respond('buckaroo_config_settings', (method, payload) => ({
        status: true,
        settings: { ...settings, ...payload, error_reporting_decided: settings.error_reporting_decided || 'error_reporting' in payload },
    }), 'post');

    return mountAndSettle(Settings, { provide, global: { components: { Loading } } });
};

const saveButton = (wrapper) => wrapper.findAll('button').find((b) => b.text() === 'Save');
const discardButton = (wrapper) => wrapper.findAll('button').find((b) => b.text() === 'Discard');
const testButton = (wrapper) => wrapper.findAll('button').find((b) => b.text().includes('Test Connection'));
const feeModeButtons = (wrapper) => wrapper.findAll('button[aria-pressed]');
const unsavedStatus = (wrapper) => wrapper.find('[role="status"]');

beforeEach(() => {
    toastr.success.mockClear();
    toastr.error.mockClear();
});

afterEach(() => {
    vi.useRealTimers();
});

describe('Settings page – loading', () => {
    it('loads the settings once and fills the credential fields', async () => {
        const wrapper = await mountSettings();

        expect(callsTo('buckaroo_config_settings', 'get')).toHaveLength(1);
        expect(wrapper.get('#website_key').element.value).toBe('WEBSITEKEY');
        expect(wrapper.get('#secret_key').element.value).toBe('SECRETKEY');
        expect(wrapper.get('#transaction_description').element.value).toBe('Order {order_number}');
        expect(wrapper.get('#payment_fee_frontend_label').element.value).toBe('Payment fee');
        expect(wrapper.get('#refundconf').element.checked).toBe(true);
        expect(wrapper.get('#restock').element.checked).toBe(false);
    });

    it('renders no form when the settings could not be loaded', async () => {
        respond('buckaroo_config_settings', { status: false }, 'get');
        const wrapper = await mountAndSettle(Settings, { global: { components: { Loading } } });

        expect(wrapper.find('#website_key').exists()).toBe(false);
        expect(saveButton(wrapper)).toBeUndefined();
        expect(toastr.error).toHaveBeenCalledWith('Something went wrong.');
    });

    it('shows an error and no form when the settings request fails', async () => {
        fail('buckaroo_config_settings', new Error('Network down'), 'get');
        const wrapper = await mountAndSettle(Settings, { global: { components: { Loading } } });

        expect(wrapper.find('#website_key').exists()).toBe(false);
        expect(wrapper.findAllComponents(Loading)).toHaveLength(0);
        expect(toastr.error).toHaveBeenCalledWith('Something went wrong.');
    });

    it('shows a spinner while the settings are loading', async () => {
        respond('buckaroo_config_settings', { status: true, settings: savedSettings() }, 'get');
        const wrapper = mountApp(Settings, { global: { components: { Loading } } });

        expect(wrapper.findComponent(Loading).exists()).toBe(true);
        await flushPromises();
        expect(wrapper.findAllComponents(Loading)).toHaveLength(0);
    });

    it('shows the help panel with the five onboarding steps and support contacts', async () => {
        const wrapper = await mountSettings();

        expect(wrapper.find('aside').attributes('aria-label')).toBe('Welcome to Buckaroo');
        expect(wrapper.findAll('aside ol li')).toHaveLength(5);
        expect(wrapper.find('a[href="mailto:support@buckaroo.nl"]').exists()).toBe(true);
        expect(wrapper.find('a[href="tel:+31307115020"]').exists()).toBe(true);
    });
});

describe('Settings page – credentials', () => {
    it('masks both keys and reveals each one with its eye button', async () => {
        const wrapper = await mountSettings();
        const websiteKey = wrapper.get('#website_key');
        const secretKey = wrapper.get('#secret_key');

        expect(websiteKey.attributes('type')).toBe('password');
        expect(secretKey.attributes('type')).toBe('password');

        const eyeButtons = wrapper.findAll('.relative > button');
        await eyeButtons[0].trigger('click');
        expect(websiteKey.attributes('type')).toBe('text');
        expect(eyeButtons[0].find('.fa-eye-slash').exists()).toBe(true);
        expect(secretKey.attributes('type')).toBe('password');

        await eyeButtons[1].trigger('click');
        expect(secretKey.attributes('type')).toBe('text');

        await eyeButtons[0].trigger('click');
        expect(websiteKey.attributes('type')).toBe('password');
        expect(eyeButtons[0].find('.fa-eye').exists()).toBe(true);
    });

    it('disables Test Connection with a hint until both keys are filled in', async () => {
        const wrapper = await mountSettings(savedSettings({ secret_key: '' }));
        const hint = 'Enter your store key and secret key to test the connection.';

        expect(testButton(wrapper).attributes('disabled')).toBeDefined();
        expect(testButton(wrapper).attributes('title')).toBe(hint);
        expect(wrapper.text()).toContain(hint);

        await wrapper.get('#secret_key').setValue('NEWSECRET');

        expect(testButton(wrapper).attributes('disabled')).toBeUndefined();
        expect(testButton(wrapper).attributes('title')).toBeUndefined();
        expect(wrapper.text()).not.toContain(hint);
    });

    it('posts the typed keys to the test endpoint and confirms valid credentials for 3 seconds', async () => {
        vi.useFakeTimers();
        const wrapper = await mountSettings();
        respond('buckaroo_config_testCredentials', { status: true }, 'post');

        await wrapper.get('#website_key').setValue('TYPEDKEY');
        await testButton(wrapper).trigger('click');
        await flushPromises();

        expect(callsTo('buckaroo_config_testCredentials', 'post')[0].payload).toEqual({
            website_key: 'TYPEDKEY',
            secret_key: 'SECRETKEY',
        });
        expect(wrapper.text()).toContain('Successfully verified the credentials!');

        vi.advanceTimersByTime(3000);
        await flushPromises();
        expect(wrapper.text()).not.toContain('Successfully verified the credentials!');
    });

    it('tells the merchant when the credentials are not valid (and keeps telling them)', async () => {
        vi.useFakeTimers();
        const wrapper = await mountSettings();
        respond('buckaroo_config_testCredentials', { status: false }, 'post');

        await testButton(wrapper).trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('The credentials are not valid!');
        vi.advanceTimersByTime(5000);
        await flushPromises();
        expect(wrapper.text()).toContain('The credentials are not valid!');
    });

    it('logs and shows no verdict when the test request fails', async () => {
        const wrapper = await mountSettings();
        const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {});
        fail('buckaroo_config_testCredentials', new Error('Network down'), 'post');

        await testButton(wrapper).trigger('click');
        await flushPromises();

        expect(consoleError).toHaveBeenCalledWith('Error testing credentials:', expect.any(Error));
        expect(wrapper.text()).not.toContain('Successfully verified the credentials!');
        expect(wrapper.text()).not.toContain('The credentials are not valid!');
    });

    it('does not call the test endpoint when a key is missing', async () => {
        const wrapper = await mountSettings(savedSettings({ website_key: '' }));

        await wrapper.vm.testCredentials();

        expect(callsTo('buckaroo_config_testCredentials')).toHaveLength(0);
    });

    it('swaps the Test Connection button for a spinner while testing', async () => {
        const wrapper = await mountSettings();
        respond('buckaroo_config_testCredentials', { status: true }, 'post');

        const pending = wrapper.vm.testCredentials();
        await wrapper.vm.$nextTick();
        expect(testButton(wrapper)).toBeUndefined();
        expect(wrapper.findComponent(Loading).exists()).toBe(true);

        await pending;
        await flushPromises();
        expect(testButton(wrapper)).toBeDefined();
    });
});

describe('Settings page – advanced settings', () => {
    it('hides the advanced settings behind an expandable toggle', async () => {
        const wrapper = await mountSettings();
        const toggle = wrapper.get('button[aria-expanded]');
        const panel = wrapper.get('#transaction_description').element.closest('.divide-y.space-y-3');

        expect(toggle.attributes('aria-expanded')).toBe('false');
        expect(toggle.text()).toBe('Advanced Settings');
        expect(toggle.find('.fa-chevron-down').exists()).toBe(true);
        expect(panel.style.display).toBe('none');

        await toggle.trigger('click');

        expect(toggle.attributes('aria-expanded')).toBe('true');
        expect(toggle.text()).toBe('Hide Advanced Settings');
        expect(toggle.find('.fa-chevron-up').exists()).toBe(true);
        expect(panel.style.display).toBe('');
    });

    it('shows a live example of the transaction description', async () => {
        const wrapper = await mountSettings(savedSettings({ transaction_description: '{shop_name} – {order_number}' }));

        expect(wrapper.text()).toContain('Example Your Shop Name – 123456789');

        await wrapper.get('#transaction_description').setValue('Order {order_number}');
        expect(wrapper.text()).toContain('Example Order 123456789');
    });

    it('shows no example when the description is empty', async () => {
        const wrapper = await mountSettings(savedSettings({ transaction_description: '' }));

        expect(wrapper.vm.displayExampleTransactionDescription).toBeUndefined();
    });

    it('appends placeholders with the Order number and Shop name buttons', async () => {
        const wrapper = await mountSettings(savedSettings({ transaction_description: 'Order ' }));
        const button = (label) => wrapper.findAll('button').find((b) => b.text() === label);

        await button('Order number').trigger('click');
        await button('Shop name').trigger('click');

        expect(wrapper.get('#transaction_description').element.value).toBe('Order {order_number}{shop_name}');
    });

    it('starts a fresh description when none was stored', async () => {
        const wrapper = await mountSettings(savedSettings({ transaction_description: null }));
        const button = (label) => wrapper.findAll('button').find((b) => b.text() === label);

        await button('Shop name').trigger('click');
        expect(wrapper.get('#transaction_description').element.value).toBe('{shop_name}');

        wrapper.vm.settings.transaction_description = null;
        await button('Order number').trigger('click');
        expect(wrapper.get('#transaction_description').element.value).toBe('{order_number}');
    });

    it('switches the payment fee mode with an aria-pressed segmented control', async () => {
        const wrapper = await mountSettings();
        const [subtotal, inclTax] = feeModeButtons(wrapper);

        expect(subtotal.text()).toBe('Subtotal');
        expect(inclTax.text()).toBe('Subtotal incl. tax');
        expect(subtotal.attributes('aria-pressed')).toBe('true');
        expect(subtotal.find('.fa-check').exists()).toBe(true);
        expect(inclTax.attributes('aria-pressed')).toBe('false');
        expect(inclTax.classes()).toContain('text-gray-500');

        await inclTax.trigger('click');

        expect(inclTax.attributes('aria-pressed')).toBe('true');
        expect(inclTax.classes()).toEqual(expect.arrayContaining(['bg-primary', 'text-fifthly']));
        expect(subtotal.attributes('aria-pressed')).toBe('false');
        expect(subtotal.find('.fa-check').exists()).toBe(false);
        expect(wrapper.vm.settings.payment_fee_mode).toBe('subtotal_incl_tax');
    });

    it('uses type="button" on the fee mode buttons so they never submit a form', async () => {
        const wrapper = await mountSettings();

        feeModeButtons(wrapper).forEach((b) => expect(b.attributes('type')).toBe('button'));
    });
});

describe('Settings page – unsaved changes', () => {
    it('starts clean: Save disabled, no banner, no Discard', async () => {
        const hasUnsavedChanges = ref(false);
        const wrapper = await mountSettings(savedSettings(), { hasUnsavedChanges });

        expect(saveButton(wrapper).attributes('disabled')).toBeDefined();
        expect(unsavedStatus(wrapper).exists()).toBe(false);
        expect(discardButton(wrapper)).toBeUndefined();
        expect(hasUnsavedChanges.value).toBe(false);
    });

    it('flags an edit, enables Save and reports it to the dashboard', async () => {
        const hasUnsavedChanges = ref(false);
        const wrapper = await mountSettings(savedSettings(), { hasUnsavedChanges });

        await wrapper.get('#restock').setValue(true);

        expect(unsavedStatus(wrapper).text()).toBe('You have unsaved changes');
        expect(saveButton(wrapper).attributes('disabled')).toBeUndefined();
        expect(hasUnsavedChanges.value).toBe(true);
    });

    it('is clean again when an edit is undone by hand', async () => {
        const wrapper = await mountSettings();

        await wrapper.get('#payment_fee_frontend_label').setValue('Fee');
        expect(unsavedStatus(wrapper).exists()).toBe(true);

        await wrapper.get('#payment_fee_frontend_label').setValue('Payment fee');
        expect(unsavedStatus(wrapper).exists()).toBe(false);
    });

    it('Discard restores the last saved values', async () => {
        const hasUnsavedChanges = ref(false);
        const wrapper = await mountSettings(savedSettings(), { hasUnsavedChanges });

        await wrapper.get('#website_key').setValue('OTHER');
        await feeModeButtons(wrapper)[1].trigger('click');
        await wrapper.get('#voucher').setValue(true);

        await discardButton(wrapper).trigger('click');
        await flushPromises();

        expect(wrapper.get('#website_key').element.value).toBe('WEBSITEKEY');
        expect(feeModeButtons(wrapper)[0].attributes('aria-pressed')).toBe('true');
        expect(wrapper.get('#voucher').element.checked).toBe(false);
        expect(unsavedStatus(wrapper).exists()).toBe(false);
        expect(hasUnsavedChanges.value).toBe(false);
    });

    it('asks the browser to warn before leaving only while there are unsaved changes', async () => {
        const wrapper = await mountSettings();
        const leave = () => {
            const event = new Event('beforeunload', { cancelable: true });
            window.dispatchEvent(event);
            return event;
        };

        expect(leave().defaultPrevented).toBe(false);

        await wrapper.get('#website_key').setValue('OTHER');
        expect(leave().defaultPrevented).toBe(true);

        wrapper.unmount();
    });

    it('stops warning and clears the dashboard flag when the page is left', async () => {
        const hasUnsavedChanges = ref(false);
        const wrapper = await mountSettings(savedSettings(), { hasUnsavedChanges });
        await wrapper.get('#website_key').setValue('OTHER');
        expect(hasUnsavedChanges.value).toBe(true);

        wrapper.unmount();

        expect(hasUnsavedChanges.value).toBe(false);
        const event = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(event);
        expect(event.defaultPrevented).toBe(false);
    });
});

describe('Settings page – saving', () => {
    it('posts every setting, leaving out the undecided error-reporting consent', async () => {
        const wrapper = await mountSettings();

        await wrapper.get('#negativePayment').setValue(true);
        await feeModeButtons(wrapper)[1].trigger('click');
        await saveButton(wrapper).trigger('click');
        await flushPromises();

        const { error_reporting, error_reporting_decided, ...expected } = savedSettings({
            negativePayment: true,
            payment_fee_mode: 'subtotal_incl_tax',
        });
        expect(callsTo('buckaroo_config_settings', 'post')).toHaveLength(1);
        expect(callsTo('buckaroo_config_settings', 'post')[0].payload).toEqual(expected);
    });

    it('sends the error-reporting consent once the merchant flips the toggle', async () => {
        const wrapper = await mountSettings();

        await wrapper.get('#error_reporting').setValue(true);
        await saveButton(wrapper).trigger('click');
        await flushPromises();

        const payload = callsTo('buckaroo_config_settings', 'post')[0].payload;
        expect(payload.error_reporting).toBe(true);
        expect(payload).not.toHaveProperty('error_reporting_decided');
    });

    it('always sends the consent once it was decided before', async () => {
        const wrapper = await mountSettings(savedSettings({ error_reporting: true, error_reporting_decided: true }));

        await wrapper.get('#website_key').setValue('NEW');
        await saveButton(wrapper).trigger('click');
        await flushPromises();

        const payload = callsTo('buckaroo_config_settings', 'post')[0].payload;
        expect(payload.error_reporting).toBe(true);
        expect(payload).not.toHaveProperty('error_reporting_decided');
    });

    it('confirms the save, adopts the stored settings and clears the unsaved state', async () => {
        const hasUnsavedChanges = ref(false);
        const wrapper = await mountSettings(savedSettings(), { hasUnsavedChanges });

        await wrapper.get('#website_key').setValue('NEWKEY');
        await saveButton(wrapper).trigger('click');
        await flushPromises();

        expect(toastr.success).toHaveBeenCalledWith('Settings successfully updated.');
        expect(wrapper.get('#website_key').element.value).toBe('NEWKEY');
        expect(unsavedStatus(wrapper).exists()).toBe(false);
        expect(saveButton(wrapper).attributes('disabled')).toBeDefined();
        expect(hasUnsavedChanges.value).toBe(false);
    });

    it('resets the consent "touched" flag after a successful save', async () => {
        const wrapper = await mountSettings();

        await wrapper.get('#error_reporting').setValue(true);
        expect(wrapper.vm.errorReportingTouched).toBe(true);
        await saveButton(wrapper).trigger('click');
        await flushPromises();

        expect(wrapper.vm.errorReportingTouched).toBe(false);
        expect(wrapper.get('#error_reporting').element.checked).toBe(true);
    });

    it('does not record a consent decision when a consent change was discarded', async () => {
        // Reverting settings.error_reporting makes ToggleField re-emit update:modelValue; that
        // programmatic update must not count as the merchant's decision.
        const wrapper = await mountSettings();

        await wrapper.get('#error_reporting').setValue(true);
        await discardButton(wrapper).trigger('click');
        await flushPromises();
        expect(wrapper.vm.errorReportingTouched).toBe(false);
        await wrapper.get('#website_key').setValue('NEW');
        await saveButton(wrapper).trigger('click');
        await flushPromises();

        expect(callsTo('buckaroo_config_settings', 'post')[0].payload).not.toHaveProperty('error_reporting');
    });

    it('does not record a consent decision when reloaded settings change the toggle', async () => {
        const wrapper = await mountSettings();
        respond('buckaroo_config_settings', { status: true, settings: savedSettings({ error_reporting: true }) }, 'get');

        wrapper.vm.getSettings();
        await flushPromises();
        expect(wrapper.get('#error_reporting').element.checked).toBe(true);
        expect(wrapper.vm.errorReportingTouched).toBe(false);

        await wrapper.get('#website_key').setValue('NEW');
        await saveButton(wrapper).trigger('click');
        await flushPromises();

        expect(callsTo('buckaroo_config_settings', 'post')[0].payload).not.toHaveProperty('error_reporting');
    });

    it('still records the decision when the merchant flips the toggle after a discard', async () => {
        const wrapper = await mountSettings();

        await wrapper.get('#error_reporting').setValue(true);
        await discardButton(wrapper).trigger('click');
        await flushPromises();
        await wrapper.get('#error_reporting').setValue(true);
        await saveButton(wrapper).trigger('click');
        await flushPromises();

        expect(callsTo('buckaroo_config_settings', 'post')[0].payload.error_reporting).toBe(true);
    });

    it('keeps the changes and shows no success toast when the server rejects them', async () => {
        const wrapper = await mountSettings();
        respond('buckaroo_config_settings', { status: false }, 'post');

        await wrapper.get('#website_key').setValue('NEWKEY');
        await saveButton(wrapper).trigger('click');
        await flushPromises();

        expect(toastr.success).not.toHaveBeenCalled();
        expect(toastr.error).toHaveBeenCalledWith('Something went wrong.');
        expect(unsavedStatus(wrapper).exists()).toBe(true);
        expect(wrapper.get('#website_key').element.value).toBe('NEWKEY');
    });

    it('shows an error and keeps the changes when the save request fails', async () => {
        const wrapper = await mountSettings();
        fail('buckaroo_config_settings', new Error('Network down'), 'post');

        await wrapper.get('#website_key').setValue('NEWKEY');
        await saveButton(wrapper).trigger('click');
        await flushPromises();

        expect(toastr.error).toHaveBeenCalledWith('Something went wrong.');
        expect(toastr.success).not.toHaveBeenCalled();
        expect(unsavedStatus(wrapper).exists()).toBe(true);
        expect(wrapper.get('#website_key').element.value).toBe('NEWKEY');
    });
});
