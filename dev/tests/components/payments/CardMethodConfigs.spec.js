import { describe, expect, it, vi } from 'vitest';
import CreditCardPaymentConfig from '@/components/payments/CreditCardPaymentConfig.vue';
import GiftcardPaymentConfig from '@/components/payments/GiftcardPaymentConfig.vue';
import { mountConfig, modelStub } from './mountConfig';

vi.mock('@/lib/api', () => import('../../mocks/api'));

// The card pickers load catalogues from the API and have their own specs; here we only check the wiring.
const stubs = {
    ActiveCreditcards: modelStub('ActiveCreditcards'),
    ActiveGiftcards: modelStub('ActiveGiftcards'),
    AllowedRedirectPayments: modelStub('AllowedRedirectPayments', ['payments']),
};
const mountWith = (component, initial, options = {}) => mountConfig(component, initial, { global: { stubs }, ...options });

describe.each([
    ['CreditCardPaymentConfig', CreditCardPaymentConfig, ['Grouped', 'Separate']],
    ['GiftcardPaymentConfig', GiftcardPaymentConfig, ['Grouped', 'Inline']],
])('%s display in checkout', (_name, component, labels) => {
    it('offers grouped / separate', async () => {
        const { wrapper } = await mountWith(component, {});
        const options = wrapper.findAll('select option');

        expect(options.map((o) => o.attributes('value'))).toEqual(['grouped', 'separate']);
        expect(options.map((o) => o.text())).toEqual(labels);
    });

    it('defaults to grouped when nothing is stored, without writing the default', async () => {
        const { wrapper, config } = await mountWith(component, {});

        expect(wrapper.get('select').element.value).toBe('grouped');
        expect(config.value.display_in_checkout).toBeUndefined();
    });

    it('shows the stored value', async () => {
        const { wrapper } = await mountWith(component, { display_in_checkout: 'separate' });

        expect(wrapper.get('select').element.value).toBe('separate');
    });

    it('writes the choice into config.display_in_checkout', async () => {
        const { wrapper, config } = await mountWith(component, { display_in_checkout: 'grouped' });

        await wrapper.get('select').setValue('separate');

        expect(config.value.display_in_checkout).toBe('separate');
    });
});

describe('CreditCardPaymentConfig', () => {
    it('passes config.activeCreditcards to ActiveCreditcards and writes its updates back', async () => {
        const cards = [{ id: 3, name: 'VISA' }];
        const { wrapper, config } = await mountWith(CreditCardPaymentConfig, { activeCreditcards: cards });
        const picker = wrapper.findComponent({ name: 'ActiveCreditcards' });

        expect(picker.props('modelValue')).toEqual(cards);

        const next = [{ id: 1, name: 'American Express' }, { id: 3, name: 'VISA' }];
        picker.vm.$emit('update:modelValue', next);

        expect(config.value.activeCreditcards).toEqual(next);
    });

    it('shows the radio/dropdown display type control and writes config.display_type', async () => {
        const { wrapper, config } = await mountWith(CreditCardPaymentConfig, { display_type: 'radio' });
        const buttons = wrapper.findAll('[role="group"] button');

        expect(buttons.map((b) => b.text())).toEqual(['Radio', 'Dropdown']);
        expect(buttons[0].attributes('aria-pressed')).toBe('true');

        await buttons[1].trigger('click');

        expect(config.value.display_type).toBe('dropdown');
    });
});

describe('GiftcardPaymentConfig', () => {
    it('passes config.activeGiftcards to ActiveGiftcards and writes its updates back', async () => {
        const cards = [{ code: 'fashioncheque' }];
        const { wrapper, config } = await mountWith(GiftcardPaymentConfig, { activeGiftcards: cards });
        const picker = wrapper.findComponent({ name: 'ActiveGiftcards' });

        expect(picker.props('modelValue')).toEqual(cards);

        picker.vm.$emit('update:modelValue', []);

        expect(config.value.activeGiftcards).toEqual([]);
    });

    it('hands its payments prop to AllowedRedirectPayments', async () => {
        const payments = [{ name: 'ideal' }, { name: 'paypal' }];
        const { wrapper } = await mountWith(GiftcardPaymentConfig, {}, { props: { payments } });

        expect(wrapper.findComponent({ name: 'AllowedRedirectPayments' }).props('payments')).toEqual(payments);
    });

    it('defaults payments to an empty list', async () => {
        const { wrapper } = await mountWith(GiftcardPaymentConfig, {});

        expect(wrapper.findComponent({ name: 'AllowedRedirectPayments' }).props('payments')).toEqual([]);
    });
});
