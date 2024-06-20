<template>
    <div>

        <div class="p-5 space-y-5">
          <div class="space-y-2">
            <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.display_type`) }}</h2>
            <div class="text-gray-400 text-xs" v-html="$t(`dashboard.pages.payments.display_type_label`)"></div>
          </div>

          <div class="flex rounded shadow border justify-between md:w-80 w-full overflow-hidden font-bold md:text-sm text-xs">
            <button class="w-1/2 h-12 space-x-1 hover:bg-green-500 hover:text-white"
                    v-bind:class="{'bg-green-500 text-white': config.display_type === 'radio' }"
                    @click="config.display_type = 'radio'">
              <i v-if="config.display_type === 'radio'" class="fas fa-check"></i>
              <span>{{ $t(`dashboard.pages.payments.display_types.radio`) }}</span>
            </button>
            <button class="w-1/2 h-12 space-x-1 hover:bg-blue-500 hover:text-white"
                    v-bind:class="{'bg-blue-500 text-white': config.display_type === 'dropdown' }"
                    @click="config.display_type = 'dropdown'">
              <i v-if="config.display_type === 'dropdown'" class="fas fa-check"></i>
              <span>{{ $t(`dashboard.pages.payments.display_types.dropdown`) }}</span>
            </button>
          </div>
        </div>

        <ActiveCreditcards v-model="config.activeCreditcards"/>

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
import { inject, computed } from "vue";
import ToggleField from '../fields/ToggleField.vue'
import { useI18n } from "vue-i18n";
import ActiveCreditcards from '../fields/ActiveCreditcards.vue'

export default {
    name: "CreditCardPaymentConfig",
    components: {
        ToggleField,
        ActiveCreditcards
    },
    setup(props) {
      const { t } = useI18n();

      const displayOptions = [
        { text: t('Grouped'), value: 'grouped' }
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
