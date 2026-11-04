<template>
    <div class="flex flex-col items-center gap-3 h-full rounded-xl border border-gray-200 bg-white p-4 text-center transition hover:border-gray-300 hover:shadow-md">
        <button type="button" class="flex flex-col items-center gap-2 w-full rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-fifthly" @click="$emit('selectPayment')">
            <span class="flex items-center justify-center h-10">
                <img :src="`${baseUrl}/modules/buckaroo3/views/img/buckaroo/Payment methods/SVG/${ payment.icon }`" alt=""
                     class="max-h-10 w-12 object-contain transition"
                     :class="{ 'opacity-60': payment.mode !== 'live' && payment.mode !== 'test' }">
            </span>
            <span class="text-sm font-semibold text-fifthly leading-tight min-h-[2.5rem] flex items-center">{{ payment.name === 'ideal' ? 'iDEAL | Wero' : $t(`payment_methods.${ payment.name }`) }}</span>
        </button>

        <div class="grid grid-cols-3 gap-1 w-full rounded-lg border border-gray-200 bg-gray-50 p-1 text-[11px] font-semibold" role="group" :aria-label="$t('dashboard.pages.payments.mode')">
            <button v-for="mode in modes" :key="mode.value" type="button"
                    class="flex items-center justify-center gap-1 rounded-md py-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-fifthly"
                    :class="payment.mode === mode.value ? 'bg-white shadow text-fifthly' : 'text-gray-500 hover:text-fifthly'"
                    :aria-pressed="payment.mode === mode.value"
                    @click="payment.mode = mode.value">
                <span class="inline-block w-1.5 h-1.5 rounded-full" :class="mode.dot" aria-hidden="true"></span>
                {{ $t(`dashboard.pages.payments.modes_settings.${mode.value}`) }}
            </button>
        </div>

        <button type="button" @click="$emit('selectPayment')"
                class="mt-auto w-full flex items-center justify-center gap-1.5 rounded-lg border border-gray-200 py-1.5 text-xs font-semibold text-fifthly hover:bg-fifthly hover:text-white hover:border-fifthly focus:outline-none focus-visible:ring-2 focus-visible:ring-fifthly">
            <i class="fas fa-cog text-[10px]" aria-hidden="true"></i>
            {{ $t(`dashboard.pages.payments.configure`) }}
        </button>
    </div>
</template>

<script>
import { inject, ref, watch, computed } from "vue";
import { useI18n } from "vue-i18n";
import { useApi } from "../lib/api"
import { useToastr } from "../lib/toastr";

export default {
    props: ['payment'],
    setup(props) {
        const { toastr } = useToastr()
        const { t } = useI18n()
        const displayName = computed(() => props.payment.name === 'ideal' ? 'iDEAL | Wero' : t(`payment_methods.${props.payment.name}`))
        const { post, data, loading, setEndpoint } = useApi('buckaroo_config_methodMode')
        const paymentState = ref((props.payment.config)? (props.payment.config.value.enabled ?? 0) : 0)
        const baseUrl = inject('baseUrl');

        // Save a mode picked on the tile; put the previous mode back when saving fails.
        let rollingBack = false
        watch(() => props.payment.mode, (mode, previousMode) => {
            if (rollingBack) {
                rollingBack = false
                return
            }

            const rollBack = () => {
                if (props.payment.mode !== previousMode) {
                    rollingBack = true
                    props.payment.mode = previousMode
                }
                toastr.error(t('dashboard.pages.order_payment_methods.something_went_wrong'))
            }

            post(props.payment).then(() => {
                if (!data.value.status) {
                    rollBack()
                    return
                }
                toastr.success(t('dashboard.pages.payments.mode_updated', {
                    name: displayName.value,
                    mode: t(`dashboard.pages.payments.modes_settings.${mode}`),
                }))
            }).catch(rollBack)
        })

        const modes = [
            { value: 'live', dot: 'bg-green-500' },
            { value: 'test', dot: 'bg-yellow-400' },
            { value: 'off', dot: 'bg-gray-400' },
        ]

        return {
            modes,
            t,
            displayName,
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


