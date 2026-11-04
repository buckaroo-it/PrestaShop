/*
 * In-memory replacement for src/lib/api.ts (useApi). Register it in a test with
 *
 *     vi.mock('@/lib/api', () => import('../mocks/api'))   // path relative to the spec
 *
 * then configure responses and assert on calls:
 *
 *     respond('buckaroo_config_creditcards', { status: true, creditcards: [...] })
 *     respond('buckaroo_config_settings', (method, payload, query) => ({ status: true }), 'post')
 *     fail('buckaroo_config_settings', new Error('boom'), 'post')
 *     expect(callsTo('buckaroo_config_settings', 'post')[0].payload).toEqual({...})
 */
import { computed, ref } from 'vue';

const responses = new Map();
export const calls = [];

const key = (endpoint, method) => `${method}:${endpoint}`;

export const resetApi = () => {
    responses.clear();
    calls.length = 0;
};

/** Response for an endpoint; method 'get', 'post' or 'any'. A function receives (method, payload, query). */
export const respond = (endpoint, response, method = 'any') => {
    responses.set(key(endpoint, method), { response });
};

/** Make an endpoint reject with the given error. */
export const fail = (endpoint, error = new Error('Request failed'), method = 'any') => {
    responses.set(key(endpoint, method), { error });
};

export const callsTo = (endpoint, method = null) =>
    calls.filter((call) => call.endpoint === endpoint && (method === null || call.method === method));

const resolveResponse = (endpoint, method, payload, query) => {
    const entry = responses.get(key(endpoint, method)) ?? responses.get(key(endpoint, 'any'));
    if (!entry) {
        return { ok: true, value: { status: false } };
    }
    if (entry.error) {
        return { ok: false, value: entry.error };
    }
    const value = typeof entry.response === 'function'
        ? entry.response(method, payload, query)
        : entry.response;

    // Hand out a copy so components can't mutate the registered fixture.
    return { ok: true, value: value === undefined ? value : JSON.parse(JSON.stringify(value)) };
};

export const useApi = (endpoint) => {
    const data = ref();
    const loading = ref(false);
    const error = ref();

    const request = (method, payload, query) => {
        calls.push({ endpoint, method, payload, query });
        loading.value = true;
        error.value = undefined;
        const result = resolveResponse(endpoint, method, payload, query);

        return Promise.resolve()
            .then(() => {
                if (!result.ok) {
                    error.value = result.value;
                    throw result.value;
                }
                data.value = result.value;

                return result.value;
            })
            .finally(() => {
                loading.value = false;
            });
    };

    return {
        data,
        loading,
        error,
        get: (query) => request('get', undefined, query),
        post: (payload, query) => request('post', payload, query),
        errorMessage: computed(() => (error.value ? error.value.message : null)),
    };
};
