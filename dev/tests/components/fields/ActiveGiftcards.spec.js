import { describe, expect, it, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import ActiveGiftcards from '@/components/fields/ActiveGiftcards.vue';
import GiftcardModal from '@/components/fields/GiftcardModal.vue';
import { respond, callsTo } from '../../mocks/api';

vi.mock('@/lib/api', () => import('../../mocks/api'));

const catalogue = {
    status: true,
    giftcards: [
        { id: 1, code: 'boekenbon', name: 'Boekenbon', logo: 'Boekenbon.svg', is_custom: false },
        { id: 2, code: 'vvvgiftcard', name: 'VVV Giftcard', logo: 'VVVgiftcard.svg', is_custom: false },
    ],
    custom_giftcards: [
        { id: 10, code: 'shopcard', name: 'Shop card', logo: 'https://cdn.test/shop.svg', is_custom: true },
        { id: 11, code: 'nologo', name: 'No logo card', logo: null, is_custom: true },
    ],
};

const mountGiftcards = async (modelValue, response = catalogue) => {
    respond('buckaroo_config_giftcards', response, 'get');
    const wrapper = mount(ActiveGiftcards, {
        props: { modelValue },
        global: { stubs: { Loading: true } },
    });
    await flushPromises();

    return wrapper;
};

const rows = (wrapper) => wrapper.findAll('li');
const names = (wrapper) => rows(wrapper).map((li) => li.get('span').text());
const row = (wrapper, name) => rows(wrapper).find((li) => li.get('span').text() === name);
const activeNames = (wrapper) => rows(wrapper).filter((li) => li.classes().includes('bg-primary')).map((li) => li.get('span').text());
const lastEmitted = (wrapper) => wrapper.emitted('update:modelValue').at(-1)[0];

describe('ActiveGiftcards', () => {
    it('loads the giftcards once and lists the standard cards before the custom ones', async () => {
        const wrapper = await mountGiftcards({ giftcards: [], customGiftcards: [] });

        expect(callsTo('buckaroo_config_giftcards', 'get')).toHaveLength(1);
        expect(names(wrapper)).toEqual(['Boekenbon', 'VVV Giftcard', 'Shop card', 'No logo card']);
    });

    it('shows bundled logos for standard cards and the stored logo for custom cards', async () => {
        const wrapper = await mountGiftcards({ giftcards: [], customGiftcards: [] });

        expect(row(wrapper, 'Boekenbon').get('img').attributes('src')).toBe('/modules/buckaroo3/views/img/buckaroo/Giftcards/SVG/Boekenbon.svg');
        expect(row(wrapper, 'Boekenbon').get('img').attributes('alt')).toBe('Boekenbon');
        expect(row(wrapper, 'Shop card').get('img').attributes('src')).toBe('https://cdn.test/shop.svg');
        expect(row(wrapper, 'No logo card').find('img').exists()).toBe(false);
    });

    it('only offers the edit button on custom cards', async () => {
        const wrapper = await mountGiftcards({ giftcards: [], customGiftcards: [] });

        expect(row(wrapper, 'Boekenbon').find('button').exists()).toBe(false);
        expect(row(wrapper, 'Shop card').find('button').exists()).toBe(true);
    });

    it('shows an empty list when the request reports a failure', async () => {
        const wrapper = await mountGiftcards({ giftcards: [] }, { status: false });

        expect(rows(wrapper)).toHaveLength(0);
    });

    it('highlights the active standard and custom cards', async () => {
        const wrapper = await mountGiftcards({ giftcards: [{ id: 2 }], customGiftcards: [{ id: 10 }] });

        expect(activeNames(wrapper)).toEqual(['VVV Giftcard', 'Shop card']);
        expect(row(wrapper, 'Boekenbon').classes()).toContain('hover:bg-gray-200');
        expect(row(wrapper, 'No logo card').classes()).toContain('hover:bg-gray-200');
    });

    it('activates a standard card and emits the selection', async () => {
        const wrapper = await mountGiftcards({ giftcards: [], customGiftcards: [] });

        await row(wrapper, 'Boekenbon').trigger('click');

        expect(activeNames(wrapper)).toEqual(['Boekenbon']);
        const value = lastEmitted(wrapper);
        expect(value.giftcards.map((g) => g.id)).toEqual([1]);
        expect(value.customGiftcards).toEqual([]);
    });

    it('activates a custom card and emits the selection', async () => {
        const wrapper = await mountGiftcards({ giftcards: [{ id: 1 }], customGiftcards: [] });

        await row(wrapper, 'Shop card').trigger('click');

        const value = lastEmitted(wrapper);
        expect(value.customGiftcards.map((g) => g.id)).toEqual([10]);
        expect(value.giftcards.map((g) => g.id)).toEqual([1]);
    });

    it('deactivates an active standard card', async () => {
        const wrapper = await mountGiftcards({ giftcards: [{ id: 1 }, { id: 2 }], customGiftcards: [] });

        await row(wrapper, 'Boekenbon').trigger('click');

        expect(activeNames(wrapper)).toEqual(['VVV Giftcard']);
        expect(lastEmitted(wrapper).giftcards.map((g) => g.id)).toEqual([2]);
    });

    it('deactivates an active custom card', async () => {
        const wrapper = await mountGiftcards({ giftcards: [], customGiftcards: [{ id: 10 }, { id: 11 }] });

        await row(wrapper, 'Shop card').trigger('click');

        expect(activeNames(wrapper)).toEqual(['No logo card']);
        expect(lastEmitted(wrapper).customGiftcards.map((g) => g.id)).toEqual([11]);
    });

    it('starts a selection when no value was saved yet', async () => {
        const wrapper = await mountGiftcards(undefined);

        expect(activeNames(wrapper)).toEqual([]);
        await row(wrapper, 'VVV Giftcard').trigger('click');

        expect(lastEmitted(wrapper)).toMatchObject({ customGiftcards: [], giftcards: [{ id: 2 }] });
        expect(activeNames(wrapper)).toEqual(['VVV Giftcard']);
    });

    it('follows a new modelValue from the parent', async () => {
        const wrapper = await mountGiftcards({ giftcards: [], customGiftcards: [] });

        await wrapper.setProps({ modelValue: { giftcards: [{ id: 1 }], customGiftcards: [{ id: 11 }] } });

        expect(activeNames(wrapper)).toEqual(['Boekenbon', 'No logo card']);
    });

    it('filters standard and custom cards by name, case-insensitively', async () => {
        const wrapper = await mountGiftcards({ giftcards: [], customGiftcards: [] });
        const search = wrapper.get('#search-giftcard');

        await search.setValue('CARD');
        expect(names(wrapper)).toEqual(['VVV Giftcard', 'Shop card', 'No logo card']);

        await search.setValue('boek');
        expect(names(wrapper)).toEqual(['Boekenbon']);

        await search.setValue('  ');
        expect(names(wrapper)).toHaveLength(4);
    });

    it('opens the add modal and appends the created custom card to the list', async () => {
        const wrapper = await mountGiftcards({ giftcards: [], customGiftcards: [] });
        respond('buckaroo_config_giftcards', {
            status: true,
            custom_giftcard: { id: 20, code: 'newcard', name: 'New card', logo: null, is_custom: true },
        }, 'post');

        await wrapper.findAll('button').find((b) => b.text().includes('Add custom giftcard')).trigger('click');
        expect(wrapper.find('dialog').exists()).toBe(true);
        await wrapper.get('#giftcard_name').setValue('New card');
        await wrapper.get('#giftcard_service_code').setValue('newcard');
        await wrapper.get('dialog').findAll('button').find((b) => b.text() === 'Add').trigger('click');
        await flushPromises();

        expect(names(wrapper)).toEqual(['Boekenbon', 'VVV Giftcard', 'Shop card', 'No logo card', 'New card']);
        expect(row(wrapper, 'New card').find('button').exists()).toBe(true);
        expect(wrapper.find('dialog').exists()).toBe(false);
    });

    it('opens a custom card in the edit modal without toggling it', async () => {
        const wrapper = await mountGiftcards({ giftcards: [], customGiftcards: [] });

        await row(wrapper, 'Shop card').get('button').trigger('click');

        expect(wrapper.find('dialog').exists()).toBe(true);
        expect(wrapper.get('#giftcard_name').element.value).toBe('Shop card');
        expect(wrapper.get('#giftcard_service_code').element.value).toBe('shopcard');
        expect(activeNames(wrapper)).toEqual([]);
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('drops a removed custom card from the list', async () => {
        const wrapper = await mountGiftcards({ giftcards: [], customGiftcards: [] });
        respond('buckaroo_remove_giftcard', { status: true }, 'post');

        await row(wrapper, 'Shop card').get('button').trigger('click');
        await wrapper.get('dialog').findAll('button').find((b) => b.text() === 'Remove').trigger('click');
        await flushPromises();

        expect(callsTo('buckaroo_remove_giftcard', 'post')[0].payload).toMatchObject({ id: 10, service_code: 'shopcard' });
        expect(names(wrapper)).toEqual(['Boekenbon', 'VVV Giftcard', 'No logo card']);
    });

    it('links the search label to the search input', async () => {
        const wrapper = await mountGiftcards({ giftcards: [], customGiftcards: [] });

        expect(wrapper.get('label[for="search-giftcard"]').text()).toBeTruthy();
        expect(wrapper.find('#search-giftcard').exists()).toBe(true);
    });

    it('also drops a removed custom card from the active selection', async () => {
        const wrapper = await mountGiftcards({ giftcards: [{ id: 1 }], customGiftcards: [{ id: 10 }, { id: 11 }] });
        respond('buckaroo_remove_giftcard', { status: true }, 'post');

        await row(wrapper, 'Shop card').get('button').trigger('click');
        await wrapper.get('dialog').findAll('button').find((b) => b.text() === 'Remove').trigger('click');
        await flushPromises();

        expect(activeNames(wrapper)).toEqual(['Boekenbon', 'No logo card']);
        const value = lastEmitted(wrapper);
        expect(value.customGiftcards.map((g) => g.id)).toEqual([11]);
        expect(value.giftcards.map((g) => g.id)).toEqual([1]);
    });

    it('removes a custom card when nothing was selected yet', async () => {
        const wrapper = await mountGiftcards(undefined);
        const modal = wrapper.findComponent(GiftcardModal);

        modal.vm.$emit('removeCustomGiftcard', { id: 10 });
        await wrapper.vm.$nextTick();

        expect(names(wrapper)).not.toContain('Shop card');
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('shows a saved edit in the list and keeps the edits out of it when the modal is closed', async () => {
        const wrapper = await mountGiftcards({ giftcards: [], customGiftcards: [] });

        await row(wrapper, 'Shop card').get('button').trigger('click');
        await wrapper.get('#giftcard_name').setValue('Unsaved name');
        await wrapper.get('dialog svg').trigger('click');
        expect(names(wrapper)).toContain('Shop card');

        respond('buckaroo_edit_giftcard', {
            status: true,
            custom_giftcard: { id: 10, name: 'Saved name', service_code: 'shopcard', is_custom: true, logo_url: null },
        }, 'post');
        await row(wrapper, 'Shop card').get('button').trigger('click');
        await wrapper.get('#giftcard_name').setValue('Saved name');
        await wrapper.get('dialog').findAll('button').find((b) => b.text() === 'Edit').trigger('click');
        await flushPromises();

        expect(names(wrapper)).toEqual(['Boekenbon', 'VVV Giftcard', 'Saved name', 'No logo card']);
    });

    it('wires the modal events to the list', async () => {
        const wrapper = await mountGiftcards({ giftcards: [], customGiftcards: [] });
        const modal = wrapper.findComponent(GiftcardModal);

        modal.vm.$emit('appendCustomGiftcard', { id: 30, name: 'Emitted', isCustom: true });
        await wrapper.vm.$nextTick();
        expect(names(wrapper)).toContain('Emitted');

        modal.vm.$emit('removeCustomGiftcard', { id: 30 });
        await wrapper.vm.$nextTick();
        expect(names(wrapper)).not.toContain('Emitted');
    });
});
