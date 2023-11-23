<template>
    <div>
      <div class="p-5 space-y-5">
        <div class="space-y-2">
          <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.seller_protection`) }}</h2>
        </div>

        <div class="relative">
          <select class="w-full rounded-lg border border-gray-300 p-2.5 peer" v-model="config.seller_protection">
            <option value="1">{{ $t(`dashboard.pages.payments.seller_protection_enabled`) }}</option>
            <option value="0">{{ $t(`dashboard.pages.payments.seller_protection_disabled`) }}</option>
          </select>

          <label for="frontend_label" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
            {{ $t(`dashboard.pages.payments.seller_protection_enable`) }}
          </label>
        </div>
      </div>
    </div>
</template>

<script>
import {inject} from "vue";

export default {
    name: "PayPalPaymentConfig",
    setup(props) {
        const config = inject('config')

        return {
            config
        }
    }
}
</script>
