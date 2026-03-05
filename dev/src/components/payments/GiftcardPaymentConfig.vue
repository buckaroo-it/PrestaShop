<template>
  <div>
    <ActiveGiftcards v-model="config.activeGiftcards"/>

    <div class="p-5 space-y-5">
      <div class="space-y-2">
        <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.displayInCheckout`) }}</h2>
        <div class="text-gray-400 text-xs" v-html="$t(`dashboard.pages.payments.displayInCheckoutDesc`)"></div>
      </div>

      <div class="relative">
        <select class="w-full rounded-lg border border-gray-300 p-2.5 peer" v-model="displayInCheckout">
          <option v-for="option in displayOptions" :key="option.value" :value="option.value">{{ option.text }}</option>
        </select>

        <label for="frontend_label" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
          {{ $t(`dashboard.pages.payments.displayInCheckout`) }}
        </label>
      </div>
    </div>
  </div>
</template>

<script>
import {computed, inject} from "vue";
import ToggleField from '../fields/ToggleField.vue'
import ActiveGiftcards from '../fields/ActiveGiftcards.vue'
import {useI18n} from "vue-i18n";

export default {
  name: "GiftcardPaymentConfig",
  components: {
    ToggleField,
    ActiveGiftcards
  },
  setup(props) {
    const { t } = useI18n();

    const displayOptions = [
      { text: t('Grouped'), value: 'grouped' },
      { text: t('Separate'), value: 'separate' }
    ];
    const config = inject('config')

    const displayInCheckout = computed({
      get() {
        return config.value.display_in_checkout || 'grouped'
      },
      set(value) {
        config.value.display_in_checkout = value
      }
    })

    return {
      config,
      displayInCheckout,
      displayOptions
    }
  }
}
</script>
