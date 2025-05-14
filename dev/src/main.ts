import '@/bootstrap';
import { createApp } from 'vue';
import i18n            from './i18n';
import Loading         from '@/components/Loading.vue';
import Dashboard       from '@/Dashboard.vue';
import '@/assets/css/tailwind.scss';

// If you later want to code‑split Dashboard or other pages,
// switch to dynamic imports:
//
// const Dashboard = () => import('@/Dashboard.vue');

createApp({})
    .use(i18n)
    .component('Loading', Loading)
    .component('Dashboard', Dashboard)
    .mount('#app');
