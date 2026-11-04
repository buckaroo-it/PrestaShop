/*
 * Runs a composable inside a mounted component so inject()/lifecycle hooks work.
 */
import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';

export const withSetup = (composable, provide = {}) => {
    let result;
    const wrapper = mount(defineComponent({
        setup() {
            result = composable();
            return () => h('div');
        },
    }), { global: { provide } });

    return { result, wrapper };
};
