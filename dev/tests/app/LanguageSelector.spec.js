import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import i18n from '@/i18n';
import LanguageSelector from '@/components/LanguageSelector.vue';

const toggle = (wrapper) => wrapper.get('[role="button"]');
const options = (wrapper) => wrapper.findAll('li');

describe('LanguageSelector', () => {
    it('shows the current language and keeps the list closed', () => {
        const wrapper = mount(LanguageSelector);

        expect(toggle(wrapper).text()).toBe('English');
        expect(toggle(wrapper).get('img').attributes('src')).toContain('flags/gb.jpg');
        expect(toggle(wrapper).attributes('aria-expanded')).toBe('false');
        expect(options(wrapper)).toHaveLength(0);
        expect(wrapper.find('.fa-chevron-down').exists()).toBe(true);
    });

    it('opens on click and lists the other languages', async () => {
        const wrapper = mount(LanguageSelector);

        await toggle(wrapper).trigger('click');

        expect(toggle(wrapper).attributes('aria-expanded')).toBe('true');
        expect(options(wrapper).map((li) => li.text())).toEqual(['Dutch', 'German', 'French']);
        expect(wrapper.get('ul').classes()).toContain('right-0');
    });

    it('switches the i18n locale and closes', async () => {
        const wrapper = mount(LanguageSelector);
        await toggle(wrapper).trigger('click');

        await options(wrapper)[0].trigger('click');

        expect(i18n.global.locale.value).toBe('nl');
        expect(toggle(wrapper).text()).toBe('Dutch');
        expect(options(wrapper)).toHaveLength(0);

        await toggle(wrapper).trigger('click');
        expect(options(wrapper).map((li) => li.text())).toEqual(['English', 'German', 'French']);
    });

    it('toggles with Enter and Space', async () => {
        const wrapper = mount(LanguageSelector);

        await toggle(wrapper).trigger('keydown', { key: 'Enter' });
        expect(toggle(wrapper).attributes('aria-expanded')).toBe('true');

        await toggle(wrapper).trigger('keydown', { key: ' ' });
        expect(toggle(wrapper).attributes('aria-expanded')).toBe('false');
    });

    it('opens upwards when dropUp is set', async () => {
        const wrapper = mount(LanguageSelector, { props: { dropUp: true } });

        expect(wrapper.find('.fa-chevron-up').exists()).toBe(true);
        await toggle(wrapper).trigger('click');
        expect(wrapper.get('ul').classes()).toContain('bottom-full');
    });

    it('closes when clicking outside the list', async () => {
        const wrapper = mount(LanguageSelector, { attachTo: document.body });
        await toggle(wrapper).trigger('click');
        expect(options(wrapper)).toHaveLength(3);
        // onClickOutside ignores a second click within the same tick
        await new Promise((resolve) => setTimeout(resolve, 0));

        document.body.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }));
        document.body.dispatchEvent(new PointerEvent('click', { bubbles: true }));
        await wrapper.vm.$nextTick();

        expect(toggle(wrapper).attributes('aria-expanded')).toBe('false');
        expect(options(wrapper)).toHaveLength(0);
        wrapper.unmount();
    });
});
