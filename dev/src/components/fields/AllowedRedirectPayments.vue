<template>
  <div class="p-5 space-y-5">
    <div class="space-y-2">
      <h2 class="font-semibold text-sm">{{ $t('dashboard.pages.payments.allowed_redirect_payments') }}</h2>
      <div class="text-gray-400 text-xs">{{ $t('dashboard.pages.payments.allowed_redirect_payments_label') }}</div>
    </div>

    <div class="relative rounded-lg border border-gray-300 max-h-96 overflow-y-auto">
      <div class="relative">
        <input
            id="search-redirect-payment"
            v-model="query"
            class="bk-no-close block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent appearance-none focus:outline-none focus:ring-0 focus:border-fifthly peer"
            placeholder=" "
            type="text"
        />
        <label
            class="bk-no-close absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-fifthly peer-placeholder-shown:scale-100 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1"
            for="search-redirect-payment"
        >
          {{ $t('dashboard.pages.payments.search_payment_method') }}
        </label>
      </div>

      <ul class="text-sm">
        <li
            v-for="payment in filteredPayments"
            :key="payment.name"
            class="p-3 flex space-x-2 cursor-pointer items-center"
            :class="isSelected(payment.name)
              ? 'bg-primary text-fifthly'
              : 'hover:bg-gray-200 hover:text-gray-700'"
            @click="toggle(payment.name)"
        >
          <img
              v-if="payment.icon"
              :src="`${baseUrl}/modules/buckaroo3/views/img/buckaroo/Payment methods/SVG/${payment.icon}`"
              :alt="labelFor(payment)"
              class="w-4"
          />
          <span class="block">{{ labelFor(payment) }}</span>
        </li>
        <li v-if="filteredPayments.length === 0" class="p-3 text-gray-500">
          {{ $t('dashboard.pages.payments.no_payment_methods_available') }}
        </li>
      </ul>
    </div>
  </div>
</template>

<script>
import { computed, inject, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const EXCLUDED = ['giftcard', 'idin'];

export default {
  name: 'AllowedRedirectPayments',
  props: {
    payments: {
      type: Array,
      default: () => [],
    },
  },
  setup(props) {
    const { t } = useI18n();
    const config = inject('config');
    const baseUrl = inject('baseUrl');
    const query = ref('');

    const labelFor = (payment) => {
      if (payment.name === 'ideal') {
        return 'iDEAL | Wero';
      }

      return t(`payment_methods.${payment.name}`);
    };

    const selectedNames = () => {
      const value = config.value?.allowed_payments;
      if (Array.isArray(value)) {
        return value.map((item) => (typeof item === 'string' ? item : item?.name)).filter(Boolean);
      }
      if (typeof value === 'string' && value !== '') {
        return value.split(',').map((item) => item.trim()).filter(Boolean);
      }

      return [];
    };

    const availablePayments = computed(() => {
      return (props.payments || []).filter((payment) => payment && !EXCLUDED.includes(payment.name));
    });

    const filteredPayments = computed(() => {
      const term = query.value.trim().toLowerCase();
      if (term === '') {
        return availablePayments.value;
      }

      return availablePayments.value.filter((payment) => {
        return labelFor(payment).toLowerCase().includes(term) || payment.name.toLowerCase().includes(term);
      });
    });

    const isSelected = (name) => selectedNames().includes(name);

    const toggle = (name) => {
      const names = selectedNames();
      config.value.allowed_payments = isSelected(name)
        ? names.filter((item) => item !== name)
        : [...names, name];
    };

    return {
      baseUrl,
      query,
      filteredPayments,
      labelFor,
      isSelected,
      toggle,
    };
  },
};
</script>
