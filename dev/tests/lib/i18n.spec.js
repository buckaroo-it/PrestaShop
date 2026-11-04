import { describe, expect, it } from 'vitest';
import i18n from '@/i18n';
import en from '../../lang/php_en.json';
import nl from '../../lang/php_nl.json';
import fr from '../../lang/php_fr.json';
import de from '../../lang/php_de.json';

// The language files are flat: { "dashboard.menu.settings": "Settings", ... }
const flatten = (obj) => Object.keys(obj);

const LOCALES = { en, nl, fr, de };

// Every English key must be translated; list a key here only while a translation is pending.
const KNOWN_MISSING = {
    nl: [],
    fr: [],
    de: [],
};

describe('i18n', () => {
    it('uses the composition API with English as default and all four languages', () => {
        expect(i18n.mode).toBe('composition');
        expect(i18n.global.locale.value).toBe('en');
        expect(Object.keys(i18n.global.messages.value).sort()).toEqual(['de', 'en', 'fr', 'nl']);
    });

    it('translates a key in each language', () => {
        const { t, locale } = i18n.global;
        try {
            for (const [code, messages] of Object.entries(LOCALES)) {
                locale.value = code;
                expect(t('dashboard.menu.settings')).toBe(messages['dashboard.menu.settings']);
            }
        } finally {
            locale.value = 'en';
        }
    });

    describe.each(['nl', 'fr', 'de'])('php_%s.json', (code) => {
        const enKeys = flatten(en);
        const keys = flatten(LOCALES[code]);

        it('has every English key (apart from the known gaps)', () => {
            const missing = enKeys.filter((key) => !keys.includes(key));
            expect(missing.sort()).toEqual([...KNOWN_MISSING[code]].sort());
        });

        it('has no keys that English lacks', () => {
            expect(keys.filter((key) => !enKeys.includes(key))).toEqual([]);
        });

        it('has no empty translations', () => {
            const empty = keys.filter((key) => String(LOCALES[code][key]).trim() === '');
            expect(empty).toEqual([]);
        });
    });
});
