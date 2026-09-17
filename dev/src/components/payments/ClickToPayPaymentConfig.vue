<template>
    <div>
      <div class="p-5 space-y-5">
        <div class="space-y-2">
          <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.clicktopay.credentials`) }}</h2>
          <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.clicktopay.credentials_label`) }}</div>
        </div>

        <div class="relative mb-4">
          <input
            type="text"
            id="clicktopay_client_id"
            class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-fifthly peer"
            placeholder=" "
            v-model="config.client_id"
          />
          <label
            for="clicktopay_client_id"
            class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-fifthly peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1"
          >
            {{ $t(`dashboard.pages.payments.clicktopay.client_id`) }}
          </label>
        </div>

        <div class="relative mb-4">
          <input
            type="password"
            autocomplete="new-password"
            id="clicktopay_client_secret"
            class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-fifthly peer"
            placeholder=" "
            v-model="config.client_secret"
          />
          <label
            for="clicktopay_client_secret"
            class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-fifthly peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1"
          >
            {{ $t(`dashboard.pages.payments.clicktopay.client_secret`) }}
          </label>
        </div>

        <div class="relative">
          <input
            type="text"
            id="clicktopay_merchant_identifier"
            class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-fifthly peer"
            placeholder=" "
            v-model="config.merchant_identifier"
          />
          <label
            for="clicktopay_merchant_identifier"
            class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-fifthly peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1"
          >
            {{ $t(`dashboard.pages.payments.clicktopay.merchant_identifier`) }}
          </label>
        </div>

        <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.clicktopay.merchant_identifier_label`) }}</div>
      </div>
    </div>
</template>

<script>
import { inject } from "vue";

export default {
    name: "ClickToPayPaymentConfig",
    setup() {
        const config = inject('config');

        return {
            config,
        };
    },
};
</script>
