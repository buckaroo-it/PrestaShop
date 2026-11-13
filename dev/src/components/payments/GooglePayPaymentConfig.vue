<template>
    <div>
      <div class="p-5 space-y-5">
        <div class="space-y-2">
          <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.googlepay.merchant_details`) }}</h2>
        </div>

        <div class="space-y-2">
          <div class="relative">
            <input
              type="text"
              id="googlepay_gateway_merchant_id"
              class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-fifthly peer"
              placeholder=" "
              v-model.trim="config.gatewayMerchantId"
            />
            <label
              for="googlepay_gateway_merchant_id"
              class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-fifthly peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1"
            >
              {{ $t(`dashboard.pages.payments.googlepay.gateway_merchant_id`) }}
            </label>
          </div>
          <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.googlepay.gateway_merchant_id_label`) }}</div>
        </div>

        <div class="space-y-2">
          <div class="relative">
            <input
              type="text"
              id="googlepay_merchant_id"
              class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-fifthly peer"
              placeholder=" "
              v-model.trim="config.merchantId"
            />
            <label
              for="googlepay_merchant_id"
              class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-fifthly peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1"
            >
              {{ $t(`dashboard.pages.payments.googlepay.merchant_id`) }}
            </label>
          </div>
          <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.googlepay.merchant_id_label`) }}</div>
        </div>

        <div class="space-y-2">
          <div class="relative">
            <input
              type="text"
              id="googlepay_merchant_name"
              class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-fifthly peer"
              placeholder=" "
              v-model="config.merchantName"
            />
            <label
              for="googlepay_merchant_name"
              class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-fifthly peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1"
            >
              {{ $t(`dashboard.pages.payments.googlepay.merchant_name`) }}
            </label>
          </div>
          <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.googlepay.merchant_name_label`) }}</div>
        </div>
      </div>

      <div class="p-5 space-y-5">
        <div class="space-y-2">
          <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.googlepay.show_on`) }}</h2>
          <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.googlepay.show_on_label`) }}</div>
        </div>

        <div class="space-y-2">
          <label v-for="placement in placements" :key="placement" class="flex items-center space-x-2 text-sm">
            <input type="checkbox" :value="placement" v-model="config.express_show_on" />
            <span>{{ $t(`dashboard.pages.payments.googlepay.placements.${placement}`) }}</span>
          </label>
        </div>
      </div>

      <div class="p-5 space-y-5">
        <div class="space-y-2">
          <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.googlepay.checkout_method`) }}</h2>
          <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.googlepay.checkout_method_label`) }}</div>
        </div>

        <select class="w-full rounded-lg border border-gray-300 p-2.5" v-model="config.checkout_method">
          <option value="1">{{ $t(`dashboard.pages.payments.googlepay.yes`) }}</option>
          <option value="0">{{ $t(`dashboard.pages.payments.googlepay.no`) }}</option>
        </select>
      </div>

      <div class="p-5 space-y-5">
        <div class="space-y-2">
          <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.googlepay.button_style`) }}</h2>
          <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.googlepay.button_style_label`) }}</div>
        </div>

        <select class="w-full rounded-lg border border-gray-300 p-2.5" v-model="config.button_style">
          <option v-for="style in buttonStyles" :key="style" :value="style">
            {{ $t(`dashboard.pages.payments.googlepay.styles.${style}`) }}
          </option>
        </select>

        <div class="space-y-2">
          <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.googlepay.button_type`) }}</h2>
          <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.googlepay.button_type_label`) }}</div>
        </div>

        <select class="w-full rounded-lg border border-gray-300 p-2.5" v-model="config.button_type">
          <option v-for="type in buttonTypes" :key="type" :value="type">
            {{ $t(`dashboard.pages.payments.googlepay.types.${type}`) }}
          </option>
        </select>

        <div class="space-y-2">
          <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.googlepay.preview`) }}</h2>
          <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.googlepay.preview_label`) }}</div>
        </div>

        <div class="rounded-lg border border-gray-200 p-4" :class="config.button_style === 'black' ? 'bg-white' : 'bg-gray-100'">
          <div v-show="previewReady" ref="previewHost" class="buckaroo-googlepay-preview"></div>
          <div v-if="!previewReady" class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.googlepay.preview_unavailable`) }}</div>
        </div>
      </div>
    </div>
</template>

<script>
import { inject, nextTick, onMounted, ref, watch } from "vue";
import { useI18n } from "vue-i18n";

// Google's Google Pay API draws the official button; the preview uses the test environment.
const GOOGLE_PAY_SDK_URL = 'https://pay.google.com/gp/p/js/pay.js';

const PLACEMENTS = ['product', 'cart', 'checkout'];
const BUTTON_STYLES = ['black', 'white'];
// Listed as Google orders them; "Pay with" is the default.
const BUTTON_TYPES = ['plain', 'pay', 'buy', 'checkout', 'order', 'book', 'subscribe'];
const DEFAULT_BUTTON_TYPE = 'pay';

let sdkPromise = null;
let paymentsClient = null;

const loadGooglePaySdk = () => {
    if (window.google && window.google.payments && window.google.payments.api) {
        return Promise.resolve(true);
    }

    if (!sdkPromise) {
        sdkPromise = new Promise((resolve) => {
            const script = document.createElement('script');
            script.src = GOOGLE_PAY_SDK_URL;
            script.async = true;
            script.onload = () => resolve(!!(window.google && window.google.payments && window.google.payments.api));
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
    name: "GooglePayPaymentConfig",
    setup() {
        const config = inject('config');
        const { locale } = useI18n();
        const previewReady = ref(false);
        const previewHost = ref(null);

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
                config.value.button_type = DEFAULT_BUTTON_TYPE;
            }
            ['gatewayMerchantId', 'merchantId', 'merchantName'].forEach((key) => {
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
                if (!paymentsClient) {
                    paymentsClient = new window.google.payments.api.PaymentsClient({ environment: 'TEST' });
                }
                const button = paymentsClient.createButton({
                    onClick: () => {},
                    buttonColor: config.value.button_style === 'white' ? 'white' : 'black',
                    buttonType: config.value.button_type || DEFAULT_BUTTON_TYPE,
                    buttonLocale: String(locale.value || 'en').substring(0, 2),
                    buttonSizeMode: 'fill',
                });
                host.innerHTML = '';
                host.appendChild(button);
            } catch (e) {
                previewReady.value = false;
            }
        };

        watch(config, applyDefaults, { immediate: true });

        watch(
            () => [config.value && config.value.button_style, config.value && config.value.button_type, locale.value],
            () => nextTick(renderPreview)
        );

        onMounted(() => {
            loadGooglePaySdk().then((ready) => {
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
.buckaroo-googlepay-preview {
    width: 100%;
    height: 44px;
}
</style>
