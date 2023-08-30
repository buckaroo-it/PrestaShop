declare global {
    interface Window { axios: any; }
}

window.axios = axios;