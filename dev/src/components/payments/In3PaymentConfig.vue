<template>
    <div>
        <div class="p-5 space-y-5">
            <div class="space-y-2">
                <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.in3.version.label`) }}</h2>
            </div>

            <div class="relative">
                <select class="w-full rounded-lg border border-gray-300 p-2.5 peer" v-model="config.version">
                    <option value="V3">{{ $t(`dashboard.pages.payments.in3.version.v3`) }}</option>
                    <option value="V2">{{ $t(`dashboard.pages.payments.in3.version.v2`) }}</option>
                </select>

                <label for="frontend_label" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
                  {{ $t(`dashboard.pages.payments.in3.version.label`) }}
                </label>
            </div>
        </div>
    </div>
  <FinancialWarning/>
</template>

<script>
import { inject } from "vue";
import FinancialWarning from "../fields/FinancialWarning.vue";

export default {
    name: "In3PaymentConfig",
    components: {
        FinancialWarning
    },
    setup() {
        const config = inject('config')
      return {
        config
      }
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
