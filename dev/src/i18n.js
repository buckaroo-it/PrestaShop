/*
 *
 *
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * It is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * @author Buckaroo.nl <plugins@buckaroo.nl>
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   http://opensource.org/licenses/afl-3.0 Academic Free License (AFL 3.0)
 */
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
