<template>
    <div class="p-5 space-y-5">
      <div class="space-y-2">
        <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.customer.type_label`) }}</h2>
      </div>

      <div class="relative">
        <select class="w-full rounded-lg border border-gray-300 p-2.5 peer" v-model="config.customer_type">
          <option v-for="option in customerTypeOptions" :key="option.value" :value="option.value">{{ option.text }}</option>
        </select>

        <label for="frontend_label" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
          {{ $t(`dashboard.pages.payments.customer_type`) }}
        </label>
      </div>
    </div>

    <div class="p-5 space-y-5" v-if="config.customer_type === 'both' || config.customer_type === 'b2b'">
      <div class="space-y-2">
        <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.order_amount_allowed`) }} ({{ $t(`dashboard.pages.payments.customer.type.b2b`) }})</h2>
        <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.order_amount_allowed_label`) }}.</div>
      </div>

      <div class="flex space-x-5">
        <div class="relative w-full">
          <input type="number" id="min_b2b_order_amount" class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-primary peer" placeholder=" " v-model="config.min_order_amount" />
          <label for="min_b2b_order_amount" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
            {{ $t(`dashboard.pages.payments.min_order_amount_b2b`)}}
          </label>
        </div>

        <div class="relative w-full">
          <input type="number" id="max_b2b_order_amount" class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-primary peer" placeholder=" " v-model="config.max_order_amount" />
          <label for="max_b2b_order_amount" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
            {{ $t(`dashboard.pages.payments.max_order_amount_b2b`)}}
          </label>
        </div>
      </div>
    </div>
    <FinancialWarning />
</template>

<script>
import {inject} from 'vue'
import {useI18n} from "vue-i18n";
import FinancialWarning from "../fields/FinancialWarning.vue";
export default {
    name: "AfterpayPaymentConfig",
    components: {
        FinancialWarning
    },
    setup(props) {
      const { t } = useI18n();
      const config = inject('config')


        const customerTypeOptions = [
          { text: t('dashboard.pages.payments.customer.type.both'), value: 'both' },
          { text: `${t('dashboard.pages.payments.customer.type.b2c')} (${t('dashboard.pages.payments.customer.type.b2c.long')})`, value: 'B2C' },
          { text: `${t('dashboard.pages.payments.customer.type.b2b')} (${t('dashboard.pages.payments.customer.type.b2b.long')})`, value: 'B2B' },
        ];

        return {
          config,
          customerTypeOptions,
        }
    }
}
</script>
