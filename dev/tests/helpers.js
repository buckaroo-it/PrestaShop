/*
 * Mount helpers for the admin app components.
 */
import { mount, flushPromises } from '@vue/test-utils';
import { reactive, ref } from 'vue';

export { flushPromises };

/**
 * The values Dashboard.vue provides to every page, with test defaults.
 * Pass overrides (e.g. { config: reactive({...}) }) to change one.
 */
export const defaultProvides = (overrides = {}) => ({
    csrfToken: 'test-token',
    adminUrl: 'https://shop.test/admin-dev/',
    baseUrl: 'https://shop.test',
    view: ref('settings'),
    hasUnsavedChanges: ref(false),
    initialRouteDetail: ref(null),
    config: reactive({}),
    ...overrides,
});

/** mount() with the app's provides; options.provide entries override the defaults. */
export const mountApp = (component, options = {}) => {
    const { provide = {}, global = {}, ...rest } = options;

    return mount(component, {
        ...rest,
        global: {
            ...global,
            provide: { ...defaultProvides(provide), ...(global.provide || {}) },
        },
    });
};

/** mount + wait for the component's initial API calls to resolve. */
export const mountAndSettle = async (component, options = {}) => {
    const wrapper = mountApp(component, options);
    await flushPromises();

    return wrapper;
};
