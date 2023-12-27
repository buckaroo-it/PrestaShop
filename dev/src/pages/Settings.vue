<template>
  <div>
    <div class="md:p-8 p-3 lg:w-4/5 w-full">
      <div v-if="settings" class="bg-white shadow rounded p-5 divide-y space-y-3">
        <div class="pb-5 space-y-1">
          <h1 class="font-bold text-2xl">{{ $t('dashboard.pages.settings.settings') }}</h1>
          <div class="text-gray-400 text-sm">{{ $t('dashboard.pages.settings.settings_label') }}</div>
        </div>

        <div class="py-5 space-y-5">
          <div>
            <h2 class="font-semibold text-sm">{{ $t('dashboard.pages.settings.mode') }}</h2>
            <div class="text-gray-400 text-xs">{{ $t('dashboard.pages.settings.mode_label') }}</div>
          </div>

          <div>
            <div class="flex space-x-5">
              <div class="rounded border border-gray-300 md:p-5 p-3 text-center space-y-2 cursor-pointer text-gray-700 relative" v-bind:class="{
                                'border-orange-400 border-2': !settings.is_live
                            }" @click="settings.is_live = 0">
                <i class="fas fa-check-circle text-orange-500 absolute -right-2 -top-2 drop-shadow bg-white rounded-full" v-if="!settings.is_live"></i>
                <span class="font-bold md:text-base text-sm">{{ $t('dashboard.pages.settings.no_im_testing') }}</span>
                <span class="block text-xs">{{ $t('dashboard.pages.settings.when_your_shop_is_not_live_yet') }}</span>
              </div>

              <div class="rounded border border-gray-300 md:p-5 p-3 text-center space-y-2 cursor-pointer text-gray-700 relative" v-bind:class="{
                                'border-green-600 border-2': settings.is_live
                            }" @click="settings.is_live = 1">
                <i class="fas fa-check-circle text-green-700 absolute -right-2 -top-2 drop-shadow bg-white rounded-full" v-if="settings.is_live"></i>
                <span class="font-bold md:text-base text-sm">{{ $t('dashboard.pages.settings.yes_im_ready_to_receive_payments') }}</span>
                <span class="block text-xs">{{ $t('dashboard.pages.settings.your_shop_is_live_and_ready_to_receive_real_payments') }}</span>
              </div>
            </div>
          </div>
        </div>

        <div class="py-5 space-y-5">
          <div class="flex justify-between items-center">
            <div>
              <h2 class="font-semibold text-sm">{{ $t('dashboard.pages.settings.credentials') }}</h2>
              <div class="text-gray-400 text-xs" v-html="$t('dashboard.pages.settings.credentials_label')"></div>
            </div>

            <div class="flex flex-col items-end space-y-1" v-if="!testCredentialsApi.loading.value">
              <button class="border border-blue-500 rounded text-blue-500 text-sm p-1 hover:bg-blue-500 hover:text-white hover:shadow-lg" v-bind:class="{'opacity-25 cursor-not-allowed': (!settings.website_key || !settings.secret_key) }" @click="testCredentials"><i class="fas fa-plug"></i> {{ $t('dashboard.pages.settings.test_connection') }}</button>
              <div v-if="credentialsAreValid === true" class="text-xs text-green-600">{{ $t('dashboard.pages.settings.successfully_verified_the_credentials') }}</div>
              <div v-if="credentialsAreValid === false" class="text-xs text-red-600">{{ $t('dashboard.pages.settings.the_credentials_are_not_valid') }}</div>
            </div>

            <Loading v-else color="text-blue-500" />
          </div>

          <div class="space-y-5">
            <div class="relative">
              <input :type="(showWebsiteKey)? 'text' : 'password'" id="website_key" class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-primary peer" placeholder=" " v-model="settings.website_key" />
              <label for="website_key" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
                {{ $t('dashboard.pages.settings.website_key') }}
              </label>

              <button @click="showWebsiteKey = !showWebsiteKey"   class="right-3 absolute top-2 hover:bg-gray-200 text-gray-500 p-1 rounded">
                <i v-if="!showWebsiteKey" class="far fa-eye"></i>
                <i v-else class="far fa-eye-slash"></i>
              </button>
            </div>

            <div class="relative">
              <input :type="(showSecretKey)? 'text' : 'password'" id="secret_key" class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-primary peer" placeholder=" " v-model="settings.secret_key" />
              <label for="secret_key" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
                {{ $t('dashboard.pages.settings.secret_key') }}
              </label>

              <button @click="showSecretKey = !showSecretKey" class="right-3 absolute top-2 hover:bg-gray-200 text-gray-500 p-1 rounded">
                <i v-if="!showSecretKey" class="far fa-eye"></i>
                <i v-else class="far fa-eye-slash"></i>
              </button>
            </div>
          </div>

          <button v-if="!showAdvanceSettings" class="text-xs border border-orange-500 rounded text-orange-500 p-1 hover:bg-orange-500 hover:text-white hover:shadow-lg select-none" @click="showAdvanceSettings = true">{{ $t('dashboard.pages.settings.advance_settings') }} <i class="fas fa-chevron-down text-[10px]"></i></button>
          <button v-else class="text-xs border border-orange-500 rounded text-orange-500 p-1 hover:bg-orange-500 hover:text-white hover:shadow-lg select-none" @click="showAdvanceSettings = false">{{ $t('dashboard.pages.settings.hide_advance_settings') }} <i class="fas fa-chevron-up text-[10px]"></i></button>
        </div>

        <Transition enter-from-class="opacity-0 translate-y-3"
                    enter-to-class="opacity-100 translate-y-0"
                    enter-active-class="transform transition ease-out duration-200"
                    leave-active-class="transform transition ease-in duration-150"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 translate-y-3">
          <div v-show="showAdvanceSettings" class="divide-y space-y-3">
            <div class="space-y-5">

              <div class="py-3 space-y-5 flex justify-between items-center">
                <div>
                  <h2 class="font-semibold text-sm">{{ $t('dashboard.pages.settings.transaction_description') }}</h2>
                  <div class="text-gray-400 text-xs" v-html="$t('dashboard.pages.settings.transaction_description_label', {
                      'order_number': '{' + $t('dashboard.pages.settings.order_number2') + '}',
                      'shop_name': '{' + $t('dashboard.pages.settings.shop_name2') + '}'
                    })">
                  </div>
                </div>
              </div>

              <div class="space-y-1">
                <div class="relative">
                  <input type="text" id="transaction_description" class="!block !px-2.5 !pb-2.5 !pt-4 !w-full !text-sm !text-gray-900 !bg-transparent !rounded-lg !border !border-gray-300 !appearance-none !focus:outline-none !focus:ring-0 !focus:border-primary !peer" placeholder=" " v-model="settings.transaction_description" />
                  <label for="transaction_description" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
                    {{ $t('dashboard.pages.settings.transaction_description') }}
                  </label>
                </div>

                <div class="text-gray-400 text-xs">
                  <div class="text-gray-400 text-xs space-y-1">

                    <div>
                      {{ $t('dashboard.pages.settings.example') }} {{ displayExampleTransactionDescription }}
                    </div>

                    <ul class="flex space-x-2">
                      <li>
                        <button class="p-1 border rounded hover:bg-primary hover:text-white" @click="(settings.transaction_description !== null)? settings.transaction_description = settings.transaction_description.concat('', '{order_number}') : settings.transaction_description = '{order_number}'">{{ $t('dashboard.pages.settings.order_number') }}</button>
                      </li>
                      <li>
                        <button class="p-1 border rounded hover:bg-primary hover:text-white" @click="(settings.transaction_description !== null)? settings.transaction_description = settings.transaction_description.concat('', '{shop_name}') : settings.transaction_description = '{shop_name}'">{{ $t('dashboard.pages.settings.shop_name') }}</button>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>

              <div class="py-3 space-y-5 flex justify-between items-center">
                <div>
                  <h2 class="font-semibold text-sm">{{ $t('dashboard.pages.settings.refunds') }}</h2>
                  <div class="text-gray-400 text-xs">
                    {{ $t('dashboard.pages.settings.refunds_label_explanation') }}
                  </div>
                </div>
              </div>
                <ToggleField class="p-5 space-y-5" v-model="settings.restock">
                    <div>
                        <label for="client-side-mode" class="font-semibold text-sm">
                          {{ $t('dashboard.pages.settings.re_stock_products') }}
                        </label>
                        <div class="text-gray-400 text-xs">{{ $t('dashboard.pages.settings.re_stock_products_label') }}</div>
                    </div>
                </ToggleField>

                <ToggleField class="p-5 space-y-5" v-model="settings.creditSlip">
                  <div>
                    <label for="client-side-mode" class="font-semibold text-sm">
                      {{ $t('dashboard.pages.settings.generate_credit_slip') }}
                    </label>
                    <div class="text-gray-400 text-xs">{{ $t('dashboard.pages.settings.generate_credit_slip_label') }}</div>
                  </div>
                </ToggleField>

                <ToggleField class="p-5 space-y-5" v-model="settings.voucher">
                  <div>
                    <label for="client-side-mode" class="font-semibold text-sm">
                      {{ $t('dashboard.pages.settings.generate_voucher') }}
                    </label>
                    <div class="text-gray-400 text-xs">{{ $t('dashboard.pages.settings.generate_voucher_label') }}</div>
                  </div>
                </ToggleField>

                <ToggleField class="p-5 space-y-5" v-model="settings.negativePayment">
                  <div>
                    <label for="client-side-mode" class="font-semibold text-sm">
                      {{ $t('dashboard.pages.settings.generate_negative_payments') }}
                    </label>
                    <div class="text-gray-400 text-xs">{{ $t('dashboard.pages.settings.generate_negative_payments_label') }}</div>
                  </div>
                </ToggleField>
            </div>
          </div>
        </Transition>

        <div class="py-5 space-y-5 flex justify-end">
          <button class="bg-secondary font-bold text-white rounded-lg px-8 py-3 hover:shadow-lg" @click="updateSettings">{{ $t('dashboard.pages.settings.save') }}</button>
        </div>
      </div>

      <loading v-if="loading" class="my-8" />
    </div>

    <div class="lg:w-1/5 w-full bg-thirdly p-5 space-y-5 text-gray-600 border-l-2 border-gray-100 lg:h-full">
      <h2 class="font-bold text-lg">{{ $t('dashboard.pages.settings.welcome_to_buckaroo') }}</h2>

      <p class="text-sm" v-html=" $t('dashboard.pages.settings.explanation_intro')">
      </p>

      <iframe class="rounded-xl" width="100%" height="180" src="https://www.youtube.com/embed/sAw16-VPkhY" title="Buckaroo Smart Checkout" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>

      <ol class="font-semibold space-y-3 text-xs">
        <li>
          <div class="inline-block border border-gray-400 rounded w-4 h-4 text-xs text-center mr-1"> 1 </div> <span v-html="$t('dashboard.pages.settings.step_one')"></span>
        </li>
        <li>
          <div class="inline-block border border-gray-400 rounded w-4 h-4 text-xs text-center mr-1"> 2 </div> <span v-html="$t('dashboard.pages.settings.step_two')"></span>
        </li>
        <li>
          <div class="inline-block border border-gray-400 rounded w-4 h-4 text-xs text-center mr-1"> 3 </div> <span v-html="$t('dashboard.pages.settings.step_three')"></span>
        </li>
        <li><div class="inline-block border border-gray-400 rounded w-4 h-4 text-xs text-center mr-1"> 4 </div> <span v-html="$t('dashboard.pages.settings.step_four')"></span></li>
        <li><div class="inline-block border border-gray-400 rounded w-4 h-4 text-xs text-center mr-1"> 5 </div> <span v-html="$t('dashboard.pages.settings.step_five')"></span></li>
      </ol>

      <p class="text-xs" >
        {{$t('dashboard.pages.settings.if_you_have_any_questions')}}
        <a href='mailto:support@buckaroo.nl' class='bg-primary inline-block rounded p-1 text-white m-1'>support@buckaroo.nl</a>
        {{$t('dashboard.pages.settings.if_you_have_any_questions_link')}}
        <a href='tel:+31307115020' class='bg-primary inline-block rounded p-1 text-white m-1'>+31 (0) 30 711 50 20</a>.
      </p>
    </div>
  </div>
</template>

<script>
import { ref, computed } from 'vue'
import { useApi } from "../lib/api.ts";
import { useToastr } from "../lib/toastr.ts"
import { useI18n } from 'vue-i18n';

import ToggleField from '../components/fields/ToggleField.vue'

export default {
  name: "Settings",
  components: {
    ToggleField
  },
  props: [],
  watch: {
    credentialsAreValid(value) {
      if (value) {
        setTimeout(() => {
          this.credentialsAreValid = null
        }, 3000)
      }
    }
  },
  setup() {
    const { t } = useI18n();
    const showWebsiteKey = ref(false)
    const showSecretKey = ref(false)
    const settings = ref(null)
    const showAdvanceSettings = ref(false)

    const {get, post, data, loading} = useApi('buckaroo_config_settings')
    const testCredentialsApi = useApi(`buckaroo_config_testCredentials`);
    const {toastr} = useToastr()
    const credentialsAreValid = ref(null)
    const getSettings = () => {
      get().then(() => {
        if (data.value.status) {
          settings.value = data.value.settings
        }
      })
    }

    const updateSettings = () => {
      post(settings.value).then(() => {
        if (data.value.status) {
          settings.value = data.value.settings

          toastr.success(t('dashboard.pages.settings.settings_successfully_updated'))
        }
      })
    }

    const testCredentials = async () => {
      credentialsAreValid.value = null;
      if (settings.value.website_key && settings.value.secret_key) {
        try {
          await testCredentialsApi.post({
            website_key: settings.value.website_key,
            secret_key: settings.value.secret_key
          });
          credentialsAreValid.value = testCredentialsApi.data.value.status;
        } catch (error) {
          console.error("Error testing credentials:", error);
        }
      }
    };

    const displayExampleTransactionDescription = computed(() => {
      if (settings.value.transaction_description) {
        return settings.value.transaction_description.replaceAll('{shop_name}', 'Your Shop Name').replaceAll('{order_number}', '123456789')
      }
    })

    getSettings()

    return {
      updateSettings,
      loading,
      settings,
      testCredentials,
      credentialsAreValid,
      testCredentialsApi,
      showWebsiteKey,
      showSecretKey,
      getSettings,
      displayExampleTransactionDescription,
      showAdvanceSettings
    }
  }
}
</script>
