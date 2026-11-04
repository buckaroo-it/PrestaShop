<template>
  <div class="md:pb-8 w-full">
    <div class="flex flex-col bg-white shadow rounded-2xl h-full">
      <PageHeader v-if="!selectedPayment"
                  :title="$t('dashboard.pages.payments.payment_methods')"
                  :subtitle="payments.length ? $t('dashboard.pages.payments.active_summary', { active: activeTotal, total: payments.length }) : $t('dashboard.pages.payments.configure_your_payment_method_settings')">
        <template v-if="payments.length" #actions>
          <div class="relative sm:w-60 w-full">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs" aria-hidden="true"></i>
            <input type="search" v-model="search"
                   :placeholder="$t('dashboard.pages.payments.search_placeholder')"
                   :aria-label="$t('dashboard.pages.payments.search_placeholder')"
                   class="w-full h-9 pl-8 pr-3 text-sm rounded-lg border border-gray-300 bg-white focus:outline-none focus:border-fifthly"/>
          </div>

          <div class="inline-flex self-start sm:self-auto rounded-lg border border-gray-200 bg-gray-50 p-1 text-xs" role="group">
            <button v-for="option in filterOptions" :key="option.value" type="button"
                    class="px-3 py-1 rounded-md font-semibold whitespace-nowrap focus:outline-none focus-visible:ring-2 focus-visible:ring-fifthly"
                    :class="modeFilter === option.value ? 'bg-white shadow text-fifthly' : 'text-gray-500 hover:text-fifthly'"
                    :aria-pressed="modeFilter === option.value"
                    @click="modeFilter = option.value">
              {{ option.label }} <span class="text-gray-400 font-normal">{{ counts[option.value] }}</span>
            </button>
          </div>
        </template>
      </PageHeader>

      <div v-else class="px-5 py-4 border-b flex gap-3 items-center">
        <button type="button" @click="goBack" class="shrink-0 hover:bg-gray-100 w-9 h-9 rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-fifthly"
                :aria-label="$t('dashboard.common.back')"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>

        <img v-if="selectedPayment.icon" :src="`${baseUrl}/modules/buckaroo3/views/img/buckaroo/Payment methods/SVG/${ selectedPayment.icon }`" alt="" class="w-10 shrink-0" />

        <div>
          <h1 class="m-0 font-bold md:text-xl text-lg">
            {{ selectedPayment.name === 'ideal' ? 'iDEAL | Wero' : $t(`payment_methods.${ selectedPayment.name }`) }}
          </h1>
          <div class="text-gray-400 md:text-sm text-xs">
            {{
              $t('dashboard.pages.payments.configure_your_payment_name_settings', {
                'payment_name': selectedPayment.name === 'ideal' ? 'iDEAL | Wero' : $t(`payment_methods.${ selectedPayment.name }`)
              })
            }}
          </div>
        </div>
      </div>

      <div v-if="!selectedPayment" class="grid grid-cols-[repeat(auto-fill,minmax(150px,1fr))] gap-4 p-5 overflow-y-auto">
        <PaymentMethodBlock v-for="payment in filteredPayments" :key="payment.name" :payment="payment" @selectPayment="selectedPayment = payment" />
      </div>

      <div v-if="!selectedPayment && !loading && payments.length && !filteredPayments.length" class="px-5 pb-8 text-center text-sm text-gray-400">
        {{ $t('dashboard.pages.payments.no_results') }}
      </div>

      <loading v-if="loading" class="py-5" />

      <div v-if="selectedPayment">
        <DefaultPaymentConfig :payment="selectedPayment">
          <AfterpayPaymentConfig v-if="selectedPayment.name === 'afterpay'"  />
          <BillinkPaymentConfig v-if="selectedPayment.name === 'billink'" />
          <CreditCardPaymentConfig v-if="selectedPayment.name === 'creditcard'" />
          <In3PaymentConfig v-if="selectedPayment.name === 'in3'" />
          <PayPalPaymentConfig v-if="selectedPayment.name === 'paypal'" />
          <PayByBankPaymentConfig v-if="selectedPayment.name === 'paybybank'" />
          <PayPerEmailPaymentConfig v-if="selectedPayment.name === 'payperemail'" :payments="payments" />
          <TransferPaymentConfig v-if="selectedPayment.name === 'transfer'" />
          <GiftcardPaymentConfig v-if="selectedPayment.name === 'giftcard'" :payments="payments" />
          <KlarnaPaymentConfig v-if="selectedPayment.name === 'klarna'" />
          <GooglePayPaymentConfig v-if="selectedPayment.name === 'googlepay'" />
          <ClickToPayPaymentConfig v-if="selectedPayment.name === 'clicktopay'" />
        </DefaultPaymentConfig>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, watch, inject } from "vue";
import { writeRoute } from "../lib/route";
import { useI18n } from "vue-i18n";
import { useApi } from "../lib/api.ts"

import CountrySelect from "../components/CountrySelect.vue";
import DefaultPaymentConfig from "../components/payments/DefaultPaymentConfig.vue";
import AfterpayPaymentConfig from "../components/payments/AfterpayPaymentConfig.vue";
import TransferPaymentConfig from "../components/payments/TransferPaymentConfig.vue";
import CreditCardPaymentConfig from "../components/payments/CreditCardPaymentConfig.vue";
import BancontactPaymentConfig from "../components/payments/BancontactPaymentConfig.vue";
import BillinkPaymentConfig from "../components/payments/BillinkPaymentConfig.vue";
import In3PaymentConfig from '../components/payments/In3PaymentConfig.vue';
import PayPerEmailPaymentConfig from '../components/payments/PayPerEmailPaymentConfig.vue';
import PayByBankPaymentConfig from "../components/payments/PayByBankPaymentConfig.vue";
import PaymentMethodBlock from "../components/PaymentMethodBlock.vue";
import PageHeader from "../components/PageHeader.vue";
import Loading from "../components/Loading.vue";
import PayPalPaymentConfig from "../components/payments/PayPalPaymentConfig.vue";
import GiftcardPaymentConfig  from "../components/payments/GiftcardPaymentConfig.vue";
import KlarnaPaymentConfig from "../components/payments/KlarnaPaymentConfig.vue";
import GooglePayPaymentConfig from "../components/payments/GooglePayPaymentConfig.vue";
import ClickToPayPaymentConfig from "../components/payments/ClickToPayPaymentConfig.vue";

export default {
  name: "PaymentMethods",
  components: {
    PayPalPaymentConfig,
    PayByBankPaymentConfig,
    Loading,
    AfterpayPaymentConfig,
    DefaultPaymentConfig,
    CountrySelect,
    BancontactPaymentConfig,
    BillinkPaymentConfig,
    CreditCardPaymentConfig,
    In3PaymentConfig,
    TransferPaymentConfig,
    PayPerEmailPaymentConfig,
    PaymentMethodBlock,
    PageHeader,
    GiftcardPaymentConfig,
    KlarnaPaymentConfig,
    GooglePayPaymentConfig,
    ClickToPayPaymentConfig
  },
  setup() {
    const payments = ref([])
    const { get, data, loading } = useApi('buckaroo_config_methods')
    const selectedPayment = ref(null)
    const { t } = useI18n()

    // Search and filter the payment method tiles
    const search = ref('')
    const modeFilter = ref('all')
    const displayName = (payment) => payment.name === 'ideal' ? 'iDEAL | Wero' : t(`payment_methods.${payment.name}`)

    const matchesSearch = (payment) => {
      const query = search.value.trim().toLowerCase()
      return !query || displayName(payment).toLowerCase().includes(query) || payment.name.toLowerCase().includes(query)
    }

    const counts = computed(() => {
      const visible = payments.value.filter(matchesSearch)
      return {
        all: visible.length,
        live: visible.filter(p => p.mode === 'live').length,
        test: visible.filter(p => p.mode === 'test').length,
        off: visible.filter(p => p.mode !== 'live' && p.mode !== 'test').length,
      }
    })

    const activeTotal = computed(() => payments.value.filter(p => p.mode === 'live' || p.mode === 'test').length)

    const filteredPayments = computed(() => payments.value.filter(payment => {
      if (!matchesSearch(payment)) return false
      if (modeFilter.value === 'all') return true
      if (modeFilter.value === 'off') return payment.mode !== 'live' && payment.mode !== 'test'
      return payment.mode === modeFilter.value
    }))

    const filterOptions = computed(() => [
      { value: 'all', label: t('dashboard.pages.payments.filter_all') },
      { value: 'live', label: t('dashboard.pages.payments.modes_settings.live') },
      { value: 'test', label: t('dashboard.pages.payments.modes_settings.test') },
      { value: 'off', label: t('dashboard.pages.payments.modes_settings.off') },
    ])

    const baseUrl = inject('baseUrl')
    const hasUnsavedChanges = inject('hasUnsavedChanges', ref(false))
    const initialRouteDetail = inject('initialRouteDetail', ref(null))

    // Keep the open payment method in the URL so a refresh reopens it
    watch(selectedPayment, (payment) => writeRoute('payment_methods', payment ? payment.name : null))

    const goBack = () => {
      if (hasUnsavedChanges.value && !window.confirm(t('dashboard.common.leave_unsaved_confirm'))) {
        return
      }
      hasUnsavedChanges.value = false
      selectedPayment.value = null
    }

    const getPayments = () => {
      get().then(() => {
        if(data.value.status) {
          payments.value = data.value.payments

          if (initialRouteDetail.value) {
            selectedPayment.value = payments.value.find(payment => payment.name === initialRouteDetail.value) ?? null
            initialRouteDetail.value = null
          }
        }
      })
    }

    getPayments()

    return {
      payments,
      loading,
      selectedPayment,
      getPayments,
      goBack,
      baseUrl,
      search,
      modeFilter,
      counts,
      filteredPayments,
      activeTotal,
      filterOptions
    }
  }
}
</script>
