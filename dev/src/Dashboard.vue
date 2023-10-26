<template>
  <div>
    <div class="md:flex">
      <Menu class="bg-fifthly lg:w-1/5 md:w-1/3 w-full md:min-h-screen" @changedView="changedView"/>

      <div class="lg:w-4/5 md:2/3 w-full">
        <Settings v-if="view === 'settings'" class="lg:flex h-full"/>
        <PaymentMethods v-if="view === 'payment_methods'" ref="paymentMethodsRef"/>
        <VerificationMethods v-if="view === 'verification_methods'" ref="paymentMethodsRef"/>
        <OrderPaymentMethods v-if="view === 'order_payment_methods'" class="md:flex h-full"/>
      </div>
    </div>
  </div>
</template>

<script>
import {ref, provide} from 'vue'

import Menu from './components/Menu.vue'
import Settings from './pages/Settings.vue'
import PaymentMethods from './pages/PaymentMethods.vue'
import OrderPaymentMethods from './pages/OrderPaymentMethods.vue'
import VerificationMethods from "./pages/VerificationMethods.vue";

export default {
  name: "Dashboard",
  props: ['token', 'baseUrl', 'adminUrl'],
  components: {
    VerificationMethods,
    Menu,
    Settings,
    PaymentMethods,
    OrderPaymentMethods,
  },
  setup(props) {
    const view = ref('settings')
    const menu = ref(null)
    const paymentMethodsRef = ref(null)

    provide('view', view)
    provide('csrfToken', props.token)
    provide('baseUrl', props.baseUrl)
    provide('adminUrl', props.adminUrl)

    console.log(props.adminUrl);
    console.log(props.baseUrl);
    const changedView = (view) => {
      if (view === 'payment_methods' && paymentMethodsRef.value) {
        paymentMethodsRef.value.selectedPayment = null
      }
      if (view === 'verification_methods' && paymentMethodsRef.value) {
        paymentMethodsRef.value.selectedVerification = null
      }
    }

    return {
      view,
      menu,
      changedView,
      paymentMethodsRef,
    }
  }
}

</script>