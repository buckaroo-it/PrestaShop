import { describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import PayPerEmailPaymentConfig from '@/components/payments/PayPerEmailPaymentConfig.vue';
import { flushPromises } from '../../helpers';
import { mountConfig } from './mountConfig';

vi.mock('@/lib/api', () => import('../../mocks/api'));

const payments = [{ name: 'ideal' }, { name: 'paypal' }, { name: 'transfer' }];

const mountPpe = (initial = {}, props = { payments }) => mountConfig(PayPerEmailPaymentConfig, initial, { props });

const checkedNames = (wrapper) => wrapper.findAll('input[type="checkbox"]')
    .filter((c) => c.element.checked)
    .map((c) => c.attributes('value'));

describe('PayPerEmailPaymentConfig', () => {
    it('lists one checkbox per payment method', async () => {
        const { wrapper } = await mountPpe();

        expect(wrapper.findAll('input[type="checkbox"]').map((c) => c.attributes('value'))).toEqual(['ideal', 'paypal', 'transfer']);
        expect(wrapper.text()).not.toContain('No payment methods available');
    });

    it('shows an empty-state message when there are no payment methods', async () => {
        const { wrapper } = await mountPpe({}, { payments: [] });

        expect(wrapper.find('input[type="checkbox"]').exists()).toBe(false);
        expect(wrapper.text()).toContain('No payment methods available');
    });

    it.each([
        ['nothing stored', {}, []],
        ['an empty string', { allowed_payments: '' }, []],
        ['a comma-separated string with spaces and blanks', { allowed_payments: 'ideal, transfer,,' }, ['ideal', 'transfer']],
        ['an array', { allowed_payments: ['paypal'] }, ['paypal']],
    ])('checks the stored methods when config.allowed_payments is %s', async (_label, initial, expected) => {
        const { wrapper } = await mountPpe(initial);

        expect(checkedNames(wrapper)).toEqual(expected);
    });

    it('writes the checked methods back as a comma-separated string', async () => {
        const { wrapper, config } = await mountPpe({ allowed_payments: 'ideal' });
        const boxes = wrapper.findAll('input[type="checkbox"]');

        await boxes[2].setValue(true);
        await flushPromises();
        expect(config.value.allowed_payments).toBe('ideal,transfer');

        await boxes[0].setValue(false);
        await flushPromises();
        expect(config.value.allowed_payments).toBe('transfer');

        await boxes[2].setValue(false);
        await flushPromises();
        expect(config.value.allowed_payments).toBe('');
        expect(checkedNames(wrapper)).toEqual([]);
    });

    it('converts an array loaded from the config into a string once the merchant changes it', async () => {
        const { wrapper, config } = await mountPpe({ allowed_payments: ['paypal'] });

        await wrapper.findAll('input[type="checkbox"]')[0].setValue(true);
        await flushPromises();

        expect(config.value.allowed_payments).toBe('paypal,ideal');
    });

    it('follows config.allowed_payments when it changes from outside (e.g. loaded from the backend)', async () => {
        const { wrapper, config } = await mountPpe({});

        config.value.allowed_payments = 'paypal,transfer';
        await flushPromises();
        expect(checkedNames(wrapper)).toEqual(['paypal', 'transfer']);

        config.value.allowed_payments = ['ideal'];
        await flushPromises();
        expect(checkedNames(wrapper)).toEqual(['ideal']);

        config.value.allowed_payments = null;
        await flushPromises();
        expect(checkedNames(wrapper)).toEqual([]);
    });

    it('ignores a non-string, non-array allowed_payments value from outside', async () => {
        const { wrapper, config } = await mountPpe({ allowed_payments: 'paypal' });

        config.value.allowed_payments = 42;
        await nextTick();

        expect(checkedNames(wrapper)).toEqual(['paypal']);
    });

    it('binds the due date and instruction email settings', async () => {
        const { wrapper, config } = await mountPpe({ due_days: 14, send_instruction_email: '1' });

        expect(wrapper.get('input[type="number"]').element.value).toBe('14');
        expect(wrapper.get('select').element.value).toBe('1');
        expect(wrapper.findAll('select option').map((o) => o.attributes('value'))).toEqual(['1', '0']);

        await wrapper.get('input[type="number"]').setValue('30');
        await wrapper.get('select').setValue('0');

        expect(config.value.due_days).toBe(30);
        expect(config.value.send_instruction_email).toBe('0');
    });
});
