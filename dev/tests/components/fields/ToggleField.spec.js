import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import ToggleField from '@/components/fields/ToggleField.vue';

const mountToggle = (props = {}, slot = '<span>Enable client side</span>') =>
    mount(ToggleField, { props: { id: 'client_side', ...props }, slots: { default: slot } });

describe('ToggleField', () => {
    it('renders the slot next to a labelled checkbox', () => {
        const wrapper = mountToggle({ modelValue: false });
        const input = wrapper.get('input[type="checkbox"]');

        expect(wrapper.text()).toContain('Enable client side');
        expect(input.attributes('id')).toBe('client_side');
        expect(wrapper.get('label').attributes('for')).toBe('client_side');
    });

    it('reflects the initial modelValue', () => {
        expect(mountToggle({ modelValue: true }).get('input').element.checked).toBe(true);
        expect(mountToggle({ modelValue: false }).get('input').element.checked).toBe(false);
    });

    it('emits update:modelValue when toggled', async () => {
        const wrapper = mountToggle({ modelValue: false });

        await wrapper.get('input').setValue(true);
        await wrapper.get('input').setValue(false);

        expect(wrapper.emitted('update:modelValue')).toEqual([[true], [false]]);
    });

    it('follows a new modelValue from the parent without echoing it back unchanged', async () => {
        const wrapper = mountToggle({ modelValue: false });

        await wrapper.setProps({ modelValue: true });

        expect(wrapper.get('input').element.checked).toBe(true);
        // The internal value changed, so the watcher re-emits the same value once.
        expect(wrapper.emitted('update:modelValue')).toEqual([[true]]);
    });
});
