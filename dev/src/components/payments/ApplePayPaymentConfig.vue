<template>
    <div>
      <div class="p-5 space-y-5">
        <div class="space-y-2">
          <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.applepay.merchant_guid`) }}</h2>
          <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.applepay.merchant_guid_label`) }}</div>
        </div>

        <div class="relative">
          <input
            type="text"
            id="applepay_merchant_guid"
            class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-fifthly peer"
            placeholder=" "
            v-model.trim="config.merchant_guid"
          />
          <label
            for="applepay_merchant_guid"
            class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-fifthly peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1"
          >
            {{ $t(`dashboard.pages.payments.applepay.merchant_guid`) }}
          </label>
        </div>
      </div>

      <div class="p-5 space-y-5">
        <div class="space-y-2">
          <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.applepay.show_on`) }}</h2>
          <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.applepay.show_on_label`) }}</div>
        </div>

        <div class="space-y-2">
          <label v-for="placement in placements" :key="placement" class="flex items-center space-x-2 text-sm">
            <input type="checkbox" :value="placement" v-model="config.express_show_on" />
            <span>{{ $t(`dashboard.pages.payments.applepay.placements.${placement}`) }}</span>
          </label>
        </div>
      </div>

      <div class="p-5 space-y-5">
        <div class="space-y-2">
          <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.applepay.checkout_method`) }}</h2>
          <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.applepay.checkout_method_label`) }}</div>
        </div>

        <select class="w-full rounded-lg border border-gray-300 p-2.5" v-model="config.checkout_method">
          <option value="1">{{ $t(`dashboard.pages.payments.applepay.yes`) }}</option>
          <option value="0">{{ $t(`dashboard.pages.payments.applepay.no`) }}</option>
        </select>
      </div>

      <div class="p-5 space-y-5">
        <div class="space-y-2">
          <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.applepay.button_style`) }}</h2>
          <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.applepay.button_style_label`) }}</div>
        </div>

        <select class="w-full rounded-lg border border-gray-300 p-2.5" v-model="config.button_style">
          <option v-for="style in buttonStyles" :key="style" :value="style">
            {{ $t(`dashboard.pages.payments.applepay.styles.${style}`) }}
          </option>
        </select>

        <div class="space-y-2">
          <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.applepay.button_type`) }}</h2>
          <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.applepay.button_type_label`) }}</div>
        </div>

        <select class="w-full rounded-lg border border-gray-300 p-2.5" v-model="config.button_type">
          <option v-for="type in buttonTypes" :key="type" :value="type">
            {{ $t(`dashboard.pages.payments.applepay.types.${type}`) }}
          </option>
        </select>

        <div class="space-y-2">
          <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.applepay.preview`) }}</h2>
          <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.applepay.preview_label`) }}</div>
        </div>

        <div class="rounded-lg border border-gray-200 p-4" :class="config.button_style === 'black' ? 'bg-white' : 'bg-gray-100'">
          <apple-pay-button
            v-if="previewReady"
            :key="`${config.button_style}-${config.button_type}`"
            :buttonstyle="config.button_style"
            :type="config.button_type"
            :locale="locale"
            class="buckaroo-applepay-preview"
          ></apple-pay-button>
          <div v-else class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.applepay.preview_unavailable`) }}</div>
        </div>
      </div>
    </div>
</template>

<script>
import { inject, onMounted, ref, watch } from "vue";
import { useI18n } from "vue-i18n";

// Apple's Apple Pay JS SDK registers the <apple-pay-button> web component in every browser.
const APPLE_PAY_SDK_URL = 'https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js';

const PLACEMENTS = ['product', 'cart', 'checkout'];
const BUTTON_STYLES = ['black', 'white', 'white-outline'];
const BUTTON_TYPES = ['plain', 'buy', 'check-out', 'order', 'book', 'subscribe'];

let sdkPromise = null;

const loadAppleSdk = () => {
    if (window.customElements && window.customElements.get('apple-pay-button')) {
        return Promise.resolve(true);
    }

    if (!sdkPromise) {
        sdkPromise = new Promise((resolve) => {
            const script = document.createElement('script');
            script.src = APPLE_PAY_SDK_URL;
            script.crossOrigin = 'anonymous';
            script.onload = () => {
                window.customElements.whenDefined('apple-pay-button').then(() => resolve(true));
            };
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
    name: "ApplePayPaymentConfig",
    setup() {
        const config = inject('config');
        const { locale: i18nLocale } = useI18n();
        const previewReady = ref(false);
        const locale = ref('en-US');

        // Configurations saved before the express settings existed get the same defaults as the shop.
        const applyDefaults = () => {
            if (!config.value) {
                return;
            }
            if (!Array.isArray(config.value.express_show_on)) {
                config.value.express_show_on = [...PLACEMENTS];
            }
            if (config.value.checkout_method === undefined || config.value.checkout_method === null) {
                config.value.checkout_method = '1';
            }
            if (!BUTTON_STYLES.includes(config.value.button_style)) {
                config.value.button_style = BUTTON_STYLES[0];
            }
            if (!BUTTON_TYPES.includes(config.value.button_type)) {
                config.value.button_type = BUTTON_TYPES[0];
            }
            if (config.value.merchant_guid === undefined || config.value.merchant_guid === null) {
                config.value.merchant_guid = '';
            }
        };

        watch(config, applyDefaults, { immediate: true });

        watch(i18nLocale, (value) => {
            locale.value = value === 'en' ? 'en-US' : `${value}-${String(value).toUpperCase()}`;
        }, { immediate: true });

        onMounted(() => {
            loadAppleSdk().then((ready) => {
                previewReady.value = ready;
            });
        });

        return {
            config,
            locale,
            previewReady,
            placements: PLACEMENTS,
            buttonStyles: BUTTON_STYLES,
            buttonTypes: BUTTON_TYPES,
        };
    },
};
</script>

<style scoped>
.buckaroo-applepay-preview {
    display: block;
    --apple-pay-button-width: 100%;
    --apple-pay-button-height: 44px;
    --apple-pay-button-border-radius: 4px;
}
</style>
