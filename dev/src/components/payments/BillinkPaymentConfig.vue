<template>
    <div>
        <div class="p-5 space-y-5">
          <div class="space-y-2">
            <h2 class="font-semibold text-sm">Vat type for wrapping</h2>
          </div>

          <div class="relative">
            <select class="w-full rounded-lg border border-gray-300 p-2.5 peer" v-model="config.wrapping_vat">
              <option v-for="option in vatOptions" :key="option.value" :value="option.value">{{ option.text }}</option>
            </select>

            <label for="frontend_label" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
              Please select  vat type for wrapping
            </label>
          </div>
        </div>

        <div class="p-5 space-y-5">
            <div class="space-y-2">
                <h2 class="font-semibold text-sm">Customer type</h2>
            </div>

            <div class="relative">
                <select class="w-full rounded-lg border border-gray-300 p-2.5 peer" v-model="config.customer_type">
                    <option value="both">Both</option>
                    <option value="B2C">B2C (Business-to-consumer)</option>
                    <option value="B2B">B2B (Business-to-Business)</option>
                </select>

                <label for="frontend_label" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
                    Customer type
                </label>
            </div>
        </div>

        <div class="p-5 space-y-5" v-if="config.customer_type === 'both' || config.customer_type === 'b2b'">
            <div class="space-y-2">
                <h2 class="font-semibold text-sm">Order amount allowed (B2B)</h2>
                <div class="text-gray-400 text-xs">This method will only be shown when this condition is met.</div>
            </div>

            <div class="flex space-x-5">
                <div class="relative w-full">
                    <input type="number" id="min_b2b_order_amount" class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-primary peer" placeholder=" " v-model="config.min_order_amount" />
                    <label for="min_b2b_order_amount" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
                        Minimum B2B order amount
                    </label>
                </div>

                <div class="relative w-full">
                    <input type="number" id="max_b2b_order_amount" class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-primary peer" placeholder=" " v-model="config.max_order_amount" />
                    <label for="max_b2b_order_amount" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
                        Maximum B2B order amount
                    </label>
                </div>
            </div>

        </div>
    </div>
</template>

<script>
import { inject } from "vue";
import { useI18n } from "vue-i18n";

export default {
  name: "BillinkPaymentConfig",
  setup(props) {
    const { t } = useI18n();

    const config = inject('config');

    const vatOptions = [
      { text: t('1 = High rate'), value: '1' },
      { text: t('2 = Low rate'), value: '2' },
      { text: t('3 = Zero rate'), value: '3' },
      { text: t('4 = Null rate'), value: '4' },
      { text: t('5 = Middle rate'), value: '5' },
    ];

    return {
      config,
      vatOptions
    }
  }
}
</script>