import axios from 'axios';
import { computed, ref, watch, inject } from 'vue';

export const useApi = (endpoint: string, access_token?: string) => {
    const signedJWT = inject('signedJWT')
    const baseUrl = inject('baseUrl')

    var endPoint = endpoint
    var headers = {
        Authorization: `Bearer ${signedJWT ?? access_token}`,
        'Content-Type': 'application/json'
    }

    const data = ref();
    const loading = ref(false);
    const error = ref();

    const api = axios.create({
        baseURL: baseUrl + ''
    })

    const get = (query?: Record<string, any>) => {
        loading.value = true
        error.value = undefined

        let queryString = ''

        if (query) {
            queryString =
                '?' +
                Object.entries(query)
                    .map(
                        ([key, value]) =>
                            `${encodeURIComponent(key)}=${encodeURIComponent(
                                value
                            )}`
                    )
                    .join('&')
        }

        return api
            .get(endPoint + queryString, {
                headers: headers
            })
            .then((res) => (data.value = res.data))
            .catch((e) => {
                error.value = e

                throw e
            })
            .finally(() => (loading.value = false))
    }

    const post = (payload?: Record<string, any>) => {
        loading.value = true
        error.value = undefined

        return api
            .post(endPoint, payload, {
                headers: headers
            })
            .then((res) => (data.value = res.data))
            .catch((e) => {
                error.value = e;
                throw e;
            })
            .finally(() => (loading.value = false));
    };

    const errorMessage = computed(() => {
        return error.value ? error.value.message : null;
    });

    watch(error, (currentError) => {

    });

    return {
        loading,
        data,
        error,
        get,
        post,
        errorMessage
    };
};
