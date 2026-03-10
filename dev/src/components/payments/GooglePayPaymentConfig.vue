<template>
    <div>
      <div class="p-5 space-y-5">
        <div class="space-y-2">
          <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.googlepay.merchant_details`) }}</h2>
        </div>

        <div class="relative mb-4">
          <input
            type="text"
            id="googlepay_merchant_name"
            class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-primary peer"
            placeholder=" "
            v-model="config.merchantName"
          />
          <label
            for="googlepay_merchant_name"
            class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1"
          >
            {{ $t(`dashboard.pages.payments.googlepay.merchant_name`) }}
          </label>
        </div>

        <div class="relative mb-4">
          <input
            type="text"
            id="googlepay_merchant_id"
            class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-primary peer"
            placeholder=" "
            v-model="config.merchantId"
          />
          <label
            for="googlepay_merchant_id"
            class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1"
          >
            {{ $t(`dashboard.pages.payments.googlepay.merchant_id`) }}
          </label>
        </div>

        <div class="relative">
          <input
            type="text"
            id="googlepay_gateway_merchant_id"
            class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-primary peer"
            placeholder=" "
            v-model="config.gatewayMerchantId"
          />
          <label
            for="googlepay_gateway_merchant_id"
            class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1"
          >
            {{ $t(`dashboard.pages.payments.googlepay.gateway_merchant_id`) }}
          </label>
        </div>
      </div>
    </div>
</template>

<script>
import { inject } from "vue";

export default {
    name: "GooglePayPaymentConfig",
    setup() {
        const config = inject('config');

        return {
            config,
        };
    },
};
</script>

