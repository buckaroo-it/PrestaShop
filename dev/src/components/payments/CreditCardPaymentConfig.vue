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
