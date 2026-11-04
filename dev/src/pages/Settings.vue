<template>
  <div>
    <div class="2xl:pr-4 2xl:pb-8 2xl:flex-1 min-w-0 w-full">
      <div v-if="settings" class="bg-white shadow rounded-2xl">
        <PageHeader :title="$t('dashboard.pages.settings.settings')" :subtitle="$t('dashboard.pages.settings.settings_label')" />

        <div class="md:p-5 p-4 divide-y space-y-3">
        <div class="pb-5 space-y-5">
          <div class="flex flex-wrap gap-2 justify-between items-start">
            <div>
              <h2 class="font-semibold text-sm">{{ $t('dashboard.pages.settings.credentials') }}</h2>
              <div class="text-gray-400 text-xs" v-html="$t('dashboard.pages.settings.credentials_label')"></div>
            </div>

            <div class="flex flex-col md:items-end items-start space-y-1" v-if="!testCredentialsApi.loading.value">
              <button
                  class="border border-fifthly rounded text-fifthly text-sm p-1 whitespace-nowrap hover:bg-fifthly hover:text-white hover:shadow-lg"
                  v-bind:class="{'opacity-25 cursor-not-allowed': (!settings.website_key || !settings.secret_key) }"
                  :disabled="!settings.website_key || !settings.secret_key"
                  :title="(!settings.website_key || !settings.secret_key) ? $t('dashboard.pages.settings.test_connection_hint') : null"
                  @click="testCredentials"><i class="fas fa-plug"></i> {{
                  $t('dashboard.pages.settings.test_connection')
                }}
              </button>
              <div v-if="!settings.website_key || !settings.secret_key" class="text-xs text-gray-400 md:text-right max-w-[16rem]">
                {{ $t('dashboard.pages.settings.test_connection_hint') }}
              </div>
              <div v-if="credentialsAreValid === true" class="text-xs text-green-600">
                {{ $t('dashboard.pages.settings.successfully_verified_the_credentials') }}
              </div>
              <div v-if="credentialsAreValid === false" class="text-xs text-red-600">
                {{ $t('dashboard.pages.settings.the_credentials_are_not_valid') }}
              </div>
            </div>

            <Loading v-else color="text-fifthly"/>
          </div>

          <div class="space-y-5">
            <div class="relative">
              <input :type="(showWebsiteKey)? 'text' : 'password'" id="website_key"
                     class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-fifthly peer"
                     placeholder=" " v-model="settings.website_key"/>
              <label for="website_key"
                     class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-fifthly peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
                {{ $t('dashboard.pages.settings.website_key') }}
              </label>

              <button @click="showWebsiteKey = !showWebsiteKey"
                      class="right-3 absolute top-2 hover:bg-gray-200 text-gray-500 p-1 rounded">
                <i v-if="!showWebsiteKey" class="far fa-eye"></i>
                <i v-else class="far fa-eye-slash"></i>
              </button>
            </div>

            <div class="relative">
              <input :type="(showSecretKey)? 'text' : 'password'" id="secret_key"
                     class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-fifthly peer"
                     placeholder=" " v-model="settings.secret_key"/>
              <label for="secret_key"
                     class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-fifthly peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
                {{ $t('dashboard.pages.settings.secret_key') }}
              </label>

              <button @click="showSecretKey = !showSecretKey"
                      class="right-3 absolute top-2 hover:bg-gray-200 text-gray-500 p-1 rounded">
                <i v-if="!showSecretKey" class="far fa-eye"></i>
                <i v-else class="far fa-eye-slash"></i>
              </button>
            </div>
          </div>

          <button type="button"
                  class="w-full flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-fifthly hover:bg-gray-100 select-none focus:outline-none focus-visible:ring-2 focus-visible:ring-fifthly"
                  :aria-expanded="showAdvanceSettings"
                  @click="showAdvanceSettings = !showAdvanceSettings">
            <span>{{ showAdvanceSettings ? $t('dashboard.pages.settings.hide_advance_settings') : $t('dashboard.pages.settings.advance_settings') }}</span>
            <i class="fas text-xs transition-transform duration-200" :class="showAdvanceSettings ? 'fa-chevron-up' : 'fa-chevron-down'" aria-hidden="true"></i>
          </button>
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
                  <input type="text" id="transaction_description"
                         class="!block !px-2.5 !pb-2.5 !pt-4 !w-full !text-sm !text-gray-900 !bg-transparent !rounded-lg !border !border-gray-300 !appearance-none !focus:outline-none !focus:ring-0 !focus:border-fifthly !peer"
                         placeholder=" " v-model="settings.transaction_description"/>
                  <label for="transaction_description"
                         class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-fifthly peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
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
                        <button class="p-1 border rounded hover:bg-primary hover:text-fifthly"
                                @click="(settings.transaction_description !== null)? settings.transaction_description = settings.transaction_description.concat('', '{order_number}') : settings.transaction_description = '{order_number}'">
                          {{ $t('dashboard.pages.settings.order_number') }}
                        </button>
                      </li>
                      <li>
                        <button class="p-1 border rounded hover:bg-primary hover:text-fifthly"
                                @click="(settings.transaction_description !== null)? settings.transaction_description = settings.transaction_description.concat('', '{shop_name}') : settings.transaction_description = '{shop_name}'">
                          {{ $t('dashboard.pages.settings.shop_name') }}
                        </button>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- Refunds -->
              <section class="py-3 space-y-3">
                <div>
                  <h2 class="m-0 font-semibold text-sm">{{ $t('dashboard.pages.settings.refunds') }}</h2>
                  <div class="text-gray-400 text-xs">{{ $t('dashboard.pages.settings.refunds_intro') }}</div>
                </div>

                <div class="rounded-xl border border-gray-200">
                  <ToggleField id="refundconf" class="px-4 py-3" v-model="settings.refundconf">
                    <div class="pr-6">
                      <label for="refundconf" class="m-0 font-semibold text-sm cursor-pointer">{{ $t('dashboard.pages.settings.refund_conf') }}</label>
                      <div class="text-gray-400 text-xs">{{ $t('dashboard.pages.settings.refund_conf_label') }}</div>
                    </div>
                  </ToggleField>
                </div>

                <div class="rounded-xl border border-gray-200">
                  <div class="px-4 pt-3 pb-1">
                    <div role="heading" aria-level="3" class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $t('dashboard.pages.settings.refund_actions_heading') }}</div>
                    <div class="text-gray-400 text-xs">{{ $t('dashboard.pages.settings.refund_actions_hint') }}</div>
                  </div>
                  <div class="divide-y divide-gray-100">
                  <ToggleField id="restock" class="px-4 py-3" v-model="settings.restock">
                    <div class="pr-6">
                      <label for="restock" class="m-0 font-semibold text-sm cursor-pointer">{{ $t('dashboard.pages.settings.re_stock_products') }}</label>
                      <div class="text-gray-400 text-xs">{{ $t('dashboard.pages.settings.re_stock_products_label') }}</div>
                    </div>
                  </ToggleField>
                  <ToggleField id="creditSlip" class="px-4 py-3" v-model="settings.creditSlip">
                    <div class="pr-6">
                      <label for="creditSlip" class="m-0 font-semibold text-sm cursor-pointer">{{ $t('dashboard.pages.settings.generate_credit_slip') }}</label>
                      <div class="text-gray-400 text-xs">{{ $t('dashboard.pages.settings.generate_credit_slip_label') }}</div>
                    </div>
                  </ToggleField>
                  <ToggleField id="voucher" class="px-4 py-3" v-model="settings.voucher">
                    <div class="pr-6">
                      <label for="voucher" class="m-0 font-semibold text-sm cursor-pointer">{{ $t('dashboard.pages.settings.generate_voucher') }}</label>
                      <div class="text-gray-400 text-xs">{{ $t('dashboard.pages.settings.generate_voucher_label') }}</div>
                    </div>
                  </ToggleField>
                  <ToggleField id="negativePayment" class="px-4 py-3" v-model="settings.negativePayment">
                    <div class="pr-6">
                      <label for="negativePayment" class="m-0 font-semibold text-sm cursor-pointer">{{ $t('dashboard.pages.settings.generate_negative_payments') }}</label>
                      <div class="text-gray-400 text-xs">{{ $t('dashboard.pages.settings.generate_negative_payments_label') }}</div>
                    </div>
                  </ToggleField>
                  </div>
                </div>
              </section>

              <!-- Payment fees -->
              <section class="py-3 space-y-3">
                <div>
                  <h2 class="m-0 font-semibold text-sm">{{ $t('dashboard.pages.settings.payment_fee') }}</h2>
                  <div class="text-gray-400 text-xs">{{ $t('dashboard.pages.settings.payment_fee_description') }}</div>
                </div>

                <div class="rounded-xl border border-gray-200 divide-y divide-gray-100">
                  <div class="px-4 py-3 space-y-3">
                    <div>
                      <div role="heading" aria-level="3" class="font-semibold text-sm">{{ $t('dashboard.pages.settings.payment_fee_percentage_mode') }}</div>
                      <div class="text-gray-400 text-xs" v-html="$t('dashboard.pages.settings.payment_fee_percentage_mode_description')"></div>
                    </div>
                    <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 text-xs font-semibold" role="group">
                      <button v-for="mode in ['subtotal', 'subtotal_incl_tax']" :key="mode" type="button"
                              class="px-3 py-1.5 rounded-md flex items-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-fifthly"
                              :class="settings.payment_fee_mode === mode ? 'bg-primary text-fifthly shadow' : 'text-gray-500 hover:text-fifthly'"
                              :aria-pressed="settings.payment_fee_mode === mode"
                              @click="settings.payment_fee_mode = mode">
                        <i v-if="settings.payment_fee_mode === mode" class="fas fa-check text-xs" aria-hidden="true"></i>
                        <span>{{ $t(`dashboard.pages.settings.${mode}`) }}</span>
                      </button>
                    </div>
                  </div>

                  <div class="px-4 py-3 space-y-3">
                    <div>
                      <label for="payment_fee_frontend_label" class="m-0 font-semibold text-sm">
                        {{ $t('dashboard.pages.settings.payment_fee_frontend_label') }}
                      </label>
                      <div class="text-gray-400 text-xs">{{ $t('dashboard.pages.settings.payment_fee_frontend_label_description') }}</div>
                    </div>
                    <div class="relative">
                      <input
                          type="text"
                          id="payment_fee_frontend_label"
                          class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-fifthly peer"
                          placeholder=" "
                          v-model="settings.payment_fee_frontend_label"
                      />
                      <label
                          for="payment_fee_frontend_label"
                          class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-fifthly peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1"
                      >
                        {{ $t('dashboard.pages.settings.payment_fee_frontend_label') }}
                      </label>
                    </div>
                  </div>
                </div>
              </section>

              <!-- Privacy -->
              <section class="py-3 space-y-3">
                <div>
                  <h2 class="m-0 font-semibold text-sm">{{ $t('dashboard.pages.settings.privacy') }}</h2>
                  <div class="text-gray-400 text-xs">{{ $t('dashboard.pages.settings.privacy_description') }}</div>
                </div>

                <div class="rounded-xl border border-gray-200">
                  <ToggleField id="error_reporting" class="px-4 py-3" v-model="settings.error_reporting"
                               @update:modelValue="errorReportingTouched = true">
                    <div class="pr-6">
                      <label for="error_reporting" class="m-0 font-semibold text-sm cursor-pointer">{{ $t('dashboard.pages.settings.error_reporting') }}</label>
                      <div class="text-gray-400 text-xs">{{ $t('dashboard.pages.settings.error_reporting_label') }}</div>
                    </div>
                  </ToggleField>
                </div>
              </section>

            </div>
          </div>
        </Transition>

        <div class="sticky bottom-0 z-10 -mx-4 md:-mx-5 -mb-4 md:-mb-5 px-4 md:px-5 py-4 bg-white rounded-b-2xl flex flex-wrap items-center justify-end gap-3"
             :class="{'shadow-[0_-4px_12px_rgba(0,0,0,0.06)]': isDirty}">
          <div v-if="isDirty" class="mr-auto flex items-center gap-2 text-sm text-fifthly" role="status">
            <span class="inline-block w-2 h-2 rounded-full bg-yellow-500" aria-hidden="true"></span>
            {{ $t('dashboard.common.unsaved_changes') }}
          </div>
          <button v-if="isDirty" type="button"
                  class="text-sm text-gray-500 hover:text-fifthly px-3 py-2 rounded-lg hover:bg-gray-100"
                  @click="discardChanges">{{ $t('dashboard.common.discard') }}
          </button>
          <button type="button" class="bg-primary font-bold text-fifthly rounded-lg px-8 py-3 hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
                  :disabled="!isDirty"
                  @click="updateSettings">{{ $t('dashboard.pages.settings.save') }}
          </button>
        </div>
        </div>
      </div>

      <loading v-if="loading" class="my-8"/>
    </div>

    <HelpPanel :title="$t('dashboard.pages.settings.welcome_to_buckaroo')" breakpoint="2xl">

      <p class="text-sm" v-html=" $t('dashboard.pages.settings.explanation_intro')">
      </p>

      <iframe class="rounded-xl" width="100%" height="180" src="https://www.youtube.com/embed/sAw16-VPkhY"
              title="Buckaroo Smart Checkout" frameborder="0"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
              allowfullscreen></iframe>

      <ol class="m-0 p-0 list-none space-y-3 text-xs leading-relaxed">
        <li v-for="(step, index) in ['step_one', 'step_two', 'step_three', 'step_four', 'step_five']" :key="step" class="flex gap-2.5">
          <span class="shrink-0 w-5 h-5 mt-px rounded-full bg-fifthly text-white text-[11px] font-bold flex items-center justify-center" aria-hidden="true">{{ index + 1 }}</span>
          <span class="text-gray-600" v-html="$t(`dashboard.pages.settings.${step}`)"></span>
        </li>
      </ol>

      <p class="text-xs">
        {{ $t('dashboard.pages.settings.if_you_have_any_questions') }}
        <a href='mailto:support@buckaroo.nl' class='bg-primary inline-block rounded p-1 text-fifthly m-1'>support@buckaroo.nl</a>
        {{ $t('dashboard.pages.settings.if_you_have_any_questions_link') }}
        <a href='tel:+31307115020' class='bg-primary inline-block rounded p-1 text-fifthly m-1'>+31 (0) 30 711 50 20</a>.
      </p>
    </HelpPanel>
  </div>
</template>

<script>
import {ref, computed, watch, inject, onMounted, onBeforeUnmount} from 'vue'
import {useApi} from "../lib/api.ts";
import {useToastr} from "../lib/toastr.ts"
import {useI18n} from 'vue-i18n';

import ToggleField from '../components/fields/ToggleField.vue'
import HelpPanel from '../components/HelpPanel.vue'
import PageHeader from '../components/PageHeader.vue'

export default {
  name: "Settings",
  components: {
    ToggleField,
    HelpPanel,
    PageHeader
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
    const {t} = useI18n();
    const showWebsiteKey = ref(false)
    const showSecretKey = ref(false)
    const settings = ref(null)
    const showAdvanceSettings = ref(false)
    const errorReportingTouched = ref(false)

    const {get, post, data, loading} = useApi('buckaroo_config_settings')
    const testCredentialsApi = useApi(`buckaroo_config_testCredentials`);
    const {toastr} = useToastr()
    const credentialsAreValid = ref(null)

    // Track unsaved changes against the last loaded or saved settings.
    const savedSnapshot = ref(null)
    const hasUnsavedChanges = inject('hasUnsavedChanges', ref(false))
    const isDirty = computed(() => settings.value !== null && JSON.stringify(settings.value) !== savedSnapshot.value)
    watch(isDirty, (dirty) => { hasUnsavedChanges.value = dirty })

    const rememberSaved = (value) => {
      settings.value = value
      savedSnapshot.value = JSON.stringify(value)
    }

    const discardChanges = () => {
      settings.value = JSON.parse(savedSnapshot.value)
      errorReportingTouched.value = false
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

    const getSettings = () => {
      get().then(() => {
        if (data.value.status) {
          rememberSaved(data.value.settings)
        }
      })
    }

    const updateSettings = () => {
      const payload = {...settings.value}
      // Keep the error reporting consent "not asked yet" unless the merchant changed the toggle.
      if (!payload.error_reporting_decided && !errorReportingTouched.value) {
        delete payload.error_reporting
      }
      delete payload.error_reporting_decided

      post(payload).then(() => {
        if (data.value.status) {
          rememberSaved(data.value.settings)
          errorReportingTouched.value = false

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
      showAdvanceSettings,
      errorReportingTouched,
      isDirty,
      discardChanges
    }
  }
}
</script>
