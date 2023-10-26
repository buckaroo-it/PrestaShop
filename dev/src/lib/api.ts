import axios from 'axios';
import { computed, ref, watch, inject } from 'vue';
import Router from './router';

export const useApi = (endpoint: string) => {
    const csrfToken: string = inject('csrfToken')
    const adminUrl: string = inject('adminUrl')

    let router = new Router(adminUrl, csrfToken)

    const data = ref();
    const loading = ref(false);
    const error = ref();

    const api = axios.create({
        baseURL: adminUrl + ''
    })

    const get = (query?: Record<string, any>) => {
        loading.value = true
        error.value = undefined

        return api
            .get(router.generate(endpoint, query))
            .then((res) => (data.value = res.data))
            .catch((e) => {
                error.value = e

                throw e
            })
            .finally(() => (loading.value = false))
    }

    const post = (payload?: Record<string, any>, query?: Record<string, any>) => {
        loading.value = true
        error.value = undefined
        return api
            .post(router.generate(endpoint, query), payload)
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
