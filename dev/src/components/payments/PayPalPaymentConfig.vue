<template>
    <div>
      <div class="p-5 space-y-5">
        <div class="space-y-2">
          <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.seller_protection`) }}</h2>
          <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.paypal.seller_protection_label`) }}</div>
        </div>

        <div class="relative">
          <select class="w-full rounded-lg border border-gray-300 p-2.5 peer" v-model="config.seller_protection">
            <option value="1">{{ $t(`dashboard.pages.payments.seller_protection_enabled`) }}</option>
            <option value="0">{{ $t(`dashboard.pages.payments.seller_protection_disabled`) }}</option>
          </select>

          <label for="frontend_label" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-fifthly peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
            {{ $t(`dashboard.pages.payments.seller_protection_enable`) }}
          </label>
        </div>
      </div>

      <div class="p-5 space-y-5">
        <div class="space-y-2">
          <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.paypal.express`) }}</h2>
          <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.paypal.express_label`) }}</div>
        </div>

        <div class="space-y-2">
          <div class="relative">
            <input
              type="text"
              id="paypal_express_merchant_id"
              class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-fifthly peer"
              placeholder=" "
              v-model.trim="config.express_merchant_id"
            />
            <label
              for="paypal_express_merchant_id"
              class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-fifthly peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1"
            >
              {{ $t(`dashboard.pages.payments.paypal.merchant_id`) }}
            </label>
          </div>
          <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.paypal.merchant_id_label`) }}</div>
        </div>

        <div class="space-y-2">
          <div class="relative">
            <input
              type="text"
              id="paypal_express_sandbox_merchant_id"
              class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-fifthly peer"
              placeholder=" "
              v-model.trim="config.express_sandbox_merchant_id"
            />
            <label
              for="paypal_express_sandbox_merchant_id"
              class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-fifthly peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1"
            >
              {{ $t(`dashboard.pages.payments.paypal.sandbox_merchant_id`) }}
            </label>
          </div>
          <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.paypal.sandbox_merchant_id_label`) }}</div>
        </div>
      </div>

      <div class="p-5 space-y-5">
        <div class="space-y-2">
          <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.paypal.show_on`) }}</h2>
          <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.paypal.show_on_label`) }}</div>
        </div>

        <div class="space-y-2">
          <label v-for="placement in placements" :key="placement" class="flex items-center space-x-2 text-sm">
            <input type="checkbox" :value="placement" v-model="config.express_show_on" />
            <span>{{ $t(`dashboard.pages.payments.paypal.placements.${placement}`) }}</span>
          </label>
        </div>
      </div>

      <div class="p-5 space-y-5">
        <div class="space-y-2">
          <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.paypal.button_style`) }}</h2>
          <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.paypal.button_style_label`) }}</div>
        </div>

        <select class="w-full rounded-lg border border-gray-300 p-2.5" v-model="config.button_style">
          <option v-for="style in buttonStyles" :key="style" :value="style">
            {{ $t(`dashboard.pages.payments.paypal.styles.${style}`) }}
          </option>
        </select>

        <div class="space-y-2">
          <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.paypal.button_type`) }}</h2>
          <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.paypal.button_type_label`) }}</div>
        </div>

        <select class="w-full rounded-lg border border-gray-300 p-2.5" v-model="config.button_type">
          <option v-for="type in buttonTypes" :key="type" :value="type">
            {{ $t(`dashboard.pages.payments.paypal.types.${type}`) }}
          </option>
        </select>

        <div class="space-y-2">
          <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.paypal.button_rounded`) }}</h2>
        </div>

        <select class="w-full rounded-lg border border-gray-300 p-2.5" v-model="config.button_rounded">
          <option value="0">{{ $t(`dashboard.pages.payments.seller_protection_disabled`) }}</option>
          <option value="1">{{ $t(`dashboard.pages.payments.seller_protection_enabled`) }}</option>
        </select>

        <div class="space-y-2">
          <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.paypal.preview`) }}</h2>
          <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.paypal.preview_label`) }}</div>
        </div>

        <div class="rounded-lg border border-gray-200 p-4" :class="config.button_style === 'white' ? 'bg-gray-100' : 'bg-white'">
          <div v-show="previewReady" ref="previewHost" class="buckaroo-paypal-preview"></div>
          <div v-if="!previewReady" class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.paypal.preview_unavailable`) }}</div>
        </div>
      </div>
    </div>
</template>

<script>
import { inject, nextTick, onMounted, ref, watch } from "vue";

// PayPal's JS SDK draws the official buttons; the preview uses Buckaroo's PayPal sandbox client.
const PAYPAL_SDK_URL = 'https://www.paypal.com/sdk/js?client-id=Ab8jz-WnSLFLHjn-i_Bs0k_eWmRThzLHKpWnFwtwuPlQigUZsZWzgDF1AXQG3dHKPgW2YWKkGtJIjFty&currency=EUR&components=buttons&disable-funding=credit,card,bancontact,blik,eps,giropay,ideal,mercadopago,mybank,p24,sepa,sofort,venmo&enable-funding=paylater';

const PLACEMENTS = ['product', 'cart', 'checkout'];
const BUTTON_STYLES = ['gold', 'blue', 'silver', 'white', 'black'];
const BUTTON_TYPES = ['paypal', 'checkout', 'buynow', 'pay'];

let sdkPromise = null;
let previewButtons = null;

const loadPayPalSdk = () => {
    if (window.paypal && typeof window.paypal.Buttons === 'function') {
        return Promise.resolve(true);
    }

    if (!sdkPromise) {
        sdkPromise = new Promise((resolve) => {
            const script = document.createElement('script');
            script.src = PAYPAL_SDK_URL;
            script.async = true;
            script.onload = () => resolve(!!(window.paypal && typeof window.paypal.Buttons === 'function'));
            script.onerror = () => {
                sdkPromise = null;
                resolve(false);
            };
            document.head.appendChild(script);
        });
    }

    return sdkPromise;
};

export default {
    name: "PayPalPaymentConfig",
    setup() {
        const config = inject('config');
        const previewReady = ref(false);
        const previewHost = ref(null);

        // Configurations saved before the express settings existed: seller protection on,
        // the express button on no page until the merchant picks them.
        const applyDefaults = () => {
            if (!config.value) {
                return;
            }
            if (config.value.seller_protection === undefined || config.value.seller_protection === null) {
                config.value.seller_protection = '1';
            }
            if (!Array.isArray(config.value.express_show_on)) {
                config.value.express_show_on = [];
            }
            if (!BUTTON_STYLES.includes(config.value.button_style)) {
                config.value.button_style = BUTTON_STYLES[0];
            }
            if (!BUTTON_TYPES.includes(config.value.button_type)) {
                config.value.button_type = BUTTON_TYPES[0];
            }
            if (config.value.button_rounded !== '1') {
                config.value.button_rounded = '0';
            }
            ['express_merchant_id', 'express_sandbox_merchant_id'].forEach((key) => {
                if (config.value[key] === undefined || config.value[key] === null) {
                    config.value[key] = '';
                }
            });
        };

        const renderPreview = () => {
            const host = previewHost.value;
            if (!previewReady.value || !host || !config.value) {
                return;
            }

            try {
                if (previewButtons && typeof previewButtons.close === 'function') {
                    previewButtons.close();
                }
                host.innerHTML = '';
                previewButtons = window.paypal.Buttons({
                    style: {
                        layout: 'vertical',
                        color: config.value.button_style,
                        shape: config.value.button_rounded === '1' ? 'pill' : 'rect',
                        label: config.value.button_type,
                        height: 45,
                    },
                    // Only a preview: the button never opens PayPal here.
                    onClick: (data, actions) => actions.reject(),
                });
                previewButtons.render(host).catch(() => {
                    previewReady.value = false;
                });
            } catch (e) {
                previewReady.value = false;
            }
        };

        watch(config, applyDefaults, { immediate: true });

        watch(
            () => [config.value && config.value.button_style, config.value && config.value.button_type, config.value && config.value.button_rounded],
            () => nextTick(renderPreview)
        );

        onMounted(() => {
            loadPayPalSdk().then((ready) => {
                previewReady.value = ready;
                nextTick(renderPreview);
            });
        });

        return {
            config,
            previewReady,
            previewHost,
            placements: PLACEMENTS,
            buttonStyles: BUTTON_STYLES,
            buttonTypes: BUTTON_TYPES,
        };
    },
};
</script>

<style scoped>
.buckaroo-paypal-preview {
    width: 100%;
    max-width: 400px;
}
</style>
