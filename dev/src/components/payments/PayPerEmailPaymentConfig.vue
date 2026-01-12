<template>
    <div>
        <div class="p-5 space-y-5">
            <div class="space-y-2">
                <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.due_date`) }}</h2>
            </div>

            <div class="relative">
                <input type="number" id="frontend_label" class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-primary peer" placeholder=" " v-model="config.due_days" />

                <label for="frontend_label" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
                    {{ $t(`dashboard.pages.payments.due_date`) }}
                </label>
            </div>
        </div>

        <div class="p-5 space-y-5">
            <div class="space-y-2">
                <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.allowed_methods`) }}</h2>
                <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.allowed_methods_label`) }}</div>
            </div>

            <div class="relative rounded-lg border border-gray-300 max-h-96 overflow-y-auto p-3">
                <div class="space-y-2">
                    <label 
                        v-for="payment in payments" 
                        :key="payment.name"
                        class="flex items-center space-x-3 p-2 rounded hover:bg-gray-100 cursor-pointer"
                    >
                        <input 
                            type="checkbox" 
                            :value="payment.name"
                            v-model="allowedPaymentsArray"
                            class="w-4 h-4 text-primary bg-gray-100 border-gray-300 rounded focus:ring-primary focus:ring-2"
                        />
                        <span class="text-sm text-gray-700">{{ payment.name }}</span>
                    </label>
                    <p v-if="payments.length === 0" class="text-sm text-gray-500 p-2">
                        {{ $t(`dashboard.pages.payments.no_payment_methods_available`) }}
                    </p>
                </div>
            </div>
        </div>


        <div class="p-5 space-y-5">
            <div class="space-y-2">
                <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.send_email_merchant`) }}</h2>
            </div>

            <div class="relative">
                <select class="w-full rounded-lg border border-gray-300 p-2.5 peer" v-model="config.send_instruction_email">
                    <option value="1">{{ $t(`dashboard.pages.payments.send_email_instruction_yes`) }}</option>
                    <option value="0">{{ $t(`dashboard.pages.payments.send_email_instruction_no`) }}</option>
                </select>

                <label for="frontend_label" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
                  {{ $t(`dashboard.pages.payments.send_email_instruction`) }}
                </label>
            </div>
        </div>


    </div>

</template>

<script>
import {inject, ref, watch} from "vue";

export default {
    name: "PayPerEmailPaymentConfig",
    props: ['payments'],
    setup(props) {
        const config = inject('config')

        // Initialize array from comma-separated string
        const initializeArray = () => {
            if (!config.value.allowed_payments) {
                return []
            }
            // If it's already an array, return it
            if (Array.isArray(config.value.allowed_payments)) {
                return config.value.allowed_payments
            }
            // If it's a string, split by comma and trim each value
            return config.value.allowed_payments
                .split(',')
                .map(item => item.trim())
                .filter(item => item !== '')
        }

        const allowedPaymentsArray = ref(initializeArray())

        // Watch for changes in the array and update config
        watch(allowedPaymentsArray, (newValue) => {
            // Convert array back to comma-separated string
            config.value.allowed_payments = Array.isArray(newValue) 
                ? newValue.join(',') 
                : ''
        }, { deep: true })

        // Watch for changes in config (when loaded from backend)
        watch(() => config.value.allowed_payments, (newValue) => {
            if (newValue) {
                if (Array.isArray(newValue)) {
                    allowedPaymentsArray.value = newValue
                } else if (typeof newValue === 'string') {
                    allowedPaymentsArray.value = newValue
                        .split(',')
                        .map(item => item.trim())
                        .filter(item => item !== '')
                }
            } else {
                allowedPaymentsArray.value = []
            }
        })

        return {
            config,
            allowedPaymentsArray
        }
    }
}
</script>
