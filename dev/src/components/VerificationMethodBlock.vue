<template>
    <div class="bg-gray-50 p-3 rounded">
        <div class="text-center space-y-2">
            <div class="flex justify-center">
                <img :src="`${baseUrl}/modules/buckaroo3/views/img/buckaroo/Identification methods/SVG/${ payment.icon }`" alt="icon" class="w-12">
            </div>

            <h3 class="font-bold text-lg modal-title">{{ $t(`verification_methods.${ payment.name }`) }}</h3>

            <div>
                <div class="rounded-lg border border-gray-300 inline-block text-xs shadow text-center overflow-hidden p-1">
                    <div class="flex space-x-1">
                        <button class="h-6 flex justify-center items-center rounded hover:bg-green-500 hover:text-white px-1 uppercase" v-bind:class="{ 'shadow-xl bg-green-500 text-white' : payment.mode === 'live', 'shadow bg-gray-200 text-gray-400': payment.mode !== 'live' }" @click="payment.mode = 'live'">
                            {{ $t(`dashboard.pages.payments.modes_settings.live`) }}
                        </button>
                        <button class="h-6 flex justify-center items-center rounded hover:bg-yellow-500 hover:text-white px-1 uppercase" v-bind:class="{ 'shadow-xl bg-yellow-500 text-white' : payment.mode === 'test', 'shadow bg-gray-200 text-gray-400': payment.mode !== 'test' }" @click="payment.mode = 'test'">
                            {{ $t(`dashboard.pages.payments.modes_settings.test`) }}
                        </button>
                        <button class="h-6 flex justify-center items-center rounded hover:bg-black hover:text-white px-1 uppercase" v-bind:class="{ 'shadow-xl bg-black text-white' : payment.mode === 'off', 'shadow bg-gray-200 text-gray-400': payment.mode !== 'off' }" @click="payment.mode = 'off'">
                            {{ $t(`dashboard.pages.payments.modes_settings.off`) }}
                        </button>
                    </div>
                </div>

                <div>
                    <button @click="$emit('selectPayment')" class="inline-block py-1 px-3 text-xs hover:font-bold">{{ $t(`dashboard.pages.payments.configure`) }}</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { inject, ref, watch } from "vue";
import { useApi } from "../lib/api"
import { useToastr } from "../lib/toastr";

export default {
    props: ['payment'],
    watch: {
        'payment.mode'({
            mode: value
       }) {
            this.post(this.payment).then(() => {
                if(this.data.status) {
                    this.toastr.success(`Payment updated.`)
                }
            })
            .catch((e) => {
                this.toastr.error(`Something went wrong...`)
            })
        },
    },
    setup(props) {
        const { toastr } = useToastr()
        const { post, data, loading, setEndpoint } = useApi('buckaroo_config_methodMode')
        const paymentState = ref((props.payment.config)? (props.payment.config.value.enabled ?? 0) : 0)
        const baseUrl = inject('baseUrl');

        return {
            toastr,
            loading,
            post,
            data,
            paymentState,
            setEndpoint,
            baseUrl
        }
    }
}
</script>


