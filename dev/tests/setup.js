/*
 * Global test setup: every mounted component gets the real i18n plugin (English),
 * the fake API starts empty and the URL hash is cleared.
 */
import { config } from '@vue/test-utils';
import { beforeEach } from 'vitest';
import i18n from '@/i18n';
import { resetApi } from './mocks/api';

config.global.plugins = [i18n];

// Several lang strings contain HTML on purpose (rendered with v-html); the
// "Detected HTML in message" warning only hides real warnings in test output.
i18n.global.warnHtmlMessage = false;

beforeEach(() => {
    resetApi();
    i18n.global.locale.value = 'en';
    window.history.replaceState(null, '', '/');
});
