<template>
  <FinancialWarning @set-warning="setWarning" :enabled="config.financial_warning" />
</template>

<script>
import { inject } from "vue";
import FinancialWarning from "../fields/FinancialWarning.vue";

export default {
  name: "KlarnaPaymentConfig.vue",
  components: {
    FinancialWarning
  },
  methods: {
    setWarning(value) {
      this.config.financial_warning = value
    }
  },
  data() {
    return {
      config: inject('config')
    }
  },
}
</script>
