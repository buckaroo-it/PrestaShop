<template>
</template>

<script>
import {computed, inject} from "vue";

export default {
    name: "IdealPaymentConfig",
    setup(props) {
        const config = inject('config')
        const showIssuers = computed({
          get() {
            return config.value.show_issuers !== false
          },
          set(value) {
            config.value.show_issuers = !!value
          }
        })
        return {
            config,
            showIssuers
        }
    }
}
</script>
