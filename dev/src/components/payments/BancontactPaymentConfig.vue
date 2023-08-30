<template>
    <div>
        <ToggleField class="p-5 space-y-5" v-model="config.isEncrypted">
            <div>
                <label for="client-side-mode" class="font-semibold text-sm">
                    Client Side
                </label>
                <div class="text-gray-400 text-xs">Enable or disable client sided payment method behavior.</div>
            </div>
        </ToggleField>
    </div>
</template>

<script>
import { inject } from "vue"
import ToggleField from '../fields/ToggleField.vue'

export default {
    name: "BancontactPaymentConfig",
    components: {
        ToggleField,
    },
    setup(props) {
        const config = inject('config')

        return {
            config
        }
    }
}
</script>
