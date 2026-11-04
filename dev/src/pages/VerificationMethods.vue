<template>
  <div class="md:pb-8 w-full">
    <div class="flex flex-col bg-white shadow rounded-2xl h-full">
      <PageHeader v-if="!selectedVerification"
                  :title="$t('dashboard.pages.payments.verification_methods')"
                  :subtitle="$t('dashboard.pages.payments.configure_your_verification_method_settings')" />

      <div v-else class="px-5 py-4 border-b flex gap-3 items-center">
        <button type="button" @click="goBack" class="shrink-0 hover:bg-gray-100 w-9 h-9 rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-fifthly" :aria-label="$t('dashboard.common.back')"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>

        <img v-if="selectedVerification.icon" :src="`${baseUrl}/modules/buckaroo3/views/img/buckaroo/Identification methods/SVG/${ selectedVerification.icon }`" alt="" class="w-10 shrink-0" />

        <div>
          <h1 class="m-0 font-bold md:text-xl text-lg">{{ $t(`verification_methods.${ selectedVerification.name }`) }}</h1>
          <div class="text-gray-400 md:text-sm text-xs">{{ $t('dashboard.pages.payments.configure_your_verification_name_settings', {
            'verification_name': $t(`verification_methods.${ selectedVerification.name }`)
          }) }}</div>
        </div>
      </div>

      <div v-if="!selectedVerification" class="grid grid-cols-[repeat(auto-fill,minmax(150px,1fr))] gap-4 p-5 overflow-y-auto">
        <VerificationMethodBlock  v-for="payment in payments" :payment="payment" @selectPayment="selectedVerification = payment" />
      </div>

      <loading v-if="loading" class="py-5" />

      <div v-if="selectedVerification">
        <DefaultVerificationConfig :payment="selectedVerification">
        </DefaultVerificationConfig>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, watch, inject } from "vue";
import { useI18n } from "vue-i18n";
import { writeRoute } from "../lib/route";
import { useApi } from "../lib/api.ts"

import CountrySelect from "../components/CountrySelect.vue";
import Loading from "../components/Loading.vue";
import VerificationMethodBlock from "../components/VerificationMethodBlock.vue";
import PageHeader from "../components/PageHeader.vue";
import DefaultVerificationConfig from "../components/verifications/DefaultVerificationConfig.vue";

export default {
  name: "VerificationMethods",
  components: {
    DefaultVerificationConfig,
    VerificationMethodBlock,
    PageHeader,
    Loading,
    CountrySelect
  },
  setup() {
    const payments = ref([])
    const { get, data, loading } = useApi('buckaroo_config_verificationMethods')
    const selectedVerification = ref(null)

    const initialRouteDetail = inject('initialRouteDetail', ref(null))
    const baseUrl = inject('baseUrl')
    const hasUnsavedChanges = inject('hasUnsavedChanges', ref(false))
    const { t } = useI18n()

    const goBack = () => {
      if (hasUnsavedChanges.value && !window.confirm(t('dashboard.common.leave_unsaved_confirm'))) {
        return
      }
      hasUnsavedChanges.value = false
      selectedVerification.value = null
    }

    // Keep the open verification method in the URL so a refresh reopens it
    watch(selectedVerification, (verification) => writeRoute('verification_methods', verification ? verification.name : null))

    const getPayments = () => {
      get().then(() => {
        if(data.value.status) {
          payments.value = data.value.payments

          if (initialRouteDetail.value) {
            selectedVerification.value = payments.value.find(payment => payment.name === initialRouteDetail.value) ?? null
            initialRouteDetail.value = null
          }
        }
      })
    }

    getPayments()

    return {
      payments,
      loading,
      selectedVerification,
      getPayments,
      goBack,
      baseUrl
    }
  }
}
</script>
