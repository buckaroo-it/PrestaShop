<template>
  <div class="md:p-8 p-3 w-full">
    <div class="flex flex-col bg-white shadow rounded h-full overflow-hidden">
      <div v-if="!selectedVerification" class="p-5 border-b space-y-1">
        <h1 class="font-bold md:text-2xl text-lg">{{ $t('dashboard.pages.payments.verification_methods') }}</h1>
        <div class="text-gray-400 md:text-sm text-xs">{{ $t('dashboard.pages.payments.configure_your_verification_method_settings') }}</div>
      </div>

      <div v-else class="p-5 border-b space-y-1 flex space-x-2 items-center">
        <button @click="selectedVerification = null" class="hover:bg-gray-100 w-9 h-9 rounded-full"><i class="fas fa-chevron-left"></i></button>

        <div>
          <h1 class="font-bold md:text-2xl text-lg">{{ $t(`verification_methods.${ selectedVerification.name }`) }}</h1>
          <div class="text-gray-400 md:text-sm text-xs">{{ $t('dashboard.pages.payments.configure_your_verification_name_settings', {
            'verification_name': $t(`verification_methods.${ selectedVerification.name }`)
          }) }}</div>
        </div>
      </div>

      <div v-if="!selectedVerification" class="grid lg:grid-cols-5 md:grid-cols-3 grid-cols-2 gap-4 p-5 overflow-y-auto">
        <VerificationMethodBlock  v-for="payment in payments" :payment="payment" @selectPayment="selectedVerification = payment" />
      </div>

      <loading v-if="loading" class="py-5" />

      <div v-if="selectedVerification" class="overflow-y-scroll h-full">
        <DefaultVerificationConfig :payment="selectedVerification">
        </DefaultVerificationConfig>
      </div>
    </div>
  </div>
</template>

<script>
import { ref } from "vue";
import { useApi } from "../lib/api.ts"

import CountrySelect from "../components/CountrySelect.vue";
import Loading from "../components/Loading.vue";
import VerificationMethodBlock from "../components/VerificationMethodBlock.vue";
import DefaultVerificationConfig from "../components/verifications/DefaultVerificationConfig.vue";

export default {
  name: "VerificationMethods",
  components: {
    DefaultVerificationConfig,
    VerificationMethodBlock,
    Loading,
    CountrySelect
  },
  setup() {
    const payments = ref([])
    const { get, data, loading } = useApi('buckaroo_config_verificationMethods')
    const selectedVerification = ref(null)

    const getPayments = () => {
      get().then(() => {
        if(data.value.status) {
          payments.value = data.value.payments
        }
      })
    }

    getPayments()

    return {
      payments,
      loading,
      selectedVerification,
      getPayments
    }
  }
}
</script>