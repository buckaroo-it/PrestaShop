import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { enableAutoUnmount } from '@vue/test-utils';
import { ref } from 'vue';
import PaymentMethods from '@/pages/PaymentMethods.vue';
import { flushPromises, mountAndSettle, mountApp } from '../../helpers';
import { callsTo, fail, respond } from '../../mocks/api';

vi.mock('@/lib/api', () => import('../../mocks/api'));

const toastr = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn() }));
vi.mock('@/lib/toastr', () => ({ useToastr: () => ({ toastr }) }));

enableAutoUnmount(afterEach);

const PAYMENTS = [
    { name: 'ideal', icon: 'iDEAL.svg', mode: 'live' },
    { name: 'paypal', icon: 'PayPal.svg', mode: 'test' },
    { name: 'afterpay', icon: 'AfterPay.svg', mode: 'live' },
    { name: 'klarna', icon: 'Klarna.svg', mode: 'off' },
    { name: 'giftcard', icon: 'Giftcards.svg' }, // never configured: no mode
];

// The settings page and the per-method settings belong to their own specs.
const DefaultPaymentConfigStub = {
    name: 'DefaultPaymentConfig',
    props: ['payment'],
    template: '<div class="default-config" :data-method="payment.name"><slot /></div>',
};
const CHILD_CONFIGS = {
    afterpay: 'AfterpayPaymentConfig',
    billink: 'BillinkPaymentConfig',
    creditcard: 'CreditCardPaymentConfig',
    in3: 'In3PaymentConfig',
    paypal: 'PayPalPaymentConfig',
    paybybank: 'PayByBankPaymentConfig',
    payperemail: 'PayPerEmailPaymentConfig',
    transfer: 'TransferPaymentConfig',
    giftcard: 'GiftcardPaymentConfig',
    klarna: 'KlarnaPaymentConfig',
    googlepay: 'GooglePayPaymentConfig',
    applepay: 'ApplePayPaymentConfig',
    clicktopay: 'ClickToPayPaymentConfig',
};
const stubs = {
    DefaultPaymentConfig: DefaultPaymentConfigStub,
    ...Object.fromEntries(Object.values(CHILD_CONFIGS).map((name) => [name, { name, props: ['payments'], template: `<div data-child="${name}" />` }])),
};

const respondPayments = (payments = PAYMENTS) =>
    respond('buckaroo_config_methods', { status: true, payments }, 'get');

const mountPage = (provide = {}) => mountAndSettle(PaymentMethods, { provide, global: { stubs } });

const tileNames = (wrapper) => wrapper.findAll('.grid > div > button:first-child').map((b) => b.text().replace(/\s+/g, ' ').trim());
const filterButtons = (wrapper) => wrapper.findAll('[role="group"]:not([aria-label]) button');
const filterButton = (wrapper, label) => filterButtons(wrapper).find((b) => b.text().startsWith(label));
const filterSummary = (wrapper) => filterButtons(wrapper).map((b) => b.text().replace(/\s+/g, ' ').trim());
const openTile = (wrapper, name) => wrapper.findAll('.grid > div').find((tile) => tile.text().includes(name)).get('button').trigger('click');
const backButton = (wrapper) => wrapper.get('button[aria-label="Back"]');

describe('PaymentMethods page', () => {
    beforeEach(() => {
        toastr.success.mockClear();
        toastr.error.mockClear();
    });

    describe('list', () => {
        it('loads the payment methods and shows a tile per method', async () => {
            respondPayments();
            const wrapper = await mountPage();

            expect(callsTo('buckaroo_config_methods', 'get')).toHaveLength(1);
            expect(wrapper.get('h1').text()).toBe('Payment methods');
            expect(tileNames(wrapper)).toEqual(['iDEAL | Wero', 'PayPal', 'Riverty', 'Klarna', 'Giftcard']);
        });

        it('summarises how many methods are active', async () => {
            respondPayments();
            const wrapper = await mountPage();

            expect(wrapper.text()).toContain('3 of 5 active');
        });

        it('shows a spinner while loading', async () => {
            respondPayments();
            const wrapper = mountApp(PaymentMethods, { global: { stubs } });

            expect(wrapper.find('svg.animate-spin').exists()).toBe(true);
            await flushPromises();
            expect(wrapper.find('svg.animate-spin').exists()).toBe(false);
        });

        it('shows the generic subtitle and no search or filters without methods', async () => {
            respond('buckaroo_config_methods', { status: false }, 'get');
            const wrapper = await mountPage();

            expect(wrapper.text()).toContain('Configure your payment method settings');
            expect(wrapper.find('input[type="search"]').exists()).toBe(false);
            expect(filterButtons(wrapper)).toHaveLength(0);
            expect(tileNames(wrapper)).toEqual([]);
            expect(wrapper.text()).not.toContain('No payment methods match');
        });

        it('shows an error and stops loading when the request fails', async () => {
            fail('buckaroo_config_methods', new Error('Network Error'), 'get');
            const initialRouteDetail = ref('klarna');
            const wrapper = await mountPage({ initialRouteDetail });

            expect(toastr.error).toHaveBeenCalledWith('Something went wrong.');
            expect(wrapper.find('svg.animate-spin').exists()).toBe(false);
            expect(wrapper.text()).toContain('Configure your payment method settings');
            expect(tileNames(wrapper)).toEqual([]);
            expect(initialRouteDetail.value).toBe('klarna');
        });
    });

    describe('filters and search', () => {
        it('counts the methods per mode, treating a missing mode as off', async () => {
            respondPayments();
            const wrapper = await mountPage();

            expect(filterSummary(wrapper)).toEqual(['All 5', 'Live 2', 'Test 1', 'Off 2']);
            expect(filterButtons(wrapper).map((b) => b.attributes('aria-pressed'))).toEqual(['true', 'false', 'false', 'false']);
        });

        it.each([
            ['Live', ['iDEAL | Wero', 'Riverty']],
            ['Test', ['PayPal']],
            ['Off', ['Klarna', 'Giftcard']],
        ])('shows only %s methods', async (label, expected) => {
            respondPayments();
            const wrapper = await mountPage();

            await filterButton(wrapper, label).trigger('click');

            expect(tileNames(wrapper)).toEqual(expected);
            expect(filterButton(wrapper, label).attributes('aria-pressed')).toBe('true');
            expect(filterButton(wrapper, label).classes()).toContain('bg-white');
            expect(filterButton(wrapper, 'All').attributes('aria-pressed')).toBe('false');
        });

        it('goes back to all methods', async () => {
            respondPayments();
            const wrapper = await mountPage();
            await filterButton(wrapper, 'Test').trigger('click');

            await filterButton(wrapper, 'All').trigger('click');

            expect(tileNames(wrapper)).toHaveLength(5);
        });

        it('searches the display name, case-insensitively and trimmed', async () => {
            respondPayments();
            const wrapper = await mountPage();

            await wrapper.get('input[type="search"]').setValue('  WERO ');

            expect(tileNames(wrapper)).toEqual(['iDEAL | Wero']);
        });

        it('searches the technical name too', async () => {
            respondPayments();
            const wrapper = await mountPage();

            await wrapper.get('input[type="search"]').setValue('afterpay');

            expect(tileNames(wrapper)).toEqual(['Riverty']);
        });

        it('updates the counts for the search but not the active summary', async () => {
            respondPayments();
            const wrapper = await mountPage();

            await wrapper.get('input[type="search"]').setValue('pay'); // PayPal, Riverty (afterpay)

            expect(filterSummary(wrapper)).toEqual(['All 2', 'Live 1', 'Test 1', 'Off 0']);
            expect(wrapper.text()).toContain('3 of 5 active');
        });

        it('combines search and filter, with a message when nothing matches', async () => {
            respondPayments();
            const wrapper = await mountPage();
            await filterButton(wrapper, 'Off').trigger('click');

            await wrapper.get('input[type="search"]').setValue('pal');

            expect(tileNames(wrapper)).toEqual([]);
            expect(wrapper.text()).toContain('No payment methods match your search.');
        });

        it('recounts after a tile changes its mode', async () => {
            respondPayments();
            respond('buckaroo_config_methodMode', { status: true }, 'post');
            const wrapper = await mountPage();

            const klarna = wrapper.findAll('.grid > div').find((tile) => tile.text().includes('Klarna'));
            await klarna.findAll('[role="group"] button')[0].trigger('click');
            await flushPromises();

            expect(filterSummary(wrapper)).toEqual(['All 5', 'Live 3', 'Test 1', 'Off 1']);
            expect(wrapper.text()).toContain('4 of 5 active');
            expect(callsTo('buckaroo_config_methodMode', 'post')[0].payload).toEqual({ name: 'klarna', icon: 'Klarna.svg', mode: 'live' });
        });
    });

    describe('method settings', () => {
        it('opens the settings of a clicked method', async () => {
            respondPayments();
            const wrapper = await mountPage();

            await openTile(wrapper, 'PayPal');

            expect(tileNames(wrapper)).toEqual([]);
            expect(wrapper.find('input[type="search"]').exists()).toBe(false);
            expect(wrapper.get('h1').text()).toBe('PayPal');
            expect(wrapper.text()).toContain('Configure your PayPal settings');
            expect(wrapper.get('img').attributes('src'))
                .toBe('https://shop.test/modules/buckaroo3/views/img/buckaroo/Payment methods/SVG/PayPal.svg');
            expect(wrapper.get('.default-config').attributes('data-method')).toBe('paypal');
            expect(wrapper.findAll('[data-child]').map((c) => c.attributes('data-child'))).toEqual(['PayPalPaymentConfig']);
        });

        it('keeps the open method in the URL hash', async () => {
            respondPayments();
            const wrapper = await mountPage();

            await openTile(wrapper, 'Klarna');
            expect(window.location.hash).toBe('#payment_methods/klarna');

            await backButton(wrapper).trigger('click');
            expect(window.location.hash).toBe('#payment_methods');
        });

        it('shows the iDEAL brand name and no icon when the method has none', async () => {
            respondPayments([{ name: 'ideal', mode: 'live' }]);
            const wrapper = await mountPage();

            await openTile(wrapper, 'iDEAL');

            expect(wrapper.get('h1').text()).toBe('iDEAL | Wero');
            expect(wrapper.text()).toContain('Configure your iDEAL | Wero settings');
            expect(wrapper.find('img').exists()).toBe(false);
            expect(wrapper.findAll('[data-child]')).toHaveLength(0);
        });

        it.each(Object.entries(CHILD_CONFIGS))('renders the %s specific settings', async (name, component) => {
            respondPayments([{ name, icon: 'x.svg', mode: 'test' }]);
            const wrapper = await mountPage({ initialRouteDetail: ref(name) });

            expect(wrapper.findAll('[data-child]').map((c) => c.attributes('data-child'))).toEqual([component]);
        });

        it.each(['payperemail', 'giftcard'])('passes the method list to the %s settings', async (name) => {
            const payments = [{ name, icon: 'x.svg', mode: 'test' }, { name: 'ideal', icon: 'i.svg', mode: 'live' }];
            respondPayments(payments);
            const wrapper = await mountPage({ initialRouteDetail: ref(name) });

            expect(wrapper.findComponent({ name: CHILD_CONFIGS[name] }).props('payments')).toEqual(payments);
        });

        it('goes back to the list without unsaved changes', async () => {
            respondPayments();
            const confirm = vi.spyOn(window, 'confirm');
            const wrapper = await mountPage();
            await openTile(wrapper, 'PayPal');

            await backButton(wrapper).trigger('click');

            expect(confirm).not.toHaveBeenCalled();
            expect(tileNames(wrapper)).toHaveLength(5);
            expect(wrapper.get('h1').text()).toBe('Payment methods');
        });

        it('stays on the settings when leaving unsaved changes is cancelled', async () => {
            respondPayments();
            const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
            const hasUnsavedChanges = ref(false);
            const wrapper = await mountPage({ hasUnsavedChanges });
            await openTile(wrapper, 'PayPal');
            hasUnsavedChanges.value = true;

            await backButton(wrapper).trigger('click');

            expect(confirm).toHaveBeenCalledWith('You have unsaved changes. Leave without saving?');
            expect(wrapper.get('h1').text()).toBe('PayPal');
            expect(hasUnsavedChanges.value).toBe(true);
        });

        it('leaves and clears the unsaved flag when confirmed', async () => {
            respondPayments();
            vi.spyOn(window, 'confirm').mockReturnValue(true);
            const hasUnsavedChanges = ref(false);
            const wrapper = await mountPage({ hasUnsavedChanges });
            await openTile(wrapper, 'PayPal');
            hasUnsavedChanges.value = true;

            await backButton(wrapper).trigger('click');

            expect(wrapper.get('h1').text()).toBe('Payment methods');
            expect(hasUnsavedChanges.value).toBe(false);
        });
    });

    describe('opening from the URL', () => {
        it('opens the method named in the route and consumes it', async () => {
            respondPayments();
            const initialRouteDetail = ref('klarna');
            const wrapper = await mountPage({ initialRouteDetail });

            expect(wrapper.get('h1').text()).toBe('Klarna');
            expect(initialRouteDetail.value).toBeNull();
            expect(window.location.hash).toBe('#payment_methods/klarna');
        });

        it('shows the list for an unknown method and consumes it', async () => {
            respondPayments();
            const initialRouteDetail = ref('doesnotexist');
            const wrapper = await mountPage({ initialRouteDetail });

            expect(wrapper.get('h1').text()).toBe('Payment methods');
            expect(tileNames(wrapper)).toHaveLength(5);
            expect(initialRouteDetail.value).toBeNull();
        });

        it('keeps the route detail when the methods fail to load', async () => {
            respond('buckaroo_config_methods', { status: false }, 'get');
            const initialRouteDetail = ref('klarna');
            await mountPage({ initialRouteDetail });

            expect(initialRouteDetail.value).toBe('klarna');
        });
    });
});
