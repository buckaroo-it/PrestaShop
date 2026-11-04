<template>
    <div>
      <div class="p-5 space-y-5">
        <div class="space-y-2">
          <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.display_type`) }}</h2>
          <div class="text-gray-400 text-xs" v-html="$t(`dashboard.pages.payments.display_type_label`)"></div>
        </div>

        <SegmentedControl v-model="config.display_type"
                          :label="$t(`dashboard.pages.payments.display_type`)"
                          :options="[
                            { value: 'radio', label: $t(`dashboard.pages.payments.display_types.radio`) },
                            { value: 'dropdown', label: $t(`dashboard.pages.payments.display_types.dropdown`) },
                          ]"/>
      </div>
    </div>
</template>

<script>
import {inject} from "vue";
import SegmentedControl from '../fields/SegmentedControl.vue'

export default {
    name: "PayByBankPaymentConfig",
    components: {
        SegmentedControl
    },
    setup(props) {
        const config = inject('config')

        return {
            config
        }
    }
}
</script>
