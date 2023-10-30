<template>
    <div>
        <div class="p-5 space-y-5">
            <div class="space-y-2">
                <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.send_email_instruction`) }}</h2>
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
    </div>

</template>

<script>
import {inject} from "vue";

export default {
    name: "TransferPaymentConfig",
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
