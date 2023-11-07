<template>
  <FinancialWarning @set-warning="setWarning" :enabled="config.financial_warning" />
</template>

<script>
import { inject } from "vue";
import FinancialWarning from "../fields/FinancialWarning.vue";

export default {
  name: "TinkaPaymentConfig.vue",
  components: {
    FinancialWarning
  },
  methods: {
    setWarning(value) {
      this.config.financial_warning = value
    }
  },
  setup(props) {
    const config = inject('config')

    return {
      config,
    }
  }
}
</script>
