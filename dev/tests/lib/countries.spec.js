import { afterEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import { callsTo, fail, respond } from '../mocks/api';
import { useCountries as realUseCountries } from '@/lib/countries';

vi.mock('@/lib/api', () => import('../mocks/api'));

const COUNTRIES = [
    { name: 'Netherlands', iso_code_2: 'NL' },
    { name: 'Belgium', iso_code_2: 'BE' },
    { name: 'New Zealand', iso_code_2: 'NZ' },
];

// countries.ts keeps the list and query at module level; reset them after each test.
let last;
const useCountries = () => (last = realUseCountries());

describe('useCountries', () => {
    afterEach(() => {
        last.countries.value = [];
        last.query.value = null;
    });

    it('loads the countries and returns them all without a query', async () => {
        respond('buckaroo_config_countries', { status: true, countries: COUNTRIES });

        const { countries, filteredCountries } = useCountries();
        await flushPromises();

        expect(callsTo('buckaroo_config_countries', 'get')).toHaveLength(1);
        expect(countries.value).toEqual(COUNTRIES);
        expect(filteredCountries.value).toEqual(COUNTRIES);
    });

    it('filters case-insensitively by name', async () => {
        respond('buckaroo_config_countries', { status: true, countries: COUNTRIES });
        const { query, filteredCountries } = useCountries();
        await flushPromises();

        query.value = 'NE';
        expect(filteredCountries.value.map((c) => c.iso_code_2)).toEqual(['NL', 'NZ']);

        query.value = 'belg';
        expect(filteredCountries.value.map((c) => c.iso_code_2)).toEqual(['BE']);

        query.value = '   ';
        expect(filteredCountries.value).toEqual(COUNTRIES);
    });

    it('keeps the list empty when the response has no success status', async () => {
        respond('buckaroo_config_countries', { status: false, countries: COUNTRIES });
        const { countries } = useCountries();
        await flushPromises();

        expect(countries.value).toEqual([]);
    });

    it('keeps the list empty, without an unhandled rejection, when the request fails', async () => {
        fail('buckaroo_config_countries', new Error('Network down'));
        const { countries, filteredCountries } = useCountries();
        await flushPromises();

        expect(countries.value).toEqual([]);
        expect(filteredCountries.value).toEqual([]);
    });

    it('shares the list and query between callers', async () => {
        respond('buckaroo_config_countries', { status: true, countries: COUNTRIES });
        const first = useCountries();
        const second = useCountries();
        await flushPromises();

        first.query.value = 'zea';
        expect(second.filteredCountries.value.map((c) => c.iso_code_2)).toEqual(['NZ']);
    });
});
