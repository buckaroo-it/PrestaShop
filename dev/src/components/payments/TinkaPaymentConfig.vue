<template>
  <FinancialWarning  />
</template>

<script>
import { inject } from "vue";
import FinancialWarning from "../fields/FinancialWarning.vue";

export default {
  name: "TinkaPaymentConfig.vue",
  components: {
    FinancialWarning
  }
}
</script>
