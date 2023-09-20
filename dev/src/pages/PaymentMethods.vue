<template>
  <div class="md:p-8 p-3 w-full">
    <div class="flex flex-col bg-white shadow rounded h-full overflow-hidden">
      <div v-if="!selectedPayment" class="p-5 border-b space-y-1">
        <h1 class="font-bold md:text-2xl text-lg">{{ $t('dashboard.pages.payments.payment_methods') }}</h1>
        <div class="text-gray-400 md:text-sm text-xs">{{ $t('dashboard.pages.payments.configure_your_payment_method_settings') }}</div>
      </div>

      <div v-else class="p-5 border-b space-y-1 flex space-x-2 items-center">
        <button @click="selectedPayment = null" class="hover:bg-gray-100 w-9 h-9 rounded-full"><i class="fas fa-chevron-left"></i></button>

        <div>
          <h1 class="font-bold md:text-2xl text-lg">{{ $t(`payment_methods.${ selectedPayment.name }`) }}</h1>
          <div class="text-gray-400 md:text-sm text-xs">{{ $t('dashboard.pages.payments.configure_your_payment_name_settings', {
            'payment_name': $t(`payment_methods.${ selectedPayment.name }`)
          }) }}</div>
        </div>
      </div>

      <div v-if="!selectedPayment" class="grid lg:grid-cols-5 md:grid-cols-3 grid-cols-2 gap-4 p-5 overflow-y-scroll">
        <PaymentMethodBlock  v-for="payment in payments" :payment="payment" @selectPayment="selectedPayment = payment" />
      </div>

      <loading v-if="loading" class="py-5" />

      <div v-if="selectedPayment" class="overflow-y-scroll h-full">
        <DefaultPaymentConfig :payment="selectedPayment">
          <AfterpayPaymentConfig v-if="selectedPayment.name === 'afterpay'"  />
          <BancontactPaymentConfig v-if="selectedPayment.name === 'bancontact'" />
          <BillinkPaymentConfig v-if="selectedPayment.name === 'billink'" />
          <CreditCardPaymentConfig v-if="selectedPayment.name === 'creditcard'" />
          <GiftcardPaymentConfig v-if="selectedPayment.name === 'giftcard'" />
          <In3PaymentConfig v-if="selectedPayment.name === 'in3'" />
          <PayPerEmailPaymentConfig v-if="selectedPayment.name === 'payperemail'" :payments="payments" />
          <TransferPaymentConfig v-if="selectedPayment.name === 'transfer'" />
        </DefaultPaymentConfig>
      </div>
    </div>
  </div>
</template>

<script>
import { ref } from "vue";
import { useApi } from "../lib/api.ts"

import CountrySelect from "../components/CountrySelect.vue";
import DefaultPaymentConfig from "../components/payments/DefaultPaymentConfig.vue";
import AfterpayPaymentConfig from "../components/payments/AfterpayPaymentConfig.vue";
import TransferPaymentConfig from "../components/payments/TransferPaymentConfig.vue"
import CreditCardPaymentConfig from "../components/payments/CreditCardPaymentConfig.vue"
import BancontactPaymentConfig from "../components/payments/BancontactPaymentConfig.vue"
import BillinkPaymentConfig from "../components/payments/BillinkPaymentConfig.vue"
import GiftcardPaymentConfig from '../components/payments/GiftcardPaymentConfig.vue'
import In3PaymentConfig from '../components/payments/In3PaymentConfig.vue'
import PayPerEmailPaymentConfig from '../components/payments/PayPerEmailPaymentConfig.vue'
import PaymentMethodBlock from "../components/PaymentMethodBlock.vue"
import Loading from "../components/Loading.vue";

export default {
  name: "PaymentMethods",
  components: {
    Loading,
    AfterpayPaymentConfig,
    DefaultPaymentConfig,
    CountrySelect,
    BancontactPaymentConfig,
    BillinkPaymentConfig,
    CreditCardPaymentConfig,
    GiftcardPaymentConfig,
    In3PaymentConfig,
    TransferPaymentConfig,
    PayPerEmailPaymentConfig,
    PaymentMethodBlock
  },
  watch: {
    selectedChannel(value) {
      this.getPayments()
    }
  },
  setup() {
    const payments = ref([])
    const { get, data, loading } = useApi('/index.php?fc=module&module=buckaroo3&controller=paymentMethods')
    const selectedPayment = ref(null)

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
      selectedPayment,
      getPayments
    }
  }
}
</script>