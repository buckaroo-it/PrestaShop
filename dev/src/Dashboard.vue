<template>
  <div>
    <div className="md:flex">
      <Menu class="bg-fifthly lg:w-1/5 md:w-1/3 w-full md:min-h-screen" @changedView="changedView"/>

      <div  className="lg:w-4/5 md:2/3 w-full">
        <Settings v-if="view === 'settings'" class="lg:flex h-full"/>
        <VerificationMethods v-if="view === 'verification_methods'" class="lg:flex h-full"/>
        <PaymentMethods v-if="view === 'payment_methods'" ref="paymentMethodsRef"/>
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
import VerificationMethods from './pages/VerificationMethods.vue'
import ChannelSelectorModal from "./components/ChannelSelectorModal.vue"

import {useApi} from "./lib/api"

export default {
  name: "Dashboard",
  components: {
    Menu,
    Settings,
    PaymentMethods,
    OrderPaymentMethods,
    VerificationMethods
  },
  props: ['config','settings', 'jwt'],
  provide() {
    return {
      config: this.settings
    };
  },
  setup(props) {
    const view = ref('settings')
    const menu = ref(null)
    const paymentMethodsRef = ref(null)

    provide('view', view)
    provide('signedJWT', props.jwt)

    const changedView = (view) => {
      if (view === 'payment_methods' && paymentMethodsRef.value) {
        paymentMethodsRef.value.selectedPayment = null
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
<style>

</style>