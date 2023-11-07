<template>
  <div class="px-5 space-y-5">
    <div class="space-y-2">
      <h2 class="font-semibold text-sm">{{ $t(`dashboard.config.financial_warning`) }}</h2>
      <div class="text-gray-400 text-xs">{{ $t(`dashboard.config.financial_warning.label`) }}</div>
    </div>
    <div class="flex rounded shadow border justify-between md:w-80 w-full overflow-hidden font-bold md:text-sm text-xs">
      <button class="w-1/2 h-12 space-x-1 hover:bg-blue-400 hover:text-white" v-bind:class="{'bg-blue-500 text-white hover:bg-blue-500': enabled === 1 }" @click="setWarning(1)">
        <i v-if="enabled === 1" class="far fa-check"></i>
        <span>{{ $t(`dashboard.config.financial_warning.options.enabled`) }}</span>
      </button>
      <button class="w-1/2 h-12 space-x-1 hover:bg-red-400 hover:text-white" v-bind:class="{'bg-red-500 text-white hover:bg-red-500': enabled === 0 }" @click="setWarning(0)">
        <i v-if="enabled === 0" class="far fa-check"></i>
        <span>{{ $t(`dashboard.config.financial_warning.options.disabled`) }}</span>
      </button>
    </div>
  </div>
</template>

<script>
export default {
  name:'FinancialWarning.vue',
  props: {
    enabled: {
      type: Number,
      default: 1
    }
  },
  methods: {
    setWarning(value) {
      this.$emit('set-warning', value)
    }
  },
}
</script>
