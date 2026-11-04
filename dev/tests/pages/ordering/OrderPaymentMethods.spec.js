import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ref, nextTick } from 'vue';
import OrderPaymentMethods from '@/pages/OrderPaymentMethods.vue';
import { useCountries } from '@/lib/countries';
import { mountApp, flushPromises } from '../../helpers';
import { respond, fail, callsTo, resetApi } from '../../mocks/api';
import { enableAutoUnmount } from '@vue/test-utils';

vi.mock('@/lib/api', () => import('../../mocks/api'));

const toastr = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn() }));
vi.mock('toastr', () => ({ default: toastr }));

// Lets a test hold back the ordering request of a country, to answer requests out of order.
const heldOrderings = vi.hoisted(() => ({}));
vi.mock('@/lib/orderingsService', async (importOriginal) => {
    const real = await importOriginal();

    return {
        useOrderingsService: () => {
            const service = real.useOrderingsService();
            const getOrdering = (country) => (heldOrderings[country]
                ? heldOrderings[country].then(() => service.getOrdering(country))
                : service.getOrdering(country));

            return { ...service, getOrdering };
        },
    };
});

// vuedraggable relies on SortableJS, which does not drag in happy-dom. The stub renders the
// item slot for each list entry like the real component, and lets tests emit `end`.
const DraggableStub = {
    name: 'draggable',
    props: ['list', 'itemKey'],
    emits: ['end'],
    template: `<div data-draggable><template v-for="(element, index) in list" :key="index"><slot name="item" :element="element" :index="index"/></template></div>`,
};

const countries = [
    { id: 1, name: 'Netherlands', iso_code_2: 'NL', icon: 'nl.jpg' },
    { id: 2, name: 'Belgium', iso_code_2: 'BE', icon: 'be.jpg' },
    { id: 3, name: 'Germany', iso_code_2: 'DE', icon: 'de.jpg' },
];

const method = (id, name, icon = `${name}.svg`) => ({ id, name, label: name, icon });

const orderingsByCountry = {
    '': { country_id: null, value: [method(1, 'ideal'), method(2, 'paypal'), method(3, 'bancontact')], status: true },
    NL: { country_id: 1, value: [method(2, 'paypal'), method(1, 'ideal')], status: true },
    BE: { country_id: 2, value: [method(3, 'bancontact', null), method(2, 'paypal')], status: true },
};

const respondOrderings = () => {
    respond(
        'buckaroo_config_orderings',
        (m, payload, query) => ({ status: true, orderings: orderingsByCountry[query.country] }),
        'get',
    );
};

const mountPage = async (options = {}) => {
    respond('buckaroo_config_countries', { status: true, countries }, 'get');
    respondOrderings();
    const hasUnsavedChanges = options.hasUnsavedChanges ?? ref(false);
    const wrapper = mountApp(OrderPaymentMethods, {
        attachTo: options.attachTo,
        provide: { hasUnsavedChanges },
        global: { stubs: { draggable: DraggableStub } },
    });
    await flushPromises();

    return { wrapper, hasUnsavedChanges };
};

const rowNames = (wrapper) => wrapper.findAll('[data-order-row]').map((row) => row.find('.flex-1').text());
const countryButton = (wrapper, name) => wrapper.findAll('li > button').find((b) => b.text() === name);
const saveButton = (wrapper) => wrapper.findAll('button').find((b) => b.text() === 'Save');
const heading = (wrapper) => wrapper.get('[role="heading"]').text();

let confirmSpy;


beforeEach(() => {
    toastr.success.mockReset();
    toastr.error.mockReset();
    confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true);
});

enableAutoUnmount(afterEach);

afterEach(() => {
    // countries.ts keeps its refs at module level; reset them for the next test. Clear the fake
    // API first so the request useCountries() fires cannot refill the list.
    resetApi();
    Object.keys(heldOrderings).forEach((country) => delete heldOrderings[country]);
    const shared = useCountries();
    shared.countries.value = [];
    shared.query.value = null;
    document.body.innerHTML = '';
});

describe('OrderPaymentMethods', () => {
    describe('loading', () => {
        it('loads the countries and the global ordering on mount', async () => {
            const { wrapper } = await mountPage();

            expect(callsTo('buckaroo_config_countries', 'get')).toHaveLength(1);
            expect(callsTo('buckaroo_config_orderings', 'get').map((c) => c.query)).toEqual([{ country: '' }]);
            expect(rowNames(wrapper)).toEqual(['iDEAL | Wero', 'PayPal', 'Bancontact']);
            expect(heading(wrapper)).toBe('All Countries');
        });

        it('lists the countries after the "All Countries" entry, with flags', async () => {
            const { wrapper } = await mountPage();
            const buttons = wrapper.findAll('li > button');

            expect(buttons.map((b) => b.text())).toEqual(['All Countries', 'Netherlands', 'Belgium', 'Germany']);
            expect(buttons[1].find('img').attributes('src')).toBe('/img/flags/nl.jpg');
        });

        it('shows the payment method icons, and none when a method has no icon', async () => {
            const { wrapper } = await mountPage();
            const firstRow = wrapper.get('[data-order-row="0"]');

            expect(firstRow.get('img').attributes('src')).toBe('/modules/buckaroo3/views/img/buckaroo/Payment methods/SVG/ideal.svg');

            await countryButton(wrapper, 'Belgium').trigger('click');
            await flushPromises();
            expect(wrapper.get('[data-order-row="0"]').find('img').exists()).toBe(false);
        });

        it('renders no list when the orderings request reports a failure', async () => {
            respond('buckaroo_config_countries', { status: true, countries }, 'get');
            respond('buckaroo_config_orderings', { status: false }, 'get');
            const wrapper = mountApp(OrderPaymentMethods, { global: { stubs: { draggable: DraggableStub } } });
            await flushPromises();

            expect(wrapper.find('[data-draggable]').exists()).toBe(false);
        });

        it('keeps the country list empty when the countries request is refused', async () => {
            respond('buckaroo_config_countries', { status: false }, 'get');
            respondOrderings();
            const wrapper = mountApp(OrderPaymentMethods, { global: { stubs: { draggable: DraggableStub } } });
            await flushPromises();

            expect(wrapper.findAll('li > button').map((b) => b.text())).toEqual(['All Countries']);
        });

        it('keeps the country list empty when the countries request fails', async () => {
            fail('buckaroo_config_countries', new Error('Network down'), 'get');
            respondOrderings();
            const wrapper = mountApp(OrderPaymentMethods, { global: { stubs: { draggable: DraggableStub } } });
            await flushPromises();

            expect(wrapper.findAll('li > button').map((b) => b.text())).toEqual(['All Countries']);
            expect(rowNames(wrapper)).toHaveLength(3);
        });
    });

    describe('country search', () => {
        it('filters the countries case-insensitively and restores them when cleared', async () => {
            const { wrapper } = await mountPage();
            const search = wrapper.get('input[type="search"]');

            expect(search.attributes('aria-label')).toBe(search.attributes('placeholder'));

            await search.setValue('BEL');
            expect(wrapper.findAll('li > button').map((b) => b.text())).toEqual(['All Countries', 'Belgium']);

            await search.setValue('   ');
            expect(wrapper.findAll('li > button')).toHaveLength(4);
        });
    });

    describe('country switching', () => {
        it('loads the selected country ordering and marks the country as pressed', async () => {
            const { wrapper } = await mountPage();

            await countryButton(wrapper, 'Netherlands').trigger('click');
            await flushPromises();

            expect(callsTo('buckaroo_config_orderings', 'get').at(-1).query).toEqual({ country: 'NL' });
            expect(rowNames(wrapper)).toEqual(['PayPal', 'iDEAL | Wero']);
            expect(heading(wrapper)).toBe('Netherlands');
            expect(wrapper.get('[role="heading"] img').attributes('src')).toBe('/img/flags/nl.jpg');
            expect(countryButton(wrapper, 'Netherlands').attributes('aria-pressed')).toBe('true');
            expect(countryButton(wrapper, 'Netherlands').classes()).toContain('font-semibold');
            expect(countryButton(wrapper, 'All Countries').attributes('aria-pressed')).toBe('false');
            expect(countryButton(wrapper, 'Belgium').attributes('aria-pressed')).toBe('false');
        });

        it('goes back to the global ordering with "All Countries"', async () => {
            const { wrapper } = await mountPage();
            await countryButton(wrapper, 'Netherlands').trigger('click');
            await flushPromises();

            await countryButton(wrapper, 'All Countries').trigger('click');
            await flushPromises();

            expect(callsTo('buckaroo_config_orderings', 'get').at(-1).query).toEqual({ country: '' });
            expect(heading(wrapper)).toBe('All Countries');
            expect(countryButton(wrapper, 'All Countries').attributes('aria-pressed')).toBe('true');
        });

        it('does not reload when the selected country is clicked again', async () => {
            const { wrapper } = await mountPage();
            await countryButton(wrapper, 'All Countries').trigger('click');
            await countryButton(wrapper, 'Netherlands').trigger('click');
            await flushPromises();
            await countryButton(wrapper, 'Netherlands').trigger('click');
            await flushPromises();

            expect(callsTo('buckaroo_config_orderings', 'get').map((c) => c.query.country)).toEqual(['', 'NL']);
        });

        it('switches without asking when there are no unsaved changes', async () => {
            const { wrapper } = await mountPage();

            await countryButton(wrapper, 'Germany').trigger('click');

            expect(confirmSpy).not.toHaveBeenCalled();
        });

        it('asks before discarding unsaved changes and switches when confirmed', async () => {
            const { wrapper, hasUnsavedChanges } = await mountPage();
            await wrapper.get('[data-order-row="0"] [data-move="down"]').trigger('click');
            expect(hasUnsavedChanges.value).toBe(true);

            await countryButton(wrapper, 'Netherlands').trigger('click');
            await flushPromises();

            expect(confirmSpy).toHaveBeenCalledWith('You have unsaved changes. Leave without saving?');
            expect(hasUnsavedChanges.value).toBe(false);
            expect(heading(wrapper)).toBe('Netherlands');
            expect(rowNames(wrapper)).toEqual(['PayPal', 'iDEAL | Wero']);
        });

        it('stays on the current country and keeps the changes when the user cancels', async () => {
            confirmSpy.mockReturnValue(false);
            const { wrapper, hasUnsavedChanges } = await mountPage();
            await wrapper.get('[data-order-row="0"] [data-move="down"]').trigger('click');

            await countryButton(wrapper, 'Netherlands').trigger('click');
            await flushPromises();

            expect(confirmSpy).toHaveBeenCalled();
            expect(hasUnsavedChanges.value).toBe(true);
            expect(heading(wrapper)).toBe('All Countries');
            expect(rowNames(wrapper)).toEqual(['PayPal', 'iDEAL | Wero', 'Bancontact']);
            expect(callsTo('buckaroo_config_orderings', 'get')).toHaveLength(1);
        });
    });

    describe('loading failures', () => {
        it.each([
            ['reports a failure', () => respond('buckaroo_config_orderings', { status: false }, 'get')],
            ['fails', () => fail('buckaroo_config_orderings', new Error('Network down'), 'get')],
        ])('clears the previous list and shows an error when the country request %s', async (_, breakOrderings) => {
            const { wrapper } = await mountPage();
            expect(rowNames(wrapper)).toHaveLength(3);

            breakOrderings();
            await countryButton(wrapper, 'Netherlands').trigger('click');
            await flushPromises();

            expect(heading(wrapper)).toBe('Netherlands');
            expect(wrapper.find('[data-draggable]').exists()).toBe(false);
            expect(toastr.error).toHaveBeenCalledWith('Something went wrong.');
        });

        it('does not save the previous country\'s ordering after a failed load', async () => {
            const { wrapper } = await mountPage();
            respond('buckaroo_config_orderings', { status: false }, 'get');
            await countryButton(wrapper, 'Netherlands').trigger('click');
            await flushPromises();

            wrapper.vm.update();
            await flushPromises();

            expect(callsTo('buckaroo_config_orderings', 'post')).toHaveLength(0);
            expect(saveButton(wrapper).attributes('disabled')).toBeDefined();
        });

        it('only shows the ordering of the country selected last when responses arrive out of order', async () => {
            const { wrapper } = await mountPage();
            let releaseNl;
            heldOrderings.NL = new Promise((resolve) => { releaseNl = resolve; });

            await countryButton(wrapper, 'Netherlands').trigger('click');
            await flushPromises();
            expect(wrapper.find('[data-draggable]').exists()).toBe(false);

            await countryButton(wrapper, 'Belgium').trigger('click');
            await flushPromises();
            expect(rowNames(wrapper)).toEqual(['Bancontact', 'PayPal']);

            releaseNl();
            await flushPromises();

            expect(heading(wrapper)).toBe('Belgium');
            expect(rowNames(wrapper)).toEqual(['Bancontact', 'PayPal']);
            expect(toastr.error).not.toHaveBeenCalled();
        });

        it('ignores a failure of a request for a country that is no longer selected', async () => {
            const { wrapper } = await mountPage();
            let failNl;
            heldOrderings.NL = new Promise((resolve, reject) => { failNl = reject; });

            await countryButton(wrapper, 'Netherlands').trigger('click');
            await countryButton(wrapper, 'Belgium').trigger('click');
            await flushPromises();

            failNl(new Error('Network down'));
            await flushPromises();

            expect(rowNames(wrapper)).toEqual(['Bancontact', 'PayPal']);
            expect(toastr.error).not.toHaveBeenCalled();
        });

        it('shows an error and keeps the unsaved state when the save request fails', async () => {
            fail('buckaroo_config_orderings', new Error('Network down'), 'post');
            const { wrapper, hasUnsavedChanges } = await mountPage();
            await wrapper.get('[data-order-row="0"] [data-move="down"]').trigger('click');

            await saveButton(wrapper).trigger('click');
            await flushPromises();

            expect(hasUnsavedChanges.value).toBe(true);
            expect(toastr.error).toHaveBeenCalledWith('Something went wrong.');
            expect(toastr.success).not.toHaveBeenCalled();
        });
    });

    describe('reordering', () => {
        it('disables "up" on the first row and "down" on the last row', async () => {
            const { wrapper } = await mountPage();
            const rows = wrapper.findAll('[data-order-row]');

            expect(rows[0].get('[data-move="up"]').attributes('disabled')).toBeDefined();
            expect(rows[0].get('[data-move="down"]').attributes('disabled')).toBeUndefined();
            expect(rows[1].get('[data-move="up"]').attributes('disabled')).toBeUndefined();
            expect(rows[2].get('[data-move="down"]').attributes('disabled')).toBeDefined();
        });

        it('labels the move buttons with the payment method name', async () => {
            const { wrapper } = await mountPage();
            const row = wrapper.get('[data-order-row="1"]');

            expect(row.get('[data-move="up"]').attributes('aria-label')).toBe('Move up: PayPal');
            expect(row.get('[data-move="down"]').attributes('aria-label')).toBe('Move down: PayPal');
            expect(row.get('[data-move="up"]').attributes('title')).toBe('Move up');
        });

        it('moves a method down and up and marks the page as changed', async () => {
            const { wrapper, hasUnsavedChanges } = await mountPage();
            expect(saveButton(wrapper).attributes('disabled')).toBeDefined();
            expect(wrapper.find('[role="status"]').exists()).toBe(false);

            await wrapper.get('[data-order-row="0"] [data-move="down"]').trigger('click');
            expect(rowNames(wrapper)).toEqual(['PayPal', 'iDEAL | Wero', 'Bancontact']);
            expect(hasUnsavedChanges.value).toBe(true);
            expect(saveButton(wrapper).attributes('disabled')).toBeUndefined();
            expect(wrapper.get('[role="status"]').text()).toBe('You have unsaved changes');

            await wrapper.get('[data-order-row="2"] [data-move="up"]').trigger('click');
            expect(rowNames(wrapper)).toEqual(['PayPal', 'Bancontact', 'iDEAL | Wero']);
        });

        it('ignores moves past either end of the list', async () => {
            const { wrapper, hasUnsavedChanges } = await mountPage();
            const vm = wrapper.vm;

            vm.moveItem(0, -1);
            vm.moveItem(2, 1);
            await nextTick();

            expect(rowNames(wrapper)).toEqual(['iDEAL | Wero', 'PayPal', 'Bancontact']);
            expect(hasUnsavedChanges.value).toBe(false);
        });

        it('ignores moves while no ordering is loaded', async () => {
            respond('buckaroo_config_countries', { status: true, countries }, 'get');
            respond('buckaroo_config_orderings', { status: false }, 'get');
            const hasUnsavedChanges = ref(false);
            const wrapper = mountApp(OrderPaymentMethods, { provide: { hasUnsavedChanges }, global: { stubs: { draggable: DraggableStub } } });
            await flushPromises();

            wrapper.vm.moveItem(0, 1);

            expect(hasUnsavedChanges.value).toBe(false);
        });

        it('keeps keyboard focus on the moved row', async () => {
            const host = document.createElement('div');
            host.id = 'app';
            const { wrapper } = await mountPage({ attachTo: host });
            // Drop the help video before attaching, or happy-dom tries to load YouTube.
            host.querySelector('iframe').remove();
            document.body.appendChild(host);

            await wrapper.get('[data-order-row="0"] [data-move="down"]').trigger('click');
            await nextTick();
            expect(document.activeElement).toBe(wrapper.get('[data-order-row="1"] [data-move="down"]').element);

            // Moving into the last slot: "down" is disabled there, so focus goes to "up".
            await wrapper.get('[data-order-row="1"] [data-move="down"]').trigger('click');
            await nextTick();
            expect(document.activeElement).toBe(wrapper.get('[data-order-row="2"] [data-move="up"]').element);

            await wrapper.get('[data-order-row="2"] [data-move="up"]').trigger('click');
            await nextTick();
            expect(document.activeElement).toBe(wrapper.get('[data-order-row="1"] [data-move="up"]').element);

            // Moving into the first slot: "up" is disabled there, so focus goes to "down".
            await wrapper.get('[data-order-row="1"] [data-move="up"]').trigger('click');
            await nextTick();
            expect(document.activeElement).toBe(wrapper.get('[data-order-row="0"] [data-move="down"]').element);
        });

        it('does not fail when the moved row is not inside #app', async () => {
            const { wrapper, hasUnsavedChanges } = await mountPage();

            await wrapper.get('[data-order-row="0"] [data-move="down"]').trigger('click');
            await nextTick();

            expect(hasUnsavedChanges.value).toBe(true);
        });

        it('passes the ordering list to draggable and marks changes when a drag ends', async () => {
            const { wrapper, hasUnsavedChanges } = await mountPage();
            const drag = wrapper.findComponent(DraggableStub);

            expect(drag.props('list').map((m) => m.name)).toEqual(['ideal', 'paypal', 'bancontact']);
            // Each ordering entry is keyed by its payment method id (entries have no "order" field).
            expect(drag.props('itemKey')).toBe('id');

            // Sortable mutates the :list array in place, then emits `end`.
            const list = drag.props('list');
            list.unshift(list.pop());
            drag.vm.$emit('end');
            await nextTick();

            expect(hasUnsavedChanges.value).toBe(true);
            expect(rowNames(wrapper)).toEqual(['Bancontact', 'iDEAL | Wero', 'PayPal']);
        });
    });

    describe('saving', () => {
        it('posts the reordered ordering with its country and clears the unsaved state', async () => {
            respond('buckaroo_config_orderings', { status: true }, 'post');
            const { wrapper, hasUnsavedChanges } = await mountPage();
            await countryButton(wrapper, 'Netherlands').trigger('click');
            await flushPromises();
            await wrapper.get('[data-order-row="0"] [data-move="down"]').trigger('click');

            await saveButton(wrapper).trigger('click');
            await flushPromises();

            const [call] = callsTo('buckaroo_config_orderings', 'post');
            expect(call.payload).toEqual({
                country_id: 1,
                value: [method(1, 'ideal'), method(2, 'paypal')],
                status: true,
            });
            expect(hasUnsavedChanges.value).toBe(false);
            expect(toastr.success).toHaveBeenCalledWith('Payment method order updated successfully.');
            expect(toastr.error).not.toHaveBeenCalled();
            expect(saveButton(wrapper).attributes('disabled')).toBeDefined();
        });

        it('posts the global ordering with a null country', async () => {
            respond('buckaroo_config_orderings', { status: true }, 'post');
            const { wrapper } = await mountPage();
            await wrapper.get('[data-order-row="2"] [data-move="up"]').trigger('click');

            await saveButton(wrapper).trigger('click');
            await flushPromises();

            const payload = callsTo('buckaroo_config_orderings', 'post')[0].payload;
            expect(payload.country_id).toBeNull();
            expect(payload.value.map((m) => m.id)).toEqual([1, 3, 2]);
        });

        it('keeps the unsaved state and shows an error when the save is rejected', async () => {
            respond('buckaroo_config_orderings', { status: false, message: 'Missing or invalid data' }, 'post');
            const { wrapper, hasUnsavedChanges } = await mountPage();
            await wrapper.get('[data-order-row="0"] [data-move="down"]').trigger('click');

            await saveButton(wrapper).trigger('click');
            await flushPromises();

            expect(hasUnsavedChanges.value).toBe(true);
            expect(toastr.error).toHaveBeenCalledWith('Something went wrong.');
            expect(toastr.success).not.toHaveBeenCalled();
        });
    });

    describe('leaving the page', () => {
        const beforeUnload = () => {
            const event = new Event('beforeunload', { cancelable: true });
            window.dispatchEvent(event);

            return event;
        };

        it('warns before unloading with unsaved changes only', async () => {
            const { wrapper } = await mountPage();

            expect(beforeUnload().defaultPrevented).toBe(false);

            await wrapper.get('[data-order-row="0"] [data-move="down"]').trigger('click');
            expect(beforeUnload().defaultPrevented).toBe(true);
            wrapper.unmount();
        });

        it('removes the unload warning and resets the unsaved flag on unmount', async () => {
            const { wrapper, hasUnsavedChanges } = await mountPage();
            await wrapper.get('[data-order-row="0"] [data-move="down"]').trigger('click');

            wrapper.unmount();

            expect(hasUnsavedChanges.value).toBe(false);
            hasUnsavedChanges.value = true;
            expect(beforeUnload().defaultPrevented).toBe(false);
        });

        it('falls back to a local unsaved flag when none is provided', async () => {
            respond('buckaroo_config_countries', { status: true, countries }, 'get');
            respondOrderings();
            const { mount } = await import('@vue/test-utils');
            const wrapper = mount(OrderPaymentMethods, { global: { stubs: { draggable: DraggableStub } } });
            await flushPromises();

            await wrapper.get('[data-order-row="0"] [data-move="down"]').trigger('click');

            expect(saveButton(wrapper).attributes('disabled')).toBeUndefined();
            expect(wrapper.find('[role="status"]').exists()).toBe(true);
        });
    });

    it('renders and reorders through the real vuedraggable component', async () => {
        respond('buckaroo_config_countries', { status: true, countries }, 'get');
        respondOrderings();
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
        const wrapper = mountApp(OrderPaymentMethods, { provide: { hasUnsavedChanges: ref(false) } });
        await flushPromises();

        expect(rowNames(wrapper)).toEqual(['iDEAL | Wero', 'PayPal', 'Bancontact']);
        await wrapper.get('[data-order-row="0"] [data-move="down"]').trigger('click');
        expect(rowNames(wrapper)).toEqual(['PayPal', 'iDEAL | Wero', 'Bancontact']);
        expect(warn.mock.calls.map((c) => String(c[0]))).toEqual([]);
    });

    it('renders the help panel with the numbered steps', async () => {
        const { wrapper } = await mountPage();

        expect(wrapper.get('aside').attributes('aria-label')).toBe('How to reorder payment methods');
        expect(wrapper.findAll('aside ol li')).toHaveLength(3);
    });
});
