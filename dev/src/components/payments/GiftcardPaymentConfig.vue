<template>
  <div>
    <ActiveGiftcards v-model="config.activeGiftcards"/>
  </div>
</template>

<script>
import { inject } from "vue";
import ToggleField from '../fields/ToggleField.vue'
import ActiveGiftcards from '../fields/ActiveGiftcards.vue'

export default {
  name: "GiftcardPaymentConfig",
  components: {
    ToggleField,
    ActiveGiftcards
  },
  setup(props) {

    const config = inject('config')

    return {
      config
    }
  }
}
</script>
