<template>
    <div>
        <ToggleField class="p-5 space-y-5" v-model="config.seperateAuthorizeAndCapture" id="seperateAuthorizeAndCapture">
            <div>
                <label for="seperateAuthorizeAndCapture" class="font-semibold text-sm">
                    {{ $t(`dashboard.pages.payments.seperate_authorize_and_capture`) }}
                </label>
                <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.seperate_authorize_and_capture_label`) }}</div>
            </div>
        </ToggleField>

        <ToggleField class="p-5 space-y-5" v-model="config.isEncrypted">
            <div>
                <label for="client-side-mode" class="font-semibold text-sm">
                    {{ $t(`dashboard.pages.payments.client_side`) }}
                </label>
                <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.client_side_label`) }}</div>
            </div>
        </ToggleField>

        <div class="p-5 space-y-5">
          <div class="space-y-2">
            <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.display_type`) }}</h2>
            <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.display_type_label`) }}</div>
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

<!--        <ToggleField class="p-5 space-y-5" v-model="config.payWithSecurityCode" id="payWithSecurityCode">-->
<!--            <div>-->
<!--                <label for="payWithSecurityCode" class="font-semibold text-sm">-->
<!--                    {{ $t(`dashboard.pages.payments.pay_w_security_code`) }}-->
<!--                </label>-->
<!--                <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.pay_w_security_code_label`) }}</div>-->
<!--            </div>-->
<!--        </ToggleField>-->

        <ActiveCreditcards v-model="config.activeCreditcards"/>
    </div>
</template>

<script>
import { inject } from "vue";
import ToggleField from '../fields/ToggleField.vue'
import ActiveCreditcards from '../fields/ActiveCreditcards.vue'

export default {
    name: "CreditCardPaymentConfig",
    components: {
        ToggleField,
        ActiveCreditcards
    },
    setup(props) {
        const config = inject('config')

        return {
            config
        }
    }
}
</script>
