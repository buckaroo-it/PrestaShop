/*
 * Local helpers for the per-method settings components.
 *
 * DefaultPaymentConfig provides `config` as a ref (provide('config', ref({...}))) and the
 * method components read and write `config.value.<key>`, so the tests provide a ref too.
 */
import { defineComponent, h, ref } from 'vue';
import { mountApp, flushPromises } from '../../helpers';

/** Mount a settings component with `config` = ref(initial). Returns { wrapper, config }. */
export const mountConfig = async (component, initial = {}, options = {}) => {
    const config = ref(initial);
    const wrapper = mountApp(component, { ...options, provide: { config, ...(options.provide || {}) } });
    await flushPromises();

    return { wrapper, config };
};

/** A v-model child stub that records its props and can emit update:modelValue. */
export const modelStub = (name, extraProps = []) => defineComponent({
    name,
    props: ['modelValue', ...extraProps],
    emits: ['update:modelValue'],
    setup(props) {
        return () => h('div', { 'data-stub': name });
    },
});
