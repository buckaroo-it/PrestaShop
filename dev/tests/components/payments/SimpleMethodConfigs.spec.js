import { describe, expect, it, vi } from 'vitest';
import AfterpayPaymentConfig from '@/components/payments/AfterpayPaymentConfig.vue';
import BillinkPaymentConfig from '@/components/payments/BillinkPaymentConfig.vue';
import ClickToPayPaymentConfig from '@/components/payments/ClickToPayPaymentConfig.vue';
import GooglePayPaymentConfig from '@/components/payments/GooglePayPaymentConfig.vue';
import PayPalPaymentConfig from '@/components/payments/PayPalPaymentConfig.vue';
import TransferPaymentConfig from '@/components/payments/TransferPaymentConfig.vue';
import BancontactPaymentConfig from '@/components/payments/BancontactPaymentConfig.vue';
import PayByBankPaymentConfig from '@/components/payments/PayByBankPaymentConfig.vue';
import IdealPaymentConfig from '@/components/payments/IdealPaymentConfig.vue';
import In3PaymentConfig from '@/components/payments/In3PaymentConfig.vue';
import KlarnaPaymentConfig from '@/components/payments/KlarnaPaymentConfig.vue';
import { mountConfig } from './mountConfig';

vi.mock('@/lib/api', () => import('../../mocks/api'));

// ---------------------------------------------------------------------------
// Customer type selects (Afterpay / Billink): B2C, B2B or both
// ---------------------------------------------------------------------------
describe.each([
    ['AfterpayPaymentConfig', AfterpayPaymentConfig, '#afterpay_customer_type'],
    ['BillinkPaymentConfig', BillinkPaymentConfig, '#billink_customer_type'],
])('%s', (_name, component, selectId) => {
    it('offers both / B2C / B2B with their long English labels', async () => {
        const { wrapper } = await mountConfig(component, {});
        const options = wrapper.findAll(`${selectId} option`);

        expect(options.map((o) => o.attributes('value'))).toEqual(['both', 'B2C', 'B2B']);
        expect(options.map((o) => o.text())).toEqual([
            'Both',
            'B2C (Business-to-consumer)',
            'B2B (Business-to-Business)',
        ]);
        expect(wrapper.get(`label[for="${selectId.slice(1)}"]`).text()).toBe('Customer type');
    });

    it('shows the stored customer type as selected', async () => {
        const { wrapper } = await mountConfig(component, { customer_type: 'B2B' });

        expect(wrapper.get(selectId).element.value).toBe('B2B');
    });

    it.each(['B2C', 'B2B', 'both'])('writes %s into config.customer_type', async (value) => {
        const { wrapper, config } = await mountConfig(component, { customer_type: 'both', mode: 'live' });

        await wrapper.get(selectId).setValue(value);

        expect(config.value).toEqual({ customer_type: value, mode: 'live' });
    });
});

// ---------------------------------------------------------------------------
// Plain text / number / select inputs bound to one config key each
// ---------------------------------------------------------------------------
const fieldCases = [
    // component, selector, config key, initial value, new value, expected label text
    ['ClickToPayPaymentConfig', ClickToPayPaymentConfig, '#clicktopay_client_id', 'client_id', 'id-1', 'id-2', 'Client ID'],
    ['ClickToPayPaymentConfig', ClickToPayPaymentConfig, '#clicktopay_client_secret', 'client_secret', 's-1', 's-2', 'Client Secret'],
    ['ClickToPayPaymentConfig', ClickToPayPaymentConfig, '#clicktopay_merchant_identifier', 'merchant_identifier', 'm-1', 'm-2', null],
    ['GooglePayPaymentConfig', GooglePayPaymentConfig, '#googlepay_merchant_name', 'merchantName', 'Shop', 'My shop', 'Merchant name'],
    ['GooglePayPaymentConfig', GooglePayPaymentConfig, '#googlepay_merchant_id', 'merchantId', 'BCR1', 'BCR2', null],
    ['GooglePayPaymentConfig', GooglePayPaymentConfig, '#googlepay_gateway_merchant_id', 'gatewayMerchantId', 'g1', 'g2', null],
    ['PayPalPaymentConfig', PayPalPaymentConfig, 'select', 'seller_protection', '0', '1', null],
    ['TransferPaymentConfig', TransferPaymentConfig, 'select', 'send_instruction_email', '1', '0', null],
    ['TransferPaymentConfig', TransferPaymentConfig, 'input[type="number"]', 'due_days', '7', '14', null],
];

describe.each(fieldCases.map(([name, component, selector, key, initial, next, label]) => ({ name, component, selector, key, initial, next, label })))('$name: $key', ({ component, selector, key, initial, next, label }) => {
    it(`shows the stored ${key}`, async () => {
        const { wrapper } = await mountConfig(component, { [key]: initial });

        expect(wrapper.get(selector).element.value).toBe(initial);
        if (label) {
            expect(wrapper.get(`label[for="${selector.slice(1)}"]`).text()).toBe(label);
        }
    });

    it(`writes ${key} into the config and leaves other keys alone`, async () => {
        const { wrapper, config } = await mountConfig(component, { [key]: initial, mode: 'test' });

        await wrapper.get(selector).setValue(next);

        expect(String(config.value[key])).toBe(next);
        expect(config.value.mode).toBe('test');
    });
});

describe('TransferPaymentConfig due days', () => {
    it('stores due_days as a number (type="number" inputs are cast by v-model)', async () => {
        const { wrapper, config } = await mountConfig(TransferPaymentConfig, {});

        await wrapper.get('input[type="number"]').setValue('21');

        expect(config.value.due_days).toBe(21);
    });
});

describe('ClickToPayPaymentConfig', () => {
    it('masks the client secret and keeps the browser from autofilling it', async () => {
        const { wrapper } = await mountConfig(ClickToPayPaymentConfig, {});
        const secret = wrapper.get('#clicktopay_client_secret');

        expect(secret.attributes('type')).toBe('password');
        expect(secret.attributes('autocomplete')).toBe('new-password');
        expect(wrapper.text()).toContain('Click to Pay credentials');
    });
});

describe('GooglePayPaymentConfig', () => {
    it('renders the three merchant fields in order', async () => {
        const { wrapper } = await mountConfig(GooglePayPaymentConfig, {});

        expect(wrapper.findAll('input').map((i) => i.attributes('id'))).toEqual([
            'googlepay_merchant_name', 'googlepay_merchant_id', 'googlepay_gateway_merchant_id',
        ]);
    });
});

describe.each([
    ['PayPalPaymentConfig', PayPalPaymentConfig, 'Seller protection'],
    ['TransferPaymentConfig', TransferPaymentConfig, 'Send instruction email'],
])('%s yes/no select', (_name, component) => {
    it('offers "1" (Yes) and "0" (No)', async () => {
        const { wrapper } = await mountConfig(component, {});
        const options = wrapper.findAll('select option');

        expect(options.map((o) => o.attributes('value'))).toEqual(['1', '0']);
        expect(options.map((o) => o.text())).toEqual(['Yes', 'No']);
    });
});

// ---------------------------------------------------------------------------
// Child field wiring
// ---------------------------------------------------------------------------
describe('BancontactPaymentConfig', () => {
    it('renders the client-side encryption toggle reflecting config.isEncrypted', async () => {
        const { wrapper } = await mountConfig(BancontactPaymentConfig, { isEncrypted: true });

        expect(wrapper.text()).toContain('Client Side Encryption');
        expect(wrapper.get('input[type="checkbox"]').element.checked).toBe(true);
    });

    it('writes the toggle state into config.isEncrypted', async () => {
        const { wrapper, config } = await mountConfig(BancontactPaymentConfig, { isEncrypted: false });

        await wrapper.get('input[type="checkbox"]').setValue(true);
        expect(config.value.isEncrypted).toBe(true);

        await wrapper.get('input[type="checkbox"]').setValue(false);
        expect(config.value.isEncrypted).toBe(false);
    });
});

describe('PayByBankPaymentConfig', () => {
    it('shows a radio/dropdown segmented control with the stored display type pressed', async () => {
        const { wrapper } = await mountConfig(PayByBankPaymentConfig, { display_type: 'dropdown' });
        const buttons = wrapper.findAll('[role="group"] button');

        expect(wrapper.get('[role="group"]').attributes('aria-label')).toBe('Button Display Type');
        expect(buttons.map((b) => b.text())).toEqual(['Radio', 'Dropdown']);
        expect(buttons[1].attributes('aria-pressed')).toBe('true');
    });

    it('writes the clicked option into config.display_type', async () => {
        const { wrapper, config } = await mountConfig(PayByBankPaymentConfig, { display_type: 'dropdown' });

        await wrapper.findAll('[role="group"] button')[0].trigger('click');

        expect(config.value.display_type).toBe('radio');
    });
});

// ---------------------------------------------------------------------------
// Components without settings of their own
// ---------------------------------------------------------------------------
describe.each([
    ['In3PaymentConfig', In3PaymentConfig],
    ['KlarnaPaymentConfig', KlarnaPaymentConfig],
])('%s', (_name, component) => {
    it('renders an empty container and does not touch the config', async () => {
        const { wrapper, config } = await mountConfig(component, { mode: 'live' });

        expect(wrapper.text()).toBe('');
        expect(wrapper.find('input, select').exists()).toBe(false);
        expect(config.value).toEqual({ mode: 'live' });
    });
});

describe('IdealPaymentConfig', () => {
    it('renders nothing', async () => {
        const { wrapper } = await mountConfig(IdealPaymentConfig, {});

        expect(wrapper.text()).toBe('');
    });

    it.each([
        [{}, true],
        [{ show_issuers: true }, true],
        [{ show_issuers: false }, false],
        [{ show_issuers: 0 }, true], // only a strict false hides the issuers
    ])('showIssuers reads %j as %s', async (initial, expected) => {
        const { wrapper } = await mountConfig(IdealPaymentConfig, initial);

        expect(wrapper.vm.showIssuers).toBe(expected);
    });

    it('showIssuers writes a boolean into config.show_issuers', async () => {
        const { wrapper, config } = await mountConfig(IdealPaymentConfig, {});

        wrapper.vm.showIssuers = 0;
        expect(config.value.show_issuers).toBe(false);

        wrapper.vm.showIssuers = 'yes';
        expect(config.value.show_issuers).toBe(true);
    });
});
