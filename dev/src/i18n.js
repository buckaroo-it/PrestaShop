import { createI18n } from 'vue-i18n';
import en from '../lang/php_en.json';
import nl from '../lang/php_nl.json';

const messages = {
    en: en,
    nl: nl
};

const i18n = createI18n({
    legacy: false,
    locale: 'en', // set default locale
    messages,
});

export default i18n;
