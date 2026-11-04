<template>
  <div>
    <div class="xl:flex xl:gap-4">
      <Menu class="bg-fifthly xl:w-64 w-full xl:shrink-0 xl:self-start xl:sticky xl:top-[141px] xl:h-[calc(100vh-157px)] xl:overflow-y-auto shadow rounded-2xl overflow-hidden xl:pb-4 xl:mb-0 mb-4" @changedView="changedView"/>

      <div class="xl:flex-1 min-w-0 w-full">
        <Settings v-if="view === 'settings'" class="2xl:flex h-full"/>
        <PaymentMethods v-if="view === 'payment_methods'" ref="paymentMethodsRef"/>
        <VerificationMethods v-if="view === 'verification_methods'" ref="paymentMethodsRef"/>
        <OrderPaymentMethods v-if="view === 'order_payment_methods'" class="2xl:flex h-full"/>
      </div>
    </div>
  </div>
</template>

<script>
import {ref, provide, watch} from 'vue'
import { readRoute, writeRoute } from './lib/route'

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
    const initialRoute = readRoute()
    const view = ref(initialRoute.view)
    // The payment/verification method to reopen after a refresh, used once by those pages
    provide('initialRouteDetail', ref(initialRoute.detail))
    watch(view, (newView) => writeRoute(newView))
    writeRoute(view.value, initialRoute.detail)
    const menu = ref(null)
    const paymentMethodsRef = ref(null)

    provide('view', view)
    provide('hasUnsavedChanges', ref(false))
    provide('csrfToken', props.token)
    provide('baseUrl', props.baseUrl)
    provide('adminUrl', props.adminUrl)
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
