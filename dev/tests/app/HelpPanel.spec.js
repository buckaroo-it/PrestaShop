import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import HelpPanel from '@/components/HelpPanel.vue';

const KEY = 'buckaroo3.helpPanelCollapsed';
const bodyShown = (wrapper) => !wrapper.get('.help-body').element.parentElement.style.display.includes('none');
const mountPanel = (props = {}) => mount(HelpPanel, {
    props: { title: 'Need help?', ...props },
    slots: { default: '<p class="help-body">Body</p>' },
});

describe('HelpPanel', () => {
    beforeEach(() => window.localStorage.removeItem(KEY));
    afterEach(() => {
        vi.unstubAllGlobals();
        window.localStorage.removeItem(KEY);
    });

    it('is open by default with the title, body and a hide button', () => {
        const wrapper = mountPanel();

        expect(wrapper.get('aside').attributes('aria-label')).toBe('Need help?');
        expect(wrapper.get('h2').text()).toBe('Need help?');
        expect(bodyShown(wrapper)).toBe(true);
        expect(wrapper.get('button').attributes('aria-expanded')).toBe('true');
        expect(wrapper.get('button').attributes('type')).toBe('button');
        expect(wrapper.get('button i').classes()).toContain('fa-times');
        expect(wrapper.get('aside').classes()).toContain('lg:w-1/5');
    });

    it('collapses on toggle, remembers it and opens again', async () => {
        const wrapper = mountPanel();

        await wrapper.get('button').trigger('click');
        expect(bodyShown(wrapper)).toBe(false);
        expect(wrapper.get('button').attributes('aria-expanded')).toBe('false');
        expect(wrapper.get('button i').classes()).toContain('fa-question-circle');
        expect(wrapper.get('h2').classes()).toContain('lg:hidden');
        expect(window.localStorage.getItem(KEY)).toBe('1');

        await wrapper.get('button').trigger('click');
        expect(bodyShown(wrapper)).toBe(true);
        expect(window.localStorage.getItem(KEY)).toBe('0');
    });

    it('starts collapsed when that was remembered', () => {
        window.localStorage.setItem(KEY, '1');
        const wrapper = mountPanel();

        expect(bodyShown(wrapper)).toBe(false);
        expect(wrapper.get('button').attributes('title')).toBe(wrapper.get('.sr-only').text());
    });

    it.each([['md', 'md:w-1/5'], ['lg', 'lg:w-1/5'], ['xl', 'xl:w-64'], ['2xl', '2xl:w-64'], ['bogus', 'lg:w-1/5']])(
        'uses the %s layout classes', (breakpoint, cls) => {
            expect(mountPanel({ breakpoint }).get('aside').classes()).toContain(cls);
        },
    );

    it('still works when localStorage throws', async () => {
        const denied = () => { throw new Error('denied'); };
        vi.stubGlobal('localStorage', { getItem: denied, setItem: denied, removeItem: () => {} });
        const wrapper = mountPanel();

        expect(bodyShown(wrapper)).toBe(true);
        await wrapper.get('button').trigger('click');
        expect(bodyShown(wrapper)).toBe(false);
    });
});
