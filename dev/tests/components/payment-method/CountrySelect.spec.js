import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { enableAutoUnmount } from '@vue/test-utils';
import CountrySelect from '@/components/CountrySelect.vue';
import { useCountries } from '@/lib/countries';
import { mountAndSettle } from '../../helpers';
import { callsTo, resetApi } from '../../mocks/api';
import { COUNTRIES, resetCountries, respondCountries } from './countries';

vi.mock('@/lib/api', () => import('../../mocks/api'));

enableAutoUnmount(afterEach);

const options = (wrapper) => wrapper.findAll('li').map((li) => li.text());

describe('CountrySelect', () => {
    beforeEach(() => {
        resetCountries();
        respondCountries();
    });

    it('loads the countries and shows no list until something is typed', async () => {
        const wrapper = await mountAndSettle(CountrySelect);

        expect(callsTo('buckaroo_config_countries', 'get')).toHaveLength(1);
        expect(wrapper.find('ul').exists()).toBe(false);
        expect(wrapper.get('label[for="country"]').text()).toBe('Search Countries');
    });

    it('filters the countries case-insensitively while typing', async () => {
        const wrapper = await mountAndSettle(CountrySelect);

        await wrapper.get('input').setValue('LAN');

        expect(options(wrapper)).toEqual(['Netherlands']);
        expect(wrapper.get('li img').attributes('src')).toBe('/img/flags/nl.jpg');
    });

    it('lists every country for whitespace-only input', async () => {
        const wrapper = await mountAndSettle(CountrySelect);

        await wrapper.get('input').setValue('   ');

        expect(options(wrapper)).toEqual(COUNTRIES.map((c) => c.name));
    });

    it('shows an empty list when nothing matches', async () => {
        const wrapper = await mountAndSettle(CountrySelect);

        await wrapper.get('input').setValue('zzz');

        expect(wrapper.findAll('li')).toHaveLength(0);
    });

    it('emits the clicked country and clears the search', async () => {
        const wrapper = await mountAndSettle(CountrySelect);
        await wrapper.get('input').setValue('ger');

        await wrapper.get('li').trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([[COUNTRIES[1]]]);
        expect(wrapper.get('input').element.value).toBe('');
        expect(wrapper.find('ul').exists()).toBe(false);
    });

    it('clears the shared search text when unmounted', async () => {
        const wrapper = await mountAndSettle(CountrySelect);
        await wrapper.get('input').setValue('bel');

        wrapper.unmount();

        expect(useCountries().query.value).toBeNull();
    });

    it('keeps the list empty when the countries request reports a failure', async () => {
        resetApi(); // no response registered: the fake API answers { status: false }
        const wrapper = await mountAndSettle(CountrySelect);

        await wrapper.get('input').setValue('a');

        expect(wrapper.findAll('li')).toHaveLength(0);
    });
});
