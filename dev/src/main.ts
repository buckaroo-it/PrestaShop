import './bootstrap';

import {createApp} from 'vue'
import i18n from './i18n';

import Loading from "./components/Loading.vue";
import './assets/css/tailwind.scss'
import Dashboard from "./Dashboard.vue";


// Use the i18n instance in your app
createApp({})
    .use(i18n)
    .component('Loading', Loading)
    .component('Dashboard', Dashboard)
    .mount('#app')


