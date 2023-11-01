<template>
  <div class="relative rounded-lg border border-gray-300" v-if="payment.mode ==='klarna'">
    <label for="country" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
      {{ $t(`dashboard.advanced_config.title`) }}
    </label>
  <div>

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
