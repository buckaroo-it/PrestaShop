import { describe, expect, it, vi } from 'vitest';
import ActiveCreditcards from '@/components/fields/ActiveCreditcards.vue';
import { mountAndSettle } from '../../helpers';
import { respond, callsTo } from '../../mocks/api';

vi.mock('@/lib/api', () => import('../../mocks/api'));

const catalogue = [
    { id: 1, name: 'American Express', icon: 'AMEX.svg' },
    { id: 2, name: 'Maestro', icon: 'Maestro.svg' },
    { id: 3, name: 'VISA', icon: 'Visa.svg' },
];

const mountCards = (modelValue = []) => {
    respond('buckaroo_config_creditcards', { status: true, creditcards: catalogue }, 'get');

    return mountAndSettle(ActiveCreditcards, { props: { modelValue } });
};

const chipNames = (wrapper) => wrapper.findAll('button[aria-label^="Remove "]').map((b) => b.attributes('aria-label').replace('Remove ', ''));

describe('ActiveCreditcards', () => {
    it('loads the card catalogue once', async () => {
        await mountCards();

        expect(callsTo('buckaroo_config_creditcards', 'get')).toHaveLength(1);
    });

    it('shows an empty-state hint and no chips when nothing is selected', async () => {
        const wrapper = await mountCards();

        expect(chipNames(wrapper)).toEqual([]);
        expect(wrapper.text()).toContain('No cards selected yet');
    });

    it('shows the selected cards as chips while the list is closed, in catalogue order', async () => {
        const wrapper = await mountCards([{ id: 3, name: 'VISA' }, { id: 1, name: 'American Express' }]);

        expect(wrapper.find('li[aria-selected]').exists()).toBe(false); // list closed
        expect(chipNames(wrapper)).toEqual(['American Express', 'VISA']);
        expect(wrapper.text()).not.toContain('No cards selected yet');
        expect(wrapper.find('img[src$="AMEX.svg"]').exists()).toBe(true);
    });

    it('keeps saved cards the catalogue does not know', async () => {
        const wrapper = await mountCards([{ id: 99, name: 'Legacy card' }]);

        expect(chipNames(wrapper)).toEqual(['Legacy card']);
    });

    it('removes a card with its chip and emits the new selection', async () => {
        const wrapper = await mountCards([{ id: 1, name: 'American Express' }, { id: 2, name: 'Maestro' }]);

        await wrapper.get('button[aria-label="Remove Maestro"]').trigger('click');

        expect(chipNames(wrapper)).toEqual(['American Express']);
        const emitted = wrapper.emitted('update:modelValue');
        expect(emitted.at(-1)[0].map((c) => c.id)).toEqual([1]);
    });

    it('opens the list on click and marks selected cards with a check', async () => {
        const wrapper = await mountCards([{ id: 2, name: 'Maestro' }]);

        await wrapper.get('input').trigger('click');
        const items = wrapper.findAll('li[aria-selected]');

        expect(items.map((li) => li.text())).toEqual(['American Express', 'Maestro', 'VISA']);
        expect(items.map((li) => li.attributes('aria-selected'))).toEqual(['false', 'true', 'false']);
        expect(items[1].find('.fa-check').exists()).toBe(true);
        expect(items[1].classes()).toContain('bg-primary');
    });

    it('toggles a card from the list', async () => {
        const wrapper = await mountCards([]);
        await wrapper.get('input').trigger('click');

        await wrapper.findAll('li[aria-selected]')[2].trigger('click');
        expect(chipNames(wrapper)).toEqual(['VISA']);

        await wrapper.findAll('li[aria-selected]')[2].trigger('click');
        expect(chipNames(wrapper)).toEqual([]);
    });

    it('filters the list case-insensitively', async () => {
        const wrapper = await mountCards();
        await wrapper.get('input').trigger('click');

        await wrapper.get('input').setValue('visa');

        expect(wrapper.findAll('li[aria-selected]').map((li) => li.text())).toEqual(['VISA']);
    });

    it('follows a new modelValue from the parent', async () => {
        const wrapper = await mountCards([]);

        await wrapper.setProps({ modelValue: [{ id: 2, name: 'Maestro' }] });

        expect(chipNames(wrapper)).toEqual(['Maestro']);
    });
});
