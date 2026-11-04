<template>
  <div class="px-4 py-3 space-y-2">
    <div>
      <label for="billink_customer_type" class="m-0 font-semibold text-sm">{{ $t(`dashboard.pages.payments.customer_type`) }}</label>
      <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.customer_type_description`) }}</div>
    </div>

    <select id="billink_customer_type" v-model="config.customer_type"
            class="block w-full md:w-80 h-[39px] px-2.5 text-sm text-gray-900 rounded-lg border border-gray-300 bg-white shadow-none focus:outline-none focus:border-fifthly">
      <option v-for="option in customerTypeOptions" :key="option.value" :value="option.value">{{ option.text }}</option>
    </select>
  </div>
</template>

<script>
import { inject } from "vue";
import { useI18n } from "vue-i18n";
export default {
  name: "BillinkPaymentConfig",
  components: {},
  setup(props) {
    const { t } = useI18n();

    const config = inject('config');

    const customerTypeOptions = [
      { text: t('dashboard.pages.payments.customer.type.both'), value: 'both' },
      { text: `${t('dashboard.pages.payments.customer.type.b2c')} (${t('dashboard.pages.payments.customer.type.b2c.long')})`, value: 'B2C' },
      { text: `${t('dashboard.pages.payments.customer.type.b2b')} (${t('dashboard.pages.payments.customer.type.b2b.long')})`, value: 'B2B' },
    ];

    return {
      config,
      customerTypeOptions
    }
  }
}
</script>
