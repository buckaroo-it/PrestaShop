import { describe, expect, it, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import GiftcardModal from '@/components/fields/GiftcardModal.vue';
import { respond, callsTo } from '../../mocks/api';

vi.mock('@/lib/api', () => import('../../mocks/api'));

const mountModal = () => mount(GiftcardModal, { global: { stubs: { Loading: { template: '<div data-loading/>' } } } });

const fill = async (wrapper, { name, code, logo }) => {
    if (name !== undefined) await wrapper.get('#giftcard_name').setValue(name);
    if (code !== undefined) await wrapper.get('#giftcard_service_code').setValue(code);
    if (logo !== undefined) await wrapper.get('#custom_giftcard_url').setValue(logo);
};

const button = (wrapper, text) => wrapper.findAll('button').find((b) => b.text() === text);
const title = (wrapper) => wrapper.get('h4').text().replace(/\s+/g, ' ');

const customCard = () => ({
    id: 7,
    name: 'Shop card',
    code: 'shopcard',
    service_code: 'shopcard',
    logo: 'https://cdn.test/shop.svg',
    logo_url: 'https://cdn.test/shop.svg',
    isCustom: true,
});

describe('GiftcardModal', () => {
    it('is hidden until opened', () => {
        expect(mountModal().find('dialog').exists()).toBe(false);
    });

    describe('adding', () => {
        it('opens an empty "Add" form', async () => {
            const wrapper = mountModal();

            wrapper.vm.add();
            await flushPromises();

            expect(wrapper.find('dialog').exists()).toBe(true);
            expect(title(wrapper)).toBe('Add Custom giftcard');
            expect(wrapper.get('#giftcard_name').element.value).toBe('');
            expect(wrapper.get('#giftcard_service_code').element.value).toBe('');
            expect(wrapper.get('#custom_giftcard_url').element.value).toBe('');
            expect(wrapper.get('label[for="giftcard_name"]').text()).toBeTruthy();
            expect(button(wrapper, 'Add')).toBeDefined();
            expect(button(wrapper, 'Remove')).toBeUndefined();
        });

        it.each([
            ['the name', { code: 'shopcard' }],
            ['the service code', { name: 'Shop card' }],
            ['both fields', {}],
        ])('does not post without %s', async (_, values) => {
            const wrapper = mountModal();
            wrapper.vm.add();
            await flushPromises();
            await fill(wrapper, values);

            await button(wrapper, 'Add').trigger('click');
            await flushPromises();

            expect(callsTo('buckaroo_config_giftcards')).toHaveLength(0);
            expect(wrapper.find('dialog').exists()).toBe(true);
        });

        it('posts the form, emits the created giftcard normalised and closes', async () => {
            respond('buckaroo_config_giftcards', {
                status: true,
                custom_giftcard: { id: 12, code: 'shopcard', name: 'Shop card', logo: 'https://cdn.test/shop.svg', is_custom: true },
            }, 'post');
            const wrapper = mountModal();
            wrapper.vm.add();
            await flushPromises();
            await fill(wrapper, { name: 'Shop card', code: 'shopcard', logo: 'https://cdn.test/shop.svg' });

            await button(wrapper, 'Add').trigger('click');
            await flushPromises();

            expect(callsTo('buckaroo_config_giftcards', 'post')[0].payload).toEqual({
                name: 'Shop card',
                service_code: 'shopcard',
                logo_url: 'https://cdn.test/shop.svg',
            });
            expect(wrapper.emitted('appendCustomGiftcard')[0][0]).toEqual({
                id: 12,
                name: 'Shop card',
                code: 'shopcard',
                service_code: 'shopcard',
                logo: 'https://cdn.test/shop.svg',
                logo_url: 'https://cdn.test/shop.svg',
                is_custom: true,
                isCustom: true,
            });
            expect(wrapper.find('dialog').exists()).toBe(false);
        });

        it('keeps service_code and logo_url when the response already has them', async () => {
            respond('buckaroo_config_giftcards', {
                status: true,
                custom_giftcard: { id: 13, name: 'X', service_code: 'xcode', logo_url: 'x.svg' },
            }, 'post');
            const wrapper = mountModal();
            wrapper.vm.add();
            await flushPromises();
            await fill(wrapper, { name: 'X', code: 'xcode' });

            await button(wrapper, 'Add').trigger('click');
            await flushPromises();

            expect(wrapper.emitted('appendCustomGiftcard')[0][0]).toMatchObject({ code: 'xcode', service_code: 'xcode', logo_url: 'x.svg' });
        });

        it('hides the buttons and shows the loader while posting', async () => {
            respond('buckaroo_config_giftcards', { status: true, custom_giftcard: { id: 1, code: 'c', name: 'n' } }, 'post');
            const wrapper = mountModal();
            wrapper.vm.add();
            await flushPromises();
            await fill(wrapper, { name: 'n', code: 'c' });

            button(wrapper, 'Add').trigger('click');
            await wrapper.vm.$nextTick();

            expect(button(wrapper, 'Add')).toBeUndefined();
            expect(wrapper.find('[data-loading]').exists()).toBe(true);
            await flushPromises();
        });

        it('stays open without emitting when the server refuses', async () => {
            respond('buckaroo_config_giftcards', { status: false, message: 'Missing or invalid data' }, 'post');
            const wrapper = mountModal();
            wrapper.vm.add();
            await flushPromises();
            await fill(wrapper, { name: 'n', code: 'c' });

            await button(wrapper, 'Add').trigger('click');
            await flushPromises();

            expect(wrapper.emitted('appendCustomGiftcard')).toBeUndefined();
            expect(wrapper.find('dialog').exists()).toBe(true);
            expect(button(wrapper, 'Add')).toBeDefined();
        });
    });

    describe('editing', () => {
        it('opens the form filled with the giftcard', async () => {
            const wrapper = mountModal();

            wrapper.vm.edit(customCard());
            await flushPromises();

            expect(title(wrapper)).toBe('Edit Custom giftcard');
            expect(wrapper.get('#giftcard_name').element.value).toBe('Shop card');
            expect(wrapper.get('#giftcard_service_code').element.value).toBe('shopcard');
            expect(wrapper.get('#custom_giftcard_url').element.value).toBe('https://cdn.test/shop.svg');
            expect(button(wrapper, 'Add')).toBeUndefined();
            expect(button(wrapper, 'Remove')).toBeDefined();
        });

        it('posts the edited giftcard and closes on success', async () => {
            respond('buckaroo_edit_giftcard', { status: true }, 'post');
            const wrapper = mountModal();
            wrapper.vm.edit(customCard());
            await flushPromises();
            await fill(wrapper, { name: 'Renamed', code: 'renamed' });

            await button(wrapper, 'Edit').trigger('click');
            await flushPromises();

            expect(callsTo('buckaroo_edit_giftcard', 'post')[0].payload).toEqual({
                ...customCard(),
                name: 'Renamed',
                service_code: 'renamed',
            });
            expect(wrapper.find('dialog').exists()).toBe(false);
        });

        it('edits a copy, so closing the modal leaves the listed giftcard unchanged', async () => {
            const card = customCard();
            const wrapper = mountModal();
            wrapper.vm.edit(card);
            await flushPromises();

            await fill(wrapper, { name: 'Typed', code: 'typed' });
            await wrapper.get('svg').trigger('click');

            expect(card).toEqual(customCard());
        });

        it('leaves the listed giftcard unchanged when the edit is refused', async () => {
            respond('buckaroo_edit_giftcard', { status: false }, 'post');
            const card = customCard();
            const wrapper = mountModal();
            wrapper.vm.edit(card);
            await flushPromises();

            await fill(wrapper, { name: 'Typed' });
            await button(wrapper, 'Edit').trigger('click');
            await flushPromises();

            expect(card).toEqual(customCard());
            expect(wrapper.get('#giftcard_name').element.value).toBe('Typed');
        });

        it('applies the stored giftcard from the response to the listed giftcard after a successful save', async () => {
            // GiftCardsRepository::editGiftCard() answers with BkGiftcards::getAll()
            respond('buckaroo_edit_giftcard', {
                status: true,
                custom_giftcard: { id: 7, name: 'Stored', service_code: 'stored', is_custom: true, logo_url: 'https://cdn.test/new.svg' },
            }, 'post');
            const card = customCard();
            const wrapper = mountModal();
            wrapper.vm.edit(card);
            await flushPromises();

            await fill(wrapper, { name: 'Stored', code: 'stored', logo: 'https://cdn.test/new.svg' });
            await button(wrapper, 'Edit').trigger('click');
            await flushPromises();

            expect(card).toEqual({
                ...customCard(),
                name: 'Stored',
                code: 'stored',
                service_code: 'stored',
                logo_url: 'https://cdn.test/new.svg',
                is_custom: true,
            });
        });

        it('applies the form values when the save response has no giftcard', async () => {
            respond('buckaroo_edit_giftcard', { status: true }, 'post');
            const card = customCard();
            const wrapper = mountModal();
            wrapper.vm.edit(card);
            await flushPromises();

            await fill(wrapper, { name: 'Renamed' });
            await button(wrapper, 'Edit').trigger('click');
            await flushPromises();

            expect(card).toEqual({ ...customCard(), name: 'Renamed' });
        });

        it('normalises a stored giftcard that uses code and logo', async () => {
            respond('buckaroo_edit_giftcard', {
                status: true,
                custom_giftcard: { id: 7, name: 'Shop card', code: 'newcode', logo: 'l.svg' },
            }, 'post');
            const card = customCard();
            const wrapper = mountModal();
            wrapper.vm.edit(card);
            await flushPromises();

            await button(wrapper, 'Edit').trigger('click');
            await flushPromises();

            expect(card).toMatchObject({ code: 'newcode', service_code: 'newcode', logo: 'l.svg', logo_url: 'l.svg', isCustom: true });
        });

        it('does not post an edit with an empty name or service code', async () => {
            const wrapper = mountModal();
            wrapper.vm.edit(customCard());
            await flushPromises();

            await fill(wrapper, { name: '' });
            await button(wrapper, 'Edit').trigger('click');
            await fill(wrapper, { name: 'ok', code: '' });
            await button(wrapper, 'Edit').trigger('click');
            await flushPromises();

            expect(callsTo('buckaroo_edit_giftcard')).toHaveLength(0);
        });

        it('stays open when the edit is refused', async () => {
            respond('buckaroo_edit_giftcard', { status: false }, 'post');
            const wrapper = mountModal();
            wrapper.vm.edit(customCard());
            await flushPromises();

            await button(wrapper, 'Edit').trigger('click');
            await flushPromises();

            expect(wrapper.find('dialog').exists()).toBe(true);
        });
    });

    describe('removing', () => {
        it('posts the giftcard, emits removeCustomGiftcard and closes', async () => {
            respond('buckaroo_remove_giftcard', { status: true, message: 'deleted' }, 'post');
            const wrapper = mountModal();
            wrapper.vm.edit(customCard());
            await flushPromises();

            await button(wrapper, 'Remove').trigger('click');
            await flushPromises();

            expect(callsTo('buckaroo_remove_giftcard', 'post')[0].payload).toMatchObject({ id: 7 });
            expect(wrapper.emitted('removeCustomGiftcard')[0][0]).toMatchObject({ id: 7 });
            expect(wrapper.find('dialog').exists()).toBe(false);
        });

        it('stays open without emitting when the removal fails', async () => {
            respond('buckaroo_remove_giftcard', { status: false, message: 'Failed' }, 'post');
            const wrapper = mountModal();
            wrapper.vm.edit(customCard());
            await flushPromises();

            await button(wrapper, 'Remove').trigger('click');
            await flushPromises();

            expect(wrapper.emitted('removeCustomGiftcard')).toBeUndefined();
            expect(wrapper.find('dialog').exists()).toBe(true);
        });
    });

    it('closes with the close icon', async () => {
        const wrapper = mountModal();
        wrapper.vm.add();
        await flushPromises();

        await wrapper.get('svg').trigger('click');

        expect(wrapper.find('dialog').exists()).toBe(false);
    });
});
