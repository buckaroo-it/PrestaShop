import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import { withSetup } from './withSetup';

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), create: vi.fn() }));

vi.mock('axios', () => ({ default: { create: http.create } }));

import { useApi } from '@/lib/api';

const provide = { csrfToken: 'test-token', adminUrl: 'https://shop.test/admin-dev/' };
const SETTINGS_URL = 'https://shop.test/admin-dev/modules/buckaroo3/config/settings';

const deferred = () => {
    let resolve;
    let reject;
    const promise = new Promise((res, rej) => { resolve = res; reject = rej; });
    return { promise, resolve, reject };
};

describe('useApi', () => {
    beforeEach(() => {
        http.get.mockReset();
        http.post.mockReset();
        http.create.mockReset().mockReturnValue({ get: http.get, post: http.post });
    });

    it('creates an axios instance on the injected admin URL', () => {
        withSetup(() => useApi('buckaroo_config_settings'), provide);

        expect(http.create).toHaveBeenCalledWith({ baseURL: 'https://shop.test/admin-dev/' });
    });

    it('get() requests the tokenized route URL and stores the response data', async () => {
        http.get.mockResolvedValue({ data: { status: true, settings: { mode: 'test' } } });
        const { result } = withSetup(() => useApi('buckaroo_config_settings'), provide);

        const value = await result.get();

        expect(http.get).toHaveBeenCalledWith(`${SETTINGS_URL}?_token=test-token`);
        expect(value).toEqual({ status: true, settings: { mode: 'test' } });
        expect(result.data.value).toEqual(value);
        expect(result.error.value).toBeUndefined();
        expect(result.errorMessage.value).toBeNull();
    });

    it('get() passes the query as URL parameters', async () => {
        http.get.mockResolvedValue({ data: {} });
        const { result } = withSetup(() => useApi('buckaroo_config_orderings'), provide);

        await result.get({ country: 'BE' });

        const url = new URL(http.get.mock.calls[0][0]);
        expect(url.searchParams.get('country')).toBe('BE');
        expect(url.searchParams.get('_token')).toBe('test-token');
    });

    it('is loading while a get is in flight and not afterwards', async () => {
        const request = deferred();
        http.get.mockReturnValue(request.promise);
        const { result } = withSetup(() => useApi('buckaroo_config_settings'), provide);

        expect(result.loading.value).toBe(false);
        const pending = result.get();
        expect(result.loading.value).toBe(true);

        request.resolve({ data: { status: true } });
        await pending;

        expect(result.loading.value).toBe(false);
    });

    it('get() stores and rethrows the error, and clears loading', async () => {
        const failure = new Error('Network Error');
        http.get.mockRejectedValue(failure);
        const { result } = withSetup(() => useApi('buckaroo_config_settings'), provide);

        await expect(result.get()).rejects.toBe(failure);
        await nextTick();

        expect(result.error.value).toBe(failure);
        expect(result.errorMessage.value).toBe('Network Error');
        expect(result.loading.value).toBe(false);
    });

    it('clears a previous error when a new request starts', async () => {
        http.get.mockRejectedValueOnce(new Error('first')).mockResolvedValueOnce({ data: { status: true } });
        const { result } = withSetup(() => useApi('buckaroo_config_settings'), provide);

        await result.get().catch(() => {});
        expect(result.errorMessage.value).toBe('first');

        await result.get();
        expect(result.error.value).toBeUndefined();
        expect(result.errorMessage.value).toBeNull();
    });

    it('post() sends the payload to the tokenized URL with the query', async () => {
        http.post.mockResolvedValue({ data: { status: true } });
        const { result } = withSetup(() => useApi('buckaroo_config_paymentMethod'), provide);

        const value = await result.post({ value: 'on' }, { paymentName: 'ideal' });

        const [url, payload] = http.post.mock.calls[0];
        expect(new URL(url).pathname).toBe('/admin-dev/modules/buckaroo3/config/payment/methods');
        expect(new URL(url).searchParams.get('paymentName')).toBe('ideal');
        expect(new URL(url).searchParams.get('_token')).toBe('test-token');
        expect(payload).toEqual({ value: 'on' });
        expect(value).toEqual({ status: true });
        expect(result.data.value).toEqual({ status: true });
        expect(result.loading.value).toBe(false);
    });

    it('post() is loading while in flight', async () => {
        const request = deferred();
        http.post.mockReturnValue(request.promise);
        const { result } = withSetup(() => useApi('buckaroo_config_settings'), provide);

        const pending = result.post({});
        expect(result.loading.value).toBe(true);
        request.resolve({ data: {} });
        await pending;
        expect(result.loading.value).toBe(false);
    });

    it('post() stores and rethrows the error', async () => {
        const failure = new Error('Request failed with status code 500');
        http.post.mockRejectedValue(failure);
        const { result } = withSetup(() => useApi('buckaroo_config_settings'), provide);

        await expect(result.post({ a: 1 })).rejects.toBe(failure);
        await nextTick();

        expect(result.error.value).toBe(failure);
        expect(result.errorMessage.value).toBe('Request failed with status code 500');
        expect(result.loading.value).toBe(false);
    });

    it('works without a token (no _token parameter)', async () => {
        http.get.mockResolvedValue({ data: {} });
        const { result } = withSetup(() => useApi('buckaroo_config_settings'),
            { csrfToken: '', adminUrl: 'https://shop.test/admin-dev' });

        await result.get();

        expect(http.get).toHaveBeenCalledWith(SETTINGS_URL);
    });
});
