import { describe, expect, it } from 'vitest';
import Router from '@/lib/router';

describe('Router', () => {
    it('generates the admin URL of an exposed route with the token appended', () => {
        const router = new Router('https://shop.test/admin-dev', 'abc123');

        expect(router.generate('buckaroo_config_settings'))
            .toBe('https://shop.test/admin-dev/modules/buckaroo3/config/settings?_token=abc123');
    });

    it('strips trailing slashes from the admin URL so there is no double slash', () => {
        const router = new Router('https://shop.test/admin-dev///', 'abc123');

        expect(router.generate('buckaroo_config_settings'))
            .toBe('https://shop.test/admin-dev/modules/buckaroo3/config/settings?_token=abc123');
    });

    it('adds query parameters next to the token', () => {
        const router = new Router('https://shop.test/admin-dev/', 'tok');

        const url = new URL(router.generate('buckaroo_config_orderings', { country: 'NL' }));

        expect(url.pathname).toBe('/admin-dev/modules/buckaroo3/config/orderings');
        expect(url.searchParams.get('country')).toBe('NL');
        expect(url.searchParams.get('_token')).toBe('tok');
    });

    it('overrides a _token passed in the params with its own token', () => {
        const router = new Router('https://shop.test/admin-dev', 'mine');

        const url = new URL(router.generate('buckaroo_config_settings', { _token: 'other' }));

        expect(url.searchParams.getAll('_token')).toEqual(['mine']);
    });

    it.each([[''], ['   '], [undefined]])('leaves the token out when it is empty (%j)', (token) => {
        const router = new Router('https://shop.test/admin-dev', token);

        expect(router.generate('buckaroo_config_settings'))
            .toBe('https://shop.test/admin-dev/modules/buckaroo3/config/settings');
    });

    it('keeps a _token already in the params when it has no token of its own', () => {
        const router = new Router('https://shop.test/admin-dev', '');

        expect(router.generate('buckaroo_config_settings', { _token: 'from-url' }))
            .toBe('https://shop.test/admin-dev/modules/buckaroo3/config/settings?_token=from-url');
    });

    it('throws for a route that is not exposed', () => {
        const router = new Router('https://shop.test/admin-dev', 'tok');

        expect(() => router.generate('does_not_exist')).toThrow();
    });
});
