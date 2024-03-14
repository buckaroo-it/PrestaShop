<template>
    <div class="border-b h-16 flex justify-between items-center">
        <div class="px-5 space-y-1 flex items-center  space-x-3">
            <div class="w-8">
              <img v-if="payment.icon" :src="`${baseUrl}/modules/buckaroo3/views/img/buckaroo/Payment methods/SVG/${ payment.icon }`" alt="" />
            </div>

            <div>
                <h2 class="font-bold h2-title">{{ $t(`payment_methods.${ payment.name }`) }}</h2>
                <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.settings`) }}</div>
            </div>
        </div>

        <button class="h-full bg-blue-500 text-white px-8 font-bold hover:bg-blue-600" @click="updateConfig">{{ $t(`dashboard.pages.payments.save`) }}</button>
    </div>

    <Transition enter-from-class="opacity-0 translate-y-3"
                enter-to-class="opacity-100 translate-y-0"
                enter-active-class="transform transition ease-out duration-50">

        <div v-if="!loading" class="h-full">
            <div class="p-5 space-y-5">

                <div class="px-5 space-y-5">
                    <div class="space-y-2">
                        <h3 class="font-semibold text-sm modal-title">{{ $t(`dashboard.pages.payments.mode`) }}</h3>
                        <div class="text-gray-400 text-xs">
                            {{ $t(`dashboard.pages.payments.mode_label`) }}
                        </div>
                    </div>

                    <div class="flex rounded shadow border justify-between md:w-80 w-full overflow-hidden font-bold md:text-sm text-xs">
                        <button class="w-1/3 h-12 space-x-1 hover:bg-green-500 hover:text-white" v-bind:class="{'bg-green-500 text-white': config.mode === 'live' }" @click="setMode('live')">
                            <i v-if="config.mode === 'live'" class="fas fa-check"></i>
                            <span>{{ $t(`dashboard.pages.payments.modes_settings.live`) }}</span>
                        </button>
                        <button class="w-1/3 h-12 space-x-1 hover:bg-yellow-400 hover:text-white" v-bind:class="{'bg-yellow-400 text-white': config.mode === 'test' }" @click="setMode('test')">
                            <i v-if="config.mode === 'test'" class="fas fa-check"></i>
                            <span>{{ $t(`dashboard.pages.payments.modes_settings.test`) }}</span>
                        </button>
                        <button class="w-1/3 h-12 space-x-1 hover:bg-gray-800 hover:text-white" v-bind:class="{'bg-gray-800 text-white': config.mode === 'off' }" @click="setMode('off')">
                            <i v-if="config.mode === 'off'" class="fas fa-check"></i>
                            <span>{{ $t(`dashboard.pages.payments.modes_settings.off`) }}</span>
                        </button>
                    </div>
                </div>

                <div class="px-5 space-y-5">
                    <div class="space-y-2">
                        <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.front_label`) }}</h2>
                        <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.front_label_label`) }}</div>
                    </div>

                    <div class="relative">
                        <input type="text" id="frontend_label" class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-primary peer" placeholder=" " v-model="config.frontend_label" />
                        <label for="frontend_label" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
                            {{ $t(`dashboard.pages.payments.front_label`) }}
                        </label>
                    </div>
                </div>

                <slot></slot>

                <div class="px-5 space-y-5">
                    <div class="space-y-2">
                        <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.payment_fee_incl_vat`) }}</h2>
                        <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.payment_fee_incl_vat_label`) }}</div>
                    </div>

                    <div class="relative">
                        <input type="number" id="fee" class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-primary peer" placeholder=" " v-model="config.payment_fee" />
                        <label for="fee" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
                            {{ $t(`dashboard.pages.payments.payment_fee_incl_vat`) }}
                        </label>
                    </div>
                </div>

                <div class="px-5 space-y-5">
                    <div class="space-y-2">
                        <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.order_amount_allowed`) }}</h2>
                        <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.order_amount_allowed_label`) }}</div>
                    </div>

                    <div class="md:flex md:space-x-5 md:space-y-0 space-y-3">
                        <div class="relative w-full">
                            <input type="number" id="min_order_amount" class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-primary peer" placeholder=" " v-model="config.min_order_amount" />
                            <label for="min_order_amount" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
                                {{ $t(`dashboard.pages.payments.minimum_order_amount`) }}
                            </label>
                        </div>

                        <div class="relative w-full">
                            <input type="number" id="max_order_amount" class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-primary peer" placeholder=" " v-model="config.max_order_amount" />
                            <label for="max_order_amount" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
                                {{ $t(`dashboard.pages.payments.maximum_order_amount`) }}
                            </label>
                        </div>
                    </div>

                </div>

                <div class="px-5 space-y-5">
                    <div class="md:flex justify-between items-center space-y-2">
                        <div class="space-y-2">
                            <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.countries`) }}</h2>
                            <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.countries_label`) }}</div>
                        </div>

                        <div class="md:block flex justify-end">
                            <button class="border border-green-400 rounded-l p-1 px-2 text-xs text-green-500 hover:bg-green-400 hover:text-white" @click="addAllCountries">{{ $t(`dashboard.pages.payments.all_countries`) }}</button>
                            <button class="border border-red-400 rounded-r p-1 px-2 text-xs text-red-500 hover:bg-red-400 hover:text-white" @click="removeAllCountries">{{ $t(`dashboard.pages.payments.clear`) }}</button>
                        </div>
                    </div>

                    <CountrySelect v-model="selectCountry" />

                    <ul class="flex flex-wrap text-sm">
                        <li v-for="country in enabledCountries" class="rounded flex border border-gray-300 p-2 space-x-2 my-1 mr-2 shadow text-sm">
                            <button class="hover:text-red-500" @click="removeCountry(country)">
                                <i class="fas fa-times text-xs"></i>
                            </button>
                            <div class="flex items-center space-x-2">
                                <img :src="`/img/flags/${ country.icon }`" class="w-4" alt="" />
                                <span class="block">{{ $t(`countries.${ country.name }`) }}</span>
                            </div>
                        </li>

                        <li v-if="config.countries && config.countries.length > 10" @click="showAllCountries = !showAllCountries" class="rounded flex border border-gray-300 p-2 space-x-2 my-1 mr-2 bg-gray-200 cursor-pointer">
                            <span v-if="!showAllCountries">{{ $t(`dashboard.pages.payments.show_country_number_and_more`, { country_number: (config.countries.length - 10) }) }}</span>
                            <span v-else>{{ $t(`dashboard.pages.payments.show_less`) }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div v-else class="h-full flex justify-center items-center py-5">
            <loading />
        </div>
    </Transition>
</template>

<script>
import { inject, ref, provide, computed} from 'vue'
import CountrySelect from '../CountrySelect.vue'
import { useApi } from "../../lib/api";
import { useToastr } from "../../lib/toastr"
import { useCountries } from "../../lib/countries";

export default {
    name: "DefaultPaymentConfig.vue",
    props: ['payment'],
    components: {
        CountrySelect,
    },
    watch: {
        selectCountry(value) {
            if(!this.config.countries) {
                this.config.countries = []
            }

            this.config.countries.push(value)
        },
        countries: {
            handler(value, oldValue){
                this.config.countries = value
            },
            deep: true
        },
        'config.payment_fee'(value) {
            if(value) {
                if(value < 0) {
                    this.config.payment_fee = 0

                    return
                }

                if(value > 999) {
                    this.config.payment_fee = 999

                    return
                }

                this.config.payment_fee = parseFloat(value);
                return;
            }

            this.config.payment_fee = ''
        },
    },
    setup(props) {

        const { get, data, loading, post, setEndpoint } = useApi(`buckaroo_config_paymentMethod`)
        const { toastr } = useToastr()
        const { countries } = useCountries()
        const selectCountry = ref(null)
        const showAllCountries = ref(false)
        const baseUrl = inject('baseUrl');

        const config = ref({
            mode: 'off',
            frontend_label: '',
            payment_fee: null,
            min_order_amount: null,
            max_order_amount: null,
            countries: [],
        })

        provide('config', config)

        const getConfig = () => {
            get({paymentName:props.payment.name}).then((e) => {
              if(data.value.status) {
                    if(data.value.config) {
                        config.value = data.value.config.value

                        return
                    }

                    config.value = {
                        mode: 'off',
                        display_type: 'dropdown',
                        frontend_label: '',
                        payment_fee: null,
                        min_order_amount: null,
                        max_order_amount: null,
                        countries: [],
                    }
                }
            })
        }

        const updateConfig = () => {
            post(config.value, {paymentName:props.payment.name}).then(() => {
                if(data.value.status) {
                    toastr.success(`Settings successfully updated.`)

                    return
                }

                toastr.error(`Something went wrong.`)
            })
        }

        const setMode = (mode) => {
            config.value.mode = mode
            props.payment.mode = mode
        }

        const addAllCountries = () => {
            config.value.countries = countries.value
        }

        const removeAllCountries = () => {
            config.value.countries = []
        }

        const enabledCountries = computed(() => {
            if(config.value.countries) {
                if(config.value.countries.length > 10 && !showAllCountries.value) {
                    return config.value.countries.slice(0, 10)
                }
            }

            return config.value.countries ?? []
        })

        const removeCountry = (country) => {
            config.value.countries = config.value.countries.filter(ec => ec.id !== country.id)
        }

        getConfig()

        return {
            removeCountry,
            removeAllCountries,
            config,
            showAllCountries,
            addAllCountries,
            updateConfig,
            setEndpoint,
            getConfig,
            setMode,
            loading,
            selectCountry,
            enabledCountries,
            baseUrl
        }
    }
}
</script>
