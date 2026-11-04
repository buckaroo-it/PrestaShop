import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import { enableAutoUnmount } from '@vue/test-utils';
import VerificationMethodBlock from '@/components/VerificationMethodBlock.vue';
import { mountApp, flushPromises } from '../../helpers';
import { respond, fail, callsTo } from '../../mocks/api';

vi.mock('@/lib/api', () => import('../../mocks/api'));

const { toastr } = vi.hoisted(() => ({ toastr: { success: vi.fn(), error: vi.fn() } }));
vi.mock('@/lib/toastr', () => ({ useToastr: () => ({ toastr }) }));

enableAutoUnmount(afterEach);

// One entry of ConfigService::getVerificationMethodsFromDBWithConfig()
const idin = (overrides = {}) => reactive({ id: 1, name: 'idin', icon: 'iDIN.svg', mode: 'off', ...overrides });

const mountBlock = (payment = idin()) => mountApp(VerificationMethodBlock, { props: { payment } });
const modeButtons = (wrapper) => wrapper.findAll('[role="group"] button');

beforeEach(() => {
    toastr.success.mockClear();
    toastr.error.mockClear();
});

describe('VerificationMethodBlock', () => {
    it('shows the method name and its icon from the module image folder', () => {
        const wrapper = mountBlock();

        expect(wrapper.text()).toContain('iDIN');
        expect(wrapper.get('img').attributes('src'))
            .toBe('https://shop.test/modules/buckaroo3/views/img/buckaroo/Identification methods/SVG/iDIN.svg');
    });

    it('dims the icon only while the method is off', () => {
        expect(mountBlock(idin({ mode: 'off' })).get('img').classes()).toContain('opacity-60');
        expect(mountBlock(idin({ mode: 'test' })).get('img').classes()).not.toContain('opacity-60');
        expect(mountBlock(idin({ mode: 'live' })).get('img').classes()).not.toContain('opacity-60');
    });

    it('renders a labelled Live/Test/Off group with the current mode pressed', () => {
        const wrapper = mountBlock(idin({ mode: 'test' }));

        expect(wrapper.get('[role="group"]').attributes('aria-label')).toBe('Mode');
        expect(modeButtons(wrapper).map((b) => b.text())).toEqual(['Live', 'Test', 'Off']);
        expect(modeButtons(wrapper).map((b) => b.attributes('aria-pressed'))).toEqual(['false', 'true', 'false']);
        expect(modeButtons(wrapper)[1].classes()).toContain('text-fifthly');
        modeButtons(wrapper).forEach((b) => expect(b.attributes('type')).toBe('button'));
    });

    it('saves a new mode straight away and confirms it', async () => {
        const payment = idin();
        respond('buckaroo_config_methodMode', { status: true }, 'post');
        const wrapper = mountBlock(payment);

        await modeButtons(wrapper)[0].trigger('click');
        await flushPromises();

        expect(modeButtons(wrapper)[0].attributes('aria-pressed')).toBe('true');
        expect(callsTo('buckaroo_config_methodMode', 'post')).toHaveLength(1);
        expect(callsTo('buckaroo_config_methodMode', 'post')[0].payload).toMatchObject({ name: 'idin', mode: 'live' });
        expect(toastr.success).toHaveBeenCalledWith('iDIN is now set to Live.');
    });

    it('does not post when the already active mode is clicked', async () => {
        const wrapper = mountBlock(idin({ mode: 'test' }));

        await modeButtons(wrapper)[1].trigger('click');
        await flushPromises();

        expect(callsTo('buckaroo_config_methodMode')).toHaveLength(0);
    });

    it('stays quiet when the server does not confirm the change', async () => {
        respond('buckaroo_config_methodMode', { status: false }, 'post');
        const wrapper = mountBlock();

        await modeButtons(wrapper)[1].trigger('click');
        await flushPromises();

        expect(toastr.success).not.toHaveBeenCalled();
        expect(toastr.error).not.toHaveBeenCalled();
    });

    it('shows an error toast when saving the mode fails', async () => {
        fail('buckaroo_config_methodMode', new Error('500'), 'post');
        const wrapper = mountBlock();

        await modeButtons(wrapper)[1].trigger('click');
        await flushPromises();

        expect(toastr.error).toHaveBeenCalledWith('Something went wrong.');
        expect(toastr.success).not.toHaveBeenCalled();
    });

    it('asks to open the method from both the tile and the Configure button', async () => {
        const wrapper = mountBlock();
        const tile = wrapper.findAll('button')[0];
        const configure = wrapper.findAll('button').find((b) => b.text() === 'Configure');

        await tile.trigger('click');
        await configure.trigger('click');

        expect(wrapper.emitted('selectPayment')).toHaveLength(2);
        expect(callsTo('buckaroo_config_methodMode')).toHaveLength(0);
    });
});
