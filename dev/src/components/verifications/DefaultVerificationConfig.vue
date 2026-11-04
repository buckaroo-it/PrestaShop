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

                    <div class="rounded-xl border border-gray-200 divide-y divide-gray-100">
                        <div class="px-4 py-3 space-y-3">
                            <div>
                                <label for="verification_frontend_label" class="m-0 font-semibold text-sm">{{ $t(`dashboard.pages.payments.front_label`) }}</label>
                                <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.front_label_default_hint`, { name: methodName }) }}</div>
                            </div>

                            <div class="relative">
                                <input type="text" id="verification_frontend_label" class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-fifthly peer" placeholder=" " v-model="config.frontend_label" />
                                <label for="verification_frontend_label" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-fifthly peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
                                    {{ methodName }}
                                </label>
                            </div>
                        </div>

                        <div class="px-4 py-3 space-y-3">
                            <div>
                                <label for="verification_display_mode" class="m-0 font-semibold text-sm">{{ $t(`dashboard.pages.payments.idin.display_mode`) }}</label>
                                <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.idin.display_mode_label`) }}</div>
                            </div>

                            <select id="verification_display_mode" v-model="config.display_mode"
                                    class="block w-full md:w-80 h-[39px] px-2.5 text-sm text-gray-900 rounded-lg border border-gray-300 bg-white shadow-none focus:outline-none focus:border-fifthly">
                                <option value="product">{{ $t(`dashboard.pages.payments.idin.display_mode.product`) }}</option>
                                <option value="global">{{ $t(`dashboard.pages.payments.idin.display_mode.global`) }}</option>
                            </select>
                        </div>
                    </div>
                </section>

                <slot></slot>

                <!-- Availability -->
                <section class="space-y-3">
                    <h2 class="m-0 font-semibold text-sm">{{ $t(`dashboard.pages.payments.section_availability`) }}</h2>
                    <div class="rounded-xl border border-gray-200 px-4 py-3">
                        <CountryAvailability v-model="config.countries" />
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
                        :disabled="!isDirty || saving" @click="updateConfig">
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
import { ref, provide, inject, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import CountryAvailability from '../CountryAvailability.vue'
import { useApi } from "../../lib/api";
import { useToastr } from "../../lib/toastr"

const emptyConfig = () => ({
    mode: 'off',
    display_type: 'dropdown',
    frontend_label: '',
    countries: [],
})

export default {
    name: "DefaultVerificationConfig.vue",
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

        const methodName = computed(() => t(`verification_methods.${props.payment.name}`))

        const modes = [
            { value: 'live', dot: 'bg-green-500' },
            { value: 'test', dot: 'bg-yellow-400' },
            { value: 'off', dot: 'bg-gray-400' },
        ]

        // Unsaved changes, compared with the last loaded or saved configuration
        const savedSnapshot = ref(null)
        const hasUnsavedChanges = inject('hasUnsavedChanges', ref(false))
        const isDirty = computed(() => savedSnapshot.value !== null && JSON.stringify(config.value) !== savedSnapshot.value)
        watch(isDirty, (dirty) => { hasUnsavedChanges.value = dirty })

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

        const getConfig = () => {
            get({paymentName:props.payment.name}).then(() => {
                if (data.value.status) {
                    config.value = data.value.config ? data.value.config.value : emptyConfig()
                    savedSnapshot.value = JSON.stringify(config.value)
                }
            })
        }

        const updateConfig = () => {
            saving.value = true
            post(config.value, {paymentName:props.payment.name}).then(() => {
                if (data.value.status) {
                    savedSnapshot.value = JSON.stringify(config.value)
                    props.payment.mode = config.value.mode
                    toastr.success(t('dashboard.pages.settings.settings_successfully_updated'))
                    return
                }

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
            methodName,
            modes,
            isDirty,
            discardChanges,
        }
    }
}
</script>
