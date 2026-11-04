import { describe, expect, it } from 'vitest';
import { ref } from 'vue';
import AllowedRedirectPayments from '@/components/fields/AllowedRedirectPayments.vue';
import { mountApp } from '../../helpers';

const payments = [
    { name: 'ideal', icon: 'iDEAL.svg' },
    { name: 'paypal', icon: 'PayPal.svg' },
    { name: 'giftcard', icon: 'Giftcards.svg' },
    { name: 'idin', icon: 'iDIN.svg' },
    { name: 'bancontact', icon: null },
];

const mountField = (allowed, props = { payments }) => {
    const config = ref(allowed === undefined ? {} : { allowed_payments: allowed });
    const wrapper = mountApp(AllowedRedirectPayments, { props, provide: { config } });

    return { wrapper, config };
};

const items = (wrapper) => wrapper.findAll('li').filter((li) => !li.classes().includes('text-gray-500'));
const labels = (wrapper) => items(wrapper).map((li) => li.text());
const selected = (wrapper) => items(wrapper).filter((li) => li.classes().includes('bg-primary')).map((li) => li.text());

describe('AllowedRedirectPayments', () => {
    it('lists the payment methods except giftcard and iDIN, with translated labels', () => {
        const { wrapper } = mountField([]);

        expect(labels(wrapper)).toEqual(['iDEAL | Wero', 'PayPal', 'Bancontact']);
    });

    it('shows each icon from the shop base url, and none for methods without one', () => {
        const { wrapper } = mountField([]);
        const [ideal, , bancontact] = items(wrapper);

        expect(ideal.get('img').attributes('src')).toBe('https://shop.test/modules/buckaroo3/views/img/buckaroo/Payment methods/SVG/iDEAL.svg');
        expect(ideal.get('img').attributes('alt')).toBe('iDEAL | Wero');
        expect(bancontact.find('img').exists()).toBe(false);
    });

    it('highlights the allowed payments from an array of names', () => {
        const { wrapper } = mountField(['paypal']);

        expect(selected(wrapper)).toEqual(['PayPal']);
        expect(items(wrapper)[0].classes()).toContain('hover:bg-gray-200');
    });

    it('accepts allowed payments saved as objects', () => {
        const { wrapper } = mountField([{ name: 'ideal' }, null, { label: 'no name' }]);

        expect(selected(wrapper)).toEqual(['iDEAL | Wero']);
    });

    it('accepts allowed payments saved as a comma separated string', () => {
        const { wrapper } = mountField('ideal, bancontact,');

        expect(selected(wrapper)).toEqual(['iDEAL | Wero', 'Bancontact']);
    });

    it('treats an empty string or a missing value as nothing allowed', () => {
        expect(selected(mountField('').wrapper)).toEqual([]);
        expect(selected(mountField(undefined).wrapper)).toEqual([]);
        expect(selected(mountField(42).wrapper)).toEqual([]);
    });

    it('adds a payment to the config when clicked', async () => {
        const { wrapper, config } = mountField('ideal');

        await items(wrapper)[1].trigger('click');

        expect(config.value.allowed_payments).toEqual(['ideal', 'paypal']);
        expect(selected(wrapper)).toEqual(['iDEAL | Wero', 'PayPal']);
    });

    it('removes an allowed payment when clicked again', async () => {
        const { wrapper, config } = mountField([{ name: 'ideal' }, 'paypal']);

        await items(wrapper)[0].trigger('click');

        expect(config.value.allowed_payments).toEqual(['paypal']);
    });

    it('starts the list when nothing was allowed yet', async () => {
        const { wrapper, config } = mountField(undefined);

        await items(wrapper)[2].trigger('click');

        expect(config.value.allowed_payments).toEqual(['bancontact']);
    });

    it('filters by translated label or by method name, case-insensitively', async () => {
        const { wrapper } = mountField([]);
        const search = wrapper.get('#search-redirect-payment');

        await search.setValue('WERO');
        expect(labels(wrapper)).toEqual(['iDEAL | Wero']);

        await search.setValue('bancon');
        expect(labels(wrapper)).toEqual(['Bancontact']);

        await search.setValue('  ');
        expect(labels(wrapper)).toHaveLength(3);
    });

    it('associates the search label with its input', () => {
        const { wrapper } = mountField([]);

        expect(wrapper.get('label').attributes('for')).toBe('search-redirect-payment');
    });

    it('shows an empty state when no method matches', async () => {
        const { wrapper } = mountField([]);

        await wrapper.get('#search-redirect-payment').setValue('klarna');

        expect(items(wrapper)).toHaveLength(0);
        expect(wrapper.text()).toContain('No payment methods available');
    });

    it('shows the empty state without payments, and skips empty entries', () => {
        expect(mountField([], {}).wrapper.text()).toContain('No payment methods available');
        expect(mountField([], { payments: null }).wrapper.text()).toContain('No payment methods available');
        expect(labels(mountField([], { payments: [null, { name: 'paypal' }] }).wrapper)).toEqual(['PayPal']);
    });
});
