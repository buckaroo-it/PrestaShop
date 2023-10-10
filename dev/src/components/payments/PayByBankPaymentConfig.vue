<template>
    <div>
      <div class="p-5 space-y-5">
        <div class="space-y-2">
          <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.display_type`) }}</h2>
          <div class="text-gray-400 text-xs" v-html="$t(`dashboard.pages.payments.display_type_label`)"></div>
        </div>

        <div class="flex rounded shadow border justify-between md:w-80 w-full overflow-hidden font-bold md:text-sm text-xs">
          <button class="w-1/2 h-12 space-x-1 hover:bg-green-500 hover:text-white"
                  v-bind:class="{'bg-green-500 text-white': config.display_type === 'radio' }"
                  @click="config.display_type = 'radio'">
            <i v-if="config.display_type === 'radio'" class="far fa-check"></i>
            <span>{{ $t(`dashboard.pages.payments.display_types.radio`) }}</span>
          </button>
          <button class="w-1/2 h-12 space-x-1 hover:bg-blue-500 hover:text-white"
                  v-bind:class="{'bg-blue-500 text-white': config.display_type === 'dropdown' }"
                  @click="config.display_type = 'dropdown'">
            <i v-if="config.display_type === 'dropdown'" class="far fa-check"></i>
            <span>{{ $t(`dashboard.pages.payments.display_types.dropdown`) }}</span>
          </button>
        </div>
      </div>
    </div>
</template>

<script>
import {inject} from "vue";

export default {
    name: "PayByBankPaymentConfig",
    setup(props) {
        const config = inject('config')

        return {
            config
        }
    }
}
</script>

<style scoped>

</style>
