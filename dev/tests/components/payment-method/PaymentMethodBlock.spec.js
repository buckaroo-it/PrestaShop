import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { enableAutoUnmount } from '@vue/test-utils';
import { reactive } from 'vue';
import PaymentMethodBlock from '@/components/PaymentMethodBlock.vue';
import { flushPromises, mountApp } from '../../helpers';
import { callsTo, fail, respond } from '../../mocks/api';

vi.mock('@/lib/api', () => import('../../mocks/api'));

enableAutoUnmount(afterEach);

const toastr = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn() }));
vi.mock('@/lib/toastr', () => ({ useToastr: () => ({ toastr }) }));

const mountBlock = (payment) => {
    const reactivePayment = reactive(payment);
    const wrapper = mountApp(PaymentMethodBlock, { props: { payment: reactivePayment } });

    return { wrapper, payment: reactivePayment };
};

const modeButtons = (wrapper) => wrapper.findAll('[role="group"] button');
const pressed = (wrapper) => modeButtons(wrapper).map((b) => b.attributes('aria-pressed'));

describe('PaymentMethodBlock', () => {
    beforeEach(() => {
        toastr.success.mockClear();
        toastr.error.mockClear();
    });

    it('renders the icon, translated name and the current mode', () => {
        const { wrapper } = mountBlock({ name: 'paypal', icon: 'PayPal.svg', mode: 'test' });

        expect(wrapper.text()).toContain('PayPal');
        expect(wrapper.get('img').attributes('src'))
            .toBe('https://shop.test/modules/buckaroo3/views/img/buckaroo/Payment methods/SVG/PayPal.svg');
        expect(wrapper.get('img').classes()).not.toContain('opacity-60');
        expect(modeButtons(wrapper).map((b) => b.text())).toEqual(['Live', 'Test', 'Off']);
        expect(pressed(wrapper)).toEqual(['false', 'true', 'false']);
        expect(wrapper.get('[role="group"]').attributes('aria-label')).toBe('Mode');
    });

    it('shows iDEAL with its brand name', () => {
        const { wrapper } = mountBlock({ name: 'ideal', icon: 'iDEAL.svg', mode: 'live' });

        expect(wrapper.text()).toContain('iDEAL | Wero');
    });

    it('dims the icon of a disabled method', () => {
        const { wrapper } = mountBlock({ name: 'klarna', icon: 'Klarna.svg', mode: 'off' });

        expect(wrapper.get('img').classes()).toContain('opacity-60');
        expect(pressed(wrapper)).toEqual(['false', 'false', 'true']);
    });

    it('emits selectPayment from the tile and from the configure button', async () => {
        const { wrapper } = mountBlock({ name: 'klarna', icon: 'Klarna.svg', mode: 'off' });
        const buttons = wrapper.findAll('button');

        await buttons[0].trigger('click');
        await buttons.at(-1).trigger('click');

        expect(buttons.at(-1).text()).toBe('Configure');
        expect(wrapper.emitted('selectPayment')).toHaveLength(2);
    });

    it('posts the new mode and confirms it', async () => {
        respond('buckaroo_config_methodMode', { status: true }, 'post');
        const { wrapper, payment } = mountBlock({ name: 'paypal', icon: 'PayPal.svg', mode: 'off' });

        await modeButtons(wrapper)[0].trigger('click');
        await flushPromises();

        expect(payment.mode).toBe('live');
        expect(pressed(wrapper)).toEqual(['true', 'false', 'false']);
        expect(callsTo('buckaroo_config_methodMode', 'post').map((c) => c.payload))
            .toEqual([{ name: 'paypal', icon: 'PayPal.svg', mode: 'live' }]);
        expect(toastr.success).toHaveBeenCalledWith('PayPal is now set to Live.');
        expect(toastr.error).not.toHaveBeenCalled();
    });

    it('does not post when the active mode is clicked again', async () => {
        const { wrapper } = mountBlock({ name: 'paypal', icon: 'PayPal.svg', mode: 'test' });

        await modeButtons(wrapper)[1].trigger('click');
        await flushPromises();

        expect(callsTo('buckaroo_config_methodMode')).toHaveLength(0);
    });

    it('rolls back and shows an error when the server answers without status', async () => {
        respond('buckaroo_config_methodMode', { status: false }, 'post');
        const { wrapper, payment } = mountBlock({ name: 'paypal', icon: 'PayPal.svg', mode: 'off' });

        await modeButtons(wrapper)[1].trigger('click');
        await flushPromises();

        expect(callsTo('buckaroo_config_methodMode', 'post')).toHaveLength(1);
        expect(toastr.success).not.toHaveBeenCalled();
        expect(toastr.error).toHaveBeenCalledWith('Something went wrong.');
        expect(payment.mode).toBe('off');
        expect(pressed(wrapper)).toEqual(['false', 'false', 'true']);
    });

    it('rolls back to the previous mode and shows an error when saving the mode fails', async () => {
        fail('buckaroo_config_methodMode', new Error('boom'), 'post');
        const { wrapper, payment } = mountBlock({ name: 'ideal', icon: 'iDEAL.svg', mode: 'live' });

        await modeButtons(wrapper)[2].trigger('click');
        await flushPromises();

        expect(toastr.error).toHaveBeenCalledTimes(1);
        expect(toastr.error).toHaveBeenCalledWith('Something went wrong.');
        expect(toastr.success).not.toHaveBeenCalled();
        expect(payment.mode).toBe('live');
        expect(pressed(wrapper)).toEqual(['true', 'false', 'false']);
        // The rollback itself is not posted again.
        expect(callsTo('buckaroo_config_methodMode', 'post')).toHaveLength(1);
    });

    it('saves the next mode change normally after a rollback', async () => {
        fail('buckaroo_config_methodMode', new Error('boom'), 'post');
        const { wrapper, payment } = mountBlock({ name: 'paypal', icon: 'PayPal.svg', mode: 'live' });
        await modeButtons(wrapper)[2].trigger('click');
        await flushPromises();

        respond('buckaroo_config_methodMode', { status: true }, 'post');
        await modeButtons(wrapper)[1].trigger('click');
        await flushPromises();

        expect(payment.mode).toBe('test');
        expect(callsTo('buckaroo_config_methodMode', 'post')).toHaveLength(2);
        expect(toastr.success).toHaveBeenCalledWith('PayPal is now set to Test.');
    });
});
