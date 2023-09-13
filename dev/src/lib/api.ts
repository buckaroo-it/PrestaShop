import axios, { AxiosRequestConfig } from 'axios';
import { computed, ref, watch, inject } from 'vue';
// Uncomment this if you want to use the router for redirects
// import { useRouter } from 'vue-router';

export const useApi = (endpoint: string, access_token?: string) => {
    // If you want to use redirection based on error codes
    // const router = useRouter();
    const signedJWT = inject('signedJWT')

    var endPoint = endpoint
    var headers = {
        Authorization: `Bearer ${signedJWT ?? access_token}`,
        'Content-Type': 'application/json'
    }

    const data = ref();
    const loading = ref(false);
    const error = ref();

    const api = axios.create({
        baseURL: ''
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

        // Convert payload to URL encoded form data
        const formData = new URLSearchParams();
        if (payload) {
            Object.entries(payload).forEach(([key, value]) => {
                formData.append(key, value);
            });
        }

        // Adjust headers for form data
        const formHeaders = {
            ...headers,
            'Content-Type': 'application/x-www-form-urlencoded'
        }

        return api
            .post(endPoint, formData.toString(), {
                headers: formHeaders
            })
            .then((res) => (data.value = res.data))
            .catch((e) => {
                error.value = e

                throw e
            })
            .finally(() => (loading.value = false))
    };


    // Similar structure for post, postData, and del...

    const errorMessage = computed(() => {
        return error.value ? error.value.message : null;
    });

    watch(error, (currentError) => {
        // If you want to handle a 401 Unauthorized error by redirecting
        // if (currentError?.response?.status === 401 && router) {
        //     router.push('/login');
        // }
    });

    return {
        loading,
        data,
        error,
        get,
        post,
        // Add other methods here like post, postData, del...
        errorMessage
    };
};