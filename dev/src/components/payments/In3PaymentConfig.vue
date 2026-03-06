<template>
  <div>
    <FinancialWarning/>
  </div>
</template>

<script>
import FinancialWarning from "../fields/FinancialWarning.vue";

export default {
  name: "In3PaymentConfig",
  components: {
    FinancialWarning
  }
}
</script>

<style scoped>
img {
  width: 100px;
}


.hidden-radio:checked + label {
  border: 2px solid #007bff;
  padding: 5px;
  border-radius: 8px;
}

.radio-image-wrapper {
  position: relative;
}

.radio-image-wrapper label {
  transition: border 0.2s;
}

.hidden-radio:checked + label::after {
  content: '✓';
  position: absolute;
  top: 0;
  right: 0;
  background-color: #007bff;
  color: white;
  padding: 2px 6px;
  border-radius: 50%;
}

.hidden-radio {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
  margin: 0;
  padding: 0;
  z-index: -1;
}
</style>
