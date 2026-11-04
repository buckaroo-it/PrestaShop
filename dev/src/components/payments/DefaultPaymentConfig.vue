<template>
    <Transition enter-from-class="opacity-0 translate-y-3"
                enter-to-class="opacity-100 translate-y-0"
                enter-active-class="transform transition ease-out duration-50">

        <div v-if="!loading">
            <div class="md:p-5 p-4 space-y-6">

                <!-- Status -->
                <section class="space-y-3">
                    <div>
                        <h2 class="m-0 font-semibold text-sm">{{ $t(`dashboard.pages.payments.mode`) }}</h2>
                        <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.mode_label`) }}</div>
                    </div>

                    <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 text-xs font-semibold" role="group" :aria-label="$t(`dashboard.pages.payments.mode`)">
                        <button v-for="mode in modes" :key="mode.value" type="button"
                                class="px-3 py-1.5 rounded-md flex items-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-fifthly"
                                :class="config.mode === mode.value ? 'bg-white shadow text-fifthly' : 'text-gray-500 hover:text-fifthly'"
                                :aria-pressed="config.mode === mode.value"
                                @click="config.mode = mode.value">
                            <span class="inline-block w-2 h-2 rounded-full" :class="mode.dot" aria-hidden="true"></span>
                            {{ $t(`dashboard.pages.payments.modes_settings.${mode.value}`) }}
                        </button>
                    </div>
                </section>

                <!-- Checkout display -->
                <section class="space-y-3">
                    <h2 class="m-0 font-semibold text-sm">{{ $t(`dashboard.pages.payments.section_display`) }}</h2>

                    <div class="rounded-xl border border-gray-200 px-4 py-3 space-y-3">
                        <div>
                            <label for="frontend_label" class="m-0 font-semibold text-sm">{{ $t(`dashboard.pages.payments.front_label`) }}</label>
                            <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.front_label_default_hint`, { name: paymentName }) }}</div>
                        </div>

                        <div class="relative">
                            <input type="text" id="frontend_label" class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-fifthly peer" placeholder=" " v-model="config.frontend_label" />
                            <label for="frontend_label" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-fifthly peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
                                {{ paymentName }}
                            </label>
                        </div>
                    </div>
                </section>

                <!-- Settings that only exist for this payment method -->
                <section v-if="hasMethodSettings" class="space-y-3">
                    <h2 class="m-0 font-semibold text-sm">{{ $t(`dashboard.pages.payments.section_method`, { name: paymentName }) }}</h2>
                    <div class="method-settings rounded-xl border border-gray-200 divide-y divide-gray-100">
                        <slot></slot>
                    </div>
                </section>

                <!-- Fee -->
                <section v-if="config.payment_fee_allowed !== false" class="space-y-3">
                    <h2 class="m-0 font-semibold text-sm">{{ $t(`dashboard.pages.payments.section_fee`) }}</h2>

                    <div class="rounded-xl border border-gray-200 px-4 py-3 space-y-3">
                        <div>
                            <label for="fee" class="m-0 font-semibold text-sm">{{ $t(`dashboard.pages.payments.payment_fee_incl_vat`) }}</label>
                            <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.payment_fee_incl_vat_label`) }}</div>
                        </div>

                        <div class="relative md:w-80">
                            <input type="text" id="fee" inputmode="decimal"
                                   class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border appearance-none focus:outline-none focus:ring-0 peer"
                                   :class="feeError ? 'border-red-400 focus:border-red-500' : 'border-gray-300 focus:border-fifthly'"
                                   :aria-invalid="!!feeError" aria-describedby="fee-error"
                                   placeholder=" " v-model="config.payment_fee" />
                            <label for="fee" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-fifthly peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
                                {{ $t(`dashboard.pages.payments.fee_placeholder`) }}
                            </label>
                        </div>
                        <div v-if="feeError" id="fee-error" class="text-xs text-red-600" role="alert">{{ feeError }}</div>
                    </div>
                </section>

                <!-- Availability -->
                <section class="space-y-3">
                    <h2 class="m-0 font-semibold text-sm">{{ $t(`dashboard.pages.payments.section_availability`) }}</h2>

                    <div class="rounded-xl border border-gray-200 divide-y divide-gray-100">
                        <div class="px-4 py-3 space-y-3">
                            <div>
                                <div role="heading" aria-level="3" class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.order_amount_allowed`) }}</div>
                                <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.order_amount_allowed_label`) }}</div>
                            </div>

                            <div class="md:flex md:space-x-5 md:space-y-0 space-y-3">
                                <div class="relative w-full">
                                    <input type="text" inputmode="decimal" id="min_order_amount" class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border appearance-none focus:outline-none focus:ring-0 peer"
                                           :class="amountError ? 'border-red-400 focus:border-red-500' : 'border-gray-300 focus:border-fifthly'"
                                           placeholder=" " v-model="config.min_order_amount" />
                                    <label for="min_order_amount" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-fifthly peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
                                        {{ $t(`dashboard.pages.payments.minimum_order_amount`) }}
                                    </label>
                                </div>

                                <div class="relative w-full">
                                    <input type="text" inputmode="decimal" id="max_order_amount" class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border appearance-none focus:outline-none focus:ring-0 peer"
                                           :class="amountError ? 'border-red-400 focus:border-red-500' : 'border-gray-300 focus:border-fifthly'"
                                           placeholder=" " v-model="config.max_order_amount" />
                                    <label for="max_order_amount" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-fifthly peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
                                        {{ $t(`dashboard.pages.payments.maximum_order_amount`) }}
                                    </label>
                                </div>
                            </div>
                            <div v-if="amountError" class="text-xs text-red-600" role="alert">{{ amountError }}</div>
                        </div>

                        <div class="px-4 py-3">
                            <CountryAvailability v-model="config.countries" />
                        </div>
                    </div>
                </section>
            </div>

            <!-- Save bar -->
            <div class="sticky bottom-0 z-10 border-t border-gray-100 md:px-5 px-4 py-4 bg-white rounded-b-2xl flex flex-wrap items-center justify-end gap-3"
                 :class="{'shadow-[0_-4px_12px_rgba(0,0,0,0.06)]': isDirty}">
                <div v-if="isDirty" class="mr-auto flex items-center gap-2 text-sm text-fifthly" role="status">
                    <span class="inline-block w-2 h-2 rounded-full bg-yellow-500" aria-hidden="true"></span>
                    {{ $t('dashboard.common.unsaved_changes') }}
                </div>
                <button v-if="isDirty" type="button" class="text-sm text-gray-500 hover:text-fifthly px-3 py-2 rounded-lg hover:bg-gray-100" @click="discardChanges">
                    {{ $t('dashboard.common.discard') }}
                </button>
                <button type="button" class="bg-primary font-bold text-fifthly rounded-lg px-8 py-3 hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="!isDirty || hasErrors || saving" @click="updateConfig">
                    {{ $t(`dashboard.pages.payments.save`) }}
                </button>
            </div>
        </div>

        <div v-else class="h-full flex justify-center items-center py-5">
            <loading />
        </div>
    </Transition>
</template>

<script>
import { inject, ref, provide, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import CountryAvailability from '../CountryAvailability.vue'
import { useApi } from "../../lib/api";
import { useToastr } from "../../lib/toastr"

// Payment methods that pass extra fields through the default slot.
const METHODS_WITH_EXTRA_SETTINGS = [
    'afterpay', 'billink', 'creditcard', 'paypal', 'paybybank', 'payperemail',
    'transfer', 'giftcard', 'googlepay', 'applepay', 'clicktopay',
]

// Fields kept numeric while typing; a stored number and its text form are the same value.
const NUMERIC_FIELDS = ['payment_fee', 'min_order_amount', 'max_order_amount']

const comparable = (value) => JSON.stringify({
    ...value,
    ...Object.fromEntries(NUMERIC_FIELDS
        .filter((field) => value[field] !== null && value[field] !== undefined)
        .map((field) => [field, String(value[field])])),
})

const emptyConfig = () => ({
    mode: 'off',
    display_type: 'dropdown',
    frontend_label: '',
    payment_fee: null,
    payment_fee_allowed: true,
    min_order_amount: null,
    max_order_amount: null,
    countries: [],
})

export default {
    name: "DefaultPaymentConfig.vue",
    props: ['payment'],
    components: {
        CountryAvailability,
    },
    setup(props) {
        const { t } = useI18n()
        const { get, data, loading, post, setEndpoint } = useApi(`buckaroo_config_paymentMethod`)
        const { toastr } = useToastr()
        const saving = ref(false)
        const baseUrl = inject('baseUrl');

        const config = ref(emptyConfig())
        provide('config', config)

        const paymentName = computed(() => props.payment.name === 'ideal' ? 'iDEAL | Wero' : t(`payment_methods.${props.payment.name}`))
        const hasMethodSettings = computed(() => METHODS_WITH_EXTRA_SETTINGS.includes(props.payment.name))

        const modes = [
            { value: 'live', dot: 'bg-green-500' },
            { value: 'test', dot: 'bg-yellow-400' },
            { value: 'off', dot: 'bg-gray-400' },
        ]

        // Unsaved changes, compared with the last loaded or saved configuration
        const savedSnapshot = ref(null)
        const savedConfig = computed(() => savedSnapshot.value === null ? null : JSON.parse(savedSnapshot.value))
        const hasUnsavedChanges = inject('hasUnsavedChanges', ref(false))
        const isDirty = computed(() => savedConfig.value !== null && comparable(config.value) !== comparable(savedConfig.value))
        watch(isDirty, (dirty) => { hasUnsavedChanges.value = dirty })

        const rememberSaved = (value) => {
            config.value = value
            savedSnapshot.value = JSON.stringify(value)
        }

        const discardChanges = () => {
            config.value = JSON.parse(savedSnapshot.value)
        }

        const warnBeforeLeaving = (event) => {
            if (isDirty.value) {
                event.preventDefault()
                event.returnValue = ''
            }
        }
        onMounted(() => window.addEventListener('beforeunload', warnBeforeLeaving))
        onBeforeUnmount(() => {
            window.removeEventListener('beforeunload', warnBeforeLeaving)
            hasUnsavedChanges.value = false
        })

        // Only allow numbers: digits, one decimal point (a comma becomes a dot) and,
        // for the fee, an optional "%" at the end. Anything else is dropped while typing.
        // Negative fees are not supported: the shop ignores a fee of zero or less.
        const sanitizeDecimal = (value, allowPercent = false) => {
            if (value === null || value === undefined) return value
            let text = String(value).replace(/,/g, '.')
            const percent = allowPercent && text.includes('%')
            text = text.replace(/[^0-9.]/g, '')
            const firstDot = text.indexOf('.')
            if (firstDot !== -1) {
                text = text.slice(0, firstDot + 1) + text.slice(firstDot + 1).replace(/\./g, '')
            }
            return percent && text !== '' ? `${text}%` : text
        }

        const keepNumeric = (field, allowPercent) => watch(
            () => config.value[field],
            (value) => {
                // Leave the stored value alone: rewriting it would change the configuration
                // (and mark the form unsaved) without the merchant touching the field.
                if (savedConfig.value !== null && value === savedConfig.value[field]) return
                const clean = sanitizeDecimal(value, allowPercent)
                if (clean !== value && !(value === null && clean === null)) {
                    config.value[field] = clean
                }
            }
        )
        keepNumeric('payment_fee', true)
        keepNumeric('min_order_amount', false)
        keepNumeric('max_order_amount', false)

        // Validation
        const feeError = computed(() => {
            const value = config.value.payment_fee
            if (value === null || value === undefined || String(value).trim() === '') return null
            if (/^\d+(\.\d+)?%?$/.test(String(value).trim())) return null
            return t('dashboard.pages.payments.fee_invalid')
        })

        const amountError = computed(() => {
            const min = parseFloat(config.value.min_order_amount)
            const max = parseFloat(config.value.max_order_amount)
            if (!isNaN(min) && !isNaN(max) && min > max) return t('dashboard.pages.payments.amount_invalid')
            return null
        })

        const hasErrors = computed(() => !!feeError.value || !!amountError.value)

        const getConfig = () => {
            get({paymentName:props.payment.name}).then(() => {
                if (data.value.status) {
                    rememberSaved(data.value.config ? data.value.config.value : emptyConfig())
                }
            }).catch(() => {
                toastr.error(t('dashboard.pages.order_payment_methods.something_went_wrong'))
            })
        }

        const updateConfig = () => {
            if (hasErrors.value) return
            saving.value = true
            post(config.value, {paymentName:props.payment.name}).then(() => {
                if (data.value.status) {
                    savedSnapshot.value = JSON.stringify(config.value)
                    props.payment.mode = config.value.mode
                    toastr.success(t('dashboard.pages.settings.settings_successfully_updated'))
                    return
                }

                toastr.error(t('dashboard.pages.order_payment_methods.something_went_wrong'))
            }).catch(() => {
                toastr.error(t('dashboard.pages.order_payment_methods.something_went_wrong'))
            }).finally(() => {
                saving.value = false
            })
        }

        getConfig()

        return {
            config,
            updateConfig,
            setEndpoint,
            getConfig,
            loading,
            saving,
            baseUrl,
            paymentName,
            hasMethodSettings,
            modes,
            isDirty,
            discardChanges,
            feeError,
            amountError,
            hasErrors,
        }
    }
}
</script>
