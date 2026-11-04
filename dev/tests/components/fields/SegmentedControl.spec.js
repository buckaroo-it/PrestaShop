import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import SegmentedControl from '@/components/fields/SegmentedControl.vue';

const options = [
    { value: 'radio', label: 'Radio' },
    { value: 'dropdown', label: 'Dropdown' },
];

describe('SegmentedControl', () => {
    it('renders one button per option inside a labelled group', () => {
        const wrapper = mount(SegmentedControl, { props: { modelValue: 'radio', options, label: 'Button Display Type' } });

        expect(wrapper.get('[role="group"]').attributes('aria-label')).toBe('Button Display Type');
        expect(wrapper.findAll('button').map((b) => b.text())).toEqual(['Radio', 'Dropdown']);
    });

    it('marks only the selected option as pressed, highlighted and checked', () => {
        const wrapper = mount(SegmentedControl, { props: { modelValue: 'dropdown', options } });
        const [radio, dropdown] = wrapper.findAll('button');

        expect(radio.attributes('aria-pressed')).toBe('false');
        expect(radio.classes()).toContain('text-gray-500');
        expect(radio.find('.fa-check').exists()).toBe(false);

        expect(dropdown.attributes('aria-pressed')).toBe('true');
        expect(dropdown.classes()).toEqual(expect.arrayContaining(['bg-primary', 'text-fifthly']));
        expect(dropdown.find('.fa-check').exists()).toBe(true);
    });

    it('emits update:modelValue with the clicked option value', async () => {
        const wrapper = mount(SegmentedControl, { props: { modelValue: 'radio', options } });

        await wrapper.findAll('button')[1].trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([['dropdown']]);
    });

    it('works with v-model', async () => {
        const wrapper = mount(SegmentedControl, {
            props: {
                modelValue: 'radio',
                options,
                'onUpdate:modelValue': (value) => wrapper.setProps({ modelValue: value }),
            },
        });

        await wrapper.findAll('button')[1].trigger('click');

        expect(wrapper.findAll('button')[1].attributes('aria-pressed')).toBe('true');
        expect(wrapper.findAll('button')[0].attributes('aria-pressed')).toBe('false');
    });

    it('uses button type="button" so it never submits a surrounding form', () => {
        const wrapper = mount(SegmentedControl, { props: { modelValue: 'radio', options } });

        wrapper.findAll('button').forEach((b) => expect(b.attributes('type')).toBe('button'));
    });
});
