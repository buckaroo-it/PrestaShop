/*
 * Shared country fixtures for the payment method specs. src/lib/countries.ts keeps
 * its `countries` and `query` refs at module level, so every test resets them.
 */
import { useCountries } from '@/lib/countries';
import { resetApi, respond } from '../../mocks/api';

export const COUNTRIES = [
    { id: 1, name: 'Belgium', iso_code_2: 'BE', icon: 'be.jpg' },
    { id: 2, name: 'Germany', iso_code_2: 'DE', icon: 'de.jpg' },
    { id: 3, name: 'Netherlands', iso_code_2: 'NL', icon: 'nl.jpg' },
    { id: 4, name: 'France', iso_code_2: 'FR', icon: 'fr.jpg' },
];

/** Empty the module-level country state and the fake API's call log. */
export const resetCountries = () => {
    const { countries, query } = useCountries();
    countries.value = [];
    query.value = null;
    resetApi();
};

export const respondCountries = (list = COUNTRIES) =>
    respond('buckaroo_config_countries', { status: true, countries: list }, 'get');

/** n made-up countries, ids 100.. */
export const manyCountries = (n) =>
    Array.from({ length: n }, (_, i) => ({ id: 100 + i, name: `Country ${i + 1}`, icon: `c${i}.jpg` }));
