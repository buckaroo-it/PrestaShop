<template>
  <div class="px-5 space-y-5">
    <div class="space-y-2">
      <h2 class="font-semibold text-sm">{{ $t(`dashboard.config.financial_warning`) }}</h2>
      <div class="text-gray-400 text-xs">{{ $t(`dashboard.config.financial_warning.label`) }}</div>
    </div>
    <div class="flex rounded shadow border justify-between md:w-80 w-full overflow-hidden font-bold md:text-sm text-xs">
      <button class="w-1/2 h-12 space-x-1 hover:bg-green-500 hover:text-white" v-bind:class="{'bg-green-500 text-white': config.financial_warning }" @click="config.financial_warning = true">
        <i v-if="config.financial_warning" class="far fa-check"></i>
        <span>{{ $t(`dashboard.config.financial_warning.options.enabled`) }}</span>
      </button>
      <button class="w-1/2 h-12 space-x-1 hover:bg-blue-500 hover:text-white" v-bind:class="{'bg-blue-500 text-white': !config.financial_warning}" @click="config.financial_warning = false">
        <i v-if="!config.financial_warning" class="far fa-check"></i>
        <span>{{ $t(`dashboard.config.financial_warning.options.disabled`) }}</span>
      </button>
    </div>
  </div>
</template>

<script>
import {inject} from "vue";

export default {
  name:'FinancialWarning.vue',
  props: {
    enabled: {
      type: Boolean,
      default: true
    }
  },
  setup(props) {
    const config = inject('config')

    return {
      config
    }
  }
}
</script>
