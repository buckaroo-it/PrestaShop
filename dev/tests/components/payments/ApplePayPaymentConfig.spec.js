import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ApplePayPaymentConfig from '@/components/payments/ApplePayPaymentConfig.vue';
import i18n from '@/i18n';
import { flushPromises } from '../../helpers';
import { mountConfig } from './mountConfig';

vi.mock('@/lib/api', () => import('../../mocks/api'));

/*
 * The component loads Apple's SDK by appending a <script> to <head>. The spy below keeps that
 * script out of the document (no network) so each test can fire onload / onerror itself.
 *
 * The SDK promise is module state: every test settles its pending script in afterEach (onerror
 * resets the promise), and the tests that define <apple-pay-button> run last because a custom
 * element cannot be undefined again.
 */
const SDK_URL = 'https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js';
let scripts = [];

beforeEach(() => {
    scripts = [];
    const original = document.head.appendChild.bind(document.head);
    vi.spyOn(document.head, 'appendChild').mockImplementation((node) => {
        if (node.tagName === 'SCRIPT') {
            scripts.push(node);

            return node;
        }

        return original(node);
    });
});

afterEach(() => {
    scripts.forEach((script) => script.onerror && script.onerror());
});

const mountApplePay = (initial = {}) => mountConfig(ApplePayPaymentConfig, initial);
const selects = (wrapper) => wrapper.findAll('select');
const previewBox = (wrapper) => wrapper.get('.rounded-lg.border-gray-200');

describe('ApplePayPaymentConfig settings', () => {
    // What the form shows: GUID, placements, checkout method, style, type.
    const shown = (wrapper) => ({
        merchant_guid: wrapper.get('#applepay_merchant_guid').element.value,
        express_show_on: wrapper.findAll('input[type="checkbox"]').filter((b) => b.element.checked).map((b) => b.attributes('value')),
        checkout_method: selects(wrapper)[0].element.value,
        button_style: selects(wrapper)[1].element.value,
        button_type: selects(wrapper)[2].element.value,
    });

    it('shows the shop defaults for a configuration saved before the express settings existed, without writing them', async () => {
        const { wrapper, config } = await mountApplePay({ mode: 'live' });

        expect(shown(wrapper)).toEqual({
            merchant_guid: '',
            express_show_on: ['product', 'cart', 'checkout'],
            checkout_method: '1',
            button_style: 'black',
            button_type: 'plain',
        });
        // Opening the page must not count as an unsaved change.
        expect(config.value).toEqual({ mode: 'live' });
    });

    it('keeps valid stored values', async () => {
        const stored = {
            express_show_on: ['cart'],
            checkout_method: '0',
            button_style: 'white-outline',
            button_type: 'subscribe',
            merchant_guid: 'guid-1',
        };
        const { wrapper, config } = await mountApplePay({ ...stored });

        expect(shown(wrapper)).toEqual(stored);
        expect(config.value).toEqual(stored);
    });

    it.each([
        ['express_show_on', 'product', ['product', 'cart', 'checkout']],
        ['checkout_method', null, '1'],
        ['button_style', 'red', 'black'],
        ['button_type', 'donate', 'plain'],
        ['merchant_guid', null, ''],
    ])('shows the default for an invalid %s (%j) and leaves the stored value alone', async (key, invalid, expected) => {
        const { wrapper, config } = await mountApplePay({ [key]: invalid });

        expect(shown(wrapper)[key]).toEqual(expected);
        expect(config.value[key]).toEqual(invalid);
    });

    it('shows the defaults again when the whole config is replaced (loaded from the backend)', async () => {
        const { wrapper, config } = await mountApplePay({ button_style: 'white' });

        config.value = { button_type: 'buy' };
        await flushPromises();

        expect(shown(wrapper)).toMatchObject({
            button_style: 'black',
            button_type: 'buy',
            checkout_method: '1',
            express_show_on: ['product', 'cart', 'checkout'],
        });
        expect(config.value).toEqual({ button_type: 'buy' });
    });

    it('stores a value once the merchant picks one', async () => {
        const { wrapper, config } = await mountApplePay({});

        await selects(wrapper)[1].setValue('white');

        expect(config.value).toEqual({ button_style: 'white' });
    });

    it('trims the merchant GUID into config.merchant_guid', async () => {
        const { wrapper, config } = await mountApplePay({ merchant_guid: 'old' });
        const input = wrapper.get('#applepay_merchant_guid');

        expect(input.element.value).toBe('old');

        await input.setValue('  1234-abcd  ');

        expect(config.value.merchant_guid).toBe('1234-abcd');
    });

    it('renders a checkbox per placement and writes the checked ones into config.express_show_on', async () => {
        const { wrapper, config } = await mountApplePay({ express_show_on: ['product', 'cart', 'checkout'] });
        const boxes = wrapper.findAll('input[type="checkbox"]');

        expect(boxes.map((b) => b.attributes('value'))).toEqual(['product', 'cart', 'checkout']);
        expect(wrapper.findAll('input[type="checkbox"] + span').map((s) => s.text()))
            .toEqual(['Product page', 'Cart page', 'Checkout page']);
        expect(boxes.every((b) => b.element.checked)).toBe(true);

        await boxes[1].setValue(false);
        expect(config.value.express_show_on).toEqual(['product', 'checkout']);

        await boxes[1].setValue(true);
        expect(config.value.express_show_on).toEqual(['product', 'checkout', 'cart']);
    });

    it('binds the checkout method select ("1"/"0") to config.checkout_method', async () => {
        const { wrapper, config } = await mountApplePay({ checkout_method: '1' });
        const select = selects(wrapper)[0];

        expect(select.findAll('option').map((o) => [o.attributes('value'), o.text()])).toEqual([['1', 'Yes'], ['0', 'No']]);

        await select.setValue('0');

        expect(config.value.checkout_method).toBe('0');
    });

    it('offers every button style and type and writes the choice into the config', async () => {
        const { wrapper, config } = await mountApplePay({});
        const [, style, type] = selects(wrapper);

        expect(style.findAll('option').map((o) => o.attributes('value'))).toEqual(['black', 'white', 'white-outline']);
        expect(style.findAll('option').map((o) => o.text())).toContain('White outline');
        expect(type.findAll('option').map((o) => o.attributes('value')))
            .toEqual(['plain', 'buy', 'check-out', 'order', 'book', 'subscribe']);
        expect(type.findAll('option').map((o) => o.text())).toContain('Check out');

        await style.setValue('white');
        await type.setValue('order');

        expect(config.value).toMatchObject({ button_style: 'white', button_type: 'order' });
    });
});

describe('ApplePayPaymentConfig preview (SDK not available)', () => {
    it('requests the Apple Pay SDK once, anonymously, and shows a placeholder while it loads', async () => {
        const { wrapper } = await mountApplePay({});

        expect(scripts).toHaveLength(1);
        expect(scripts[0].src).toBe(SDK_URL);
        expect(scripts[0].crossOrigin).toBe('anonymous');
        expect(wrapper.find('apple-pay-button').exists()).toBe(false);
        expect(wrapper.text()).toContain('The preview could not be loaded from Apple.');
    });

    it('shares one pending SDK request between components', async () => {
        await mountApplePay({});
        await mountApplePay({});

        expect(scripts).toHaveLength(1);
    });

    it('keeps the placeholder when the SDK fails and retries on the next mount', async () => {
        const { wrapper } = await mountApplePay({});
        scripts[0].onerror();
        await flushPromises();

        expect(wrapper.find('apple-pay-button').exists()).toBe(false);
        expect(wrapper.text()).toContain('The preview could not be loaded from Apple.');

        await mountApplePay({});
        expect(scripts).toHaveLength(2);
    });

    it('uses a white background behind the black button and grey behind the white ones', async () => {
        const { wrapper, config } = await mountApplePay({ button_style: 'black' });

        expect(previewBox(wrapper).classes()).toContain('bg-white');

        config.value.button_style = 'white';
        await flushPromises();

        expect(previewBox(wrapper).classes()).toContain('bg-gray-100');
        expect(previewBox(wrapper).classes()).not.toContain('bg-white');
    });
});

// Must stay last: from here on <apple-pay-button> is a defined custom element.
describe('ApplePayPaymentConfig preview (SDK loaded)', () => {
    it('shows the Apple Pay button once the SDK has loaded and registered the element', async () => {
        const { wrapper } = await mountApplePay({ button_style: 'white', button_type: 'buy' });

        expect(scripts).toHaveLength(1);
        scripts[0].onload();
        await flushPromises();
        // whenDefined() only resolves after the SDK registers the element.
        expect(wrapper.find('apple-pay-button').exists()).toBe(false);

        window.customElements.define('apple-pay-button', class extends HTMLElement {});
        await flushPromises();

        const button = wrapper.get('apple-pay-button');
        expect(button.attributes()).toMatchObject({ buttonstyle: 'white', type: 'buy', locale: 'en-US' });
        expect(button.classes()).toContain('buckaroo-applepay-preview');
        expect(wrapper.text()).not.toContain('The preview could not be loaded from Apple.');
    });

    it('skips loading the SDK when the element is already registered', async () => {
        const { wrapper } = await mountApplePay({});

        expect(scripts).toHaveLength(0);
        expect(wrapper.find('apple-pay-button').exists()).toBe(true);
    });

    it('re-renders the preview button when the style or type changes', async () => {
        const { wrapper, config } = await mountApplePay({ button_style: 'black', button_type: 'plain' });
        const first = wrapper.get('apple-pay-button').element;

        await selects(wrapper)[1].setValue('white-outline');
        await selects(wrapper)[2].setValue('check-out');

        const button = wrapper.get('apple-pay-button');
        expect(button.element).not.toBe(first); // keyed on style + type so the web component re-initialises
        expect(button.attributes()).toMatchObject({ buttonstyle: 'white-outline', type: 'check-out' });
        expect(config.value).toMatchObject({ button_style: 'white-outline', button_type: 'check-out' });
    });

    it.each([
        ['nl', 'nl-NL'],
        ['de', 'de-DE'],
        ['fr', 'fr-FR'],
    ])('passes the admin language %s to the button as %s', async (lang, expected) => {
        const { wrapper } = await mountApplePay({});

        i18n.global.locale.value = lang;
        await flushPromises();

        expect(wrapper.get('apple-pay-button').attributes('locale')).toBe(expected);
    });
});
