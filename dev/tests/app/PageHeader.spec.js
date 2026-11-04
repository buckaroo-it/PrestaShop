import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import PageHeader from '@/components/PageHeader.vue';

describe('PageHeader', () => {
    it('renders only the title by default', () => {
        const wrapper = mount(PageHeader, { props: { title: 'Settings' } });

        expect(wrapper.get('h1').text()).toBe('Settings');
        expect(wrapper.find('.text-gray-400').exists()).toBe(false);
        expect(wrapper.element.children).toHaveLength(1);
    });

    it('renders the subtitle and the actions slot', () => {
        const wrapper = mount(PageHeader, {
            props: { title: 'Settings', subtitle: 'General configuration' },
            slots: { actions: '<button class="save">Save</button>' },
        });

        expect(wrapper.get('.text-gray-400').text()).toBe('General configuration');
        expect(wrapper.get('button.save').text()).toBe('Save');
    });
});
