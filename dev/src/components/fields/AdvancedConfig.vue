<template>

  <div class="px-5 space-y-5">
    <div class="space-y-2">
      <h2 class="font-semibold text-sm">{{ $t(`dashboard.advanced_config.title`) }}</h2>
    </div>
  </div>
</template>

<script>

export default {
  name:'AdvancedPaymentConfig.vue',
  props: ['payment'],

  setup(props, { emit }) {
    const configuration = (config) => {
      console.log(config)
    }

    return {
      configuration
    }
  }
}

</script>
