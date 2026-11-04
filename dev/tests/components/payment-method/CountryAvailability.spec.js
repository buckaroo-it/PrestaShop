import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { enableAutoUnmount } from '@vue/test-utils';
import CountryAvailability from '@/components/CountryAvailability.vue';
import { mountAndSettle } from '../../helpers';
import { COUNTRIES, manyCountries, resetCountries, respondCountries } from './countries';

vi.mock('@/lib/api', () => import('../../mocks/api'));

enableAutoUnmount(afterEach);

const mountAvailability = (modelValue) =>
    mountAndSettle(CountryAvailability, { props: modelValue === undefined ? {} : { modelValue } });

const chips = (wrapper) => wrapper.findAll('button[aria-label^="Remove: "]').map((b) => b.attributes('aria-label').replace('Remove: ', ''));
const button = (wrapper, text) => wrapper.findAll('button').find((b) => b.text() === text);
const lastEmit = (wrapper) => wrapper.emitted('update:modelValue').at(-1)[0];

describe('CountryAvailability', () => {
    beforeEach(() => {
        resetCountries();
        respondCountries();
    });

    it('explains that no selection means all countries', async () => {
        const wrapper = await mountAvailability();

        expect(wrapper.text()).toContain('No countries selected, so this payment method is shown in all countries.');
        expect(wrapper.text()).not.toMatch(/\d+ selected/);
        expect(wrapper.find('ul.flex-wrap').exists()).toBe(false);
        expect(button(wrapper, 'Clear').attributes('disabled')).toBeDefined();
    });

    it('treats a null model value as no selection', async () => {
        const wrapper = await mountAvailability(null);

        expect(wrapper.text()).toContain('shown in all countries');
    });

    it('shows the selected countries as chips with a count', async () => {
        const wrapper = await mountAvailability([COUNTRIES[0], COUNTRIES[2]]);

        expect(chips(wrapper)).toEqual(['Belgium', 'Netherlands']);
        expect(wrapper.text()).toContain('2 selected');
        expect(wrapper.find('img[src="/img/flags/be.jpg"]').exists()).toBe(true);
        expect(button(wrapper, 'Clear').attributes('disabled')).toBeUndefined();
    });

    it('removes a country with its chip button', async () => {
        const wrapper = await mountAvailability([COUNTRIES[0], COUNTRIES[2]]);

        await wrapper.get('button[aria-label="Remove: Belgium"]').trigger('click');

        expect(lastEmit(wrapper)).toEqual([COUNTRIES[2]]);
    });

    it('selects every loaded country', async () => {
        const wrapper = await mountAvailability([COUNTRIES[0]]);

        await button(wrapper, 'Select all').trigger('click');

        expect(lastEmit(wrapper)).toEqual(COUNTRIES);
    });

    it('clears the selection', async () => {
        const wrapper = await mountAvailability([COUNTRIES[0]]);

        await button(wrapper, 'Clear').trigger('click');

        expect(lastEmit(wrapper)).toEqual([]);
    });

    it('adds a country picked in the search', async () => {
        const wrapper = await mountAvailability([COUNTRIES[0]]);
        await wrapper.get('input').setValue('fra');

        await wrapper.get('ul.text-sm li').trigger('click');

        expect(lastEmit(wrapper)).toEqual([COUNTRIES[0], COUNTRIES[3]]);
    });

    it('ignores a picked country that is already selected', async () => {
        const wrapper = await mountAvailability([COUNTRIES[0]]);
        await wrapper.get('input').setValue('bel');

        await wrapper.get('ul.text-sm li').trigger('click');

        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('shows the first ten countries and toggles the rest', async () => {
        const selected = manyCountries(13);
        const wrapper = await mountAvailability(selected);

        expect(chips(wrapper)).toHaveLength(10);
        expect(wrapper.text()).toContain('13 selected');
        const toggle = button(wrapper, 'and 3 more...');
        expect(toggle).toBeDefined();

        await toggle.trigger('click');
        expect(chips(wrapper)).toHaveLength(13);
        expect(chips(wrapper).at(-1)).toBe('Country 13');

        await button(wrapper, 'Show less').trigger('click');
        expect(chips(wrapper)).toHaveLength(10);
    });

    it('has no toggle with exactly ten countries', async () => {
        const wrapper = await mountAvailability(manyCountries(10));

        expect(chips(wrapper)).toHaveLength(10);
        expect(wrapper.text()).not.toContain('more...');
    });
});
