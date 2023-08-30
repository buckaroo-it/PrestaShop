<template>
  <div class="p-8 w-full h-screen">
    <div class="flex flex-col bg-white shadow rounded h-full overflow-hidden">
      <div class="p-5 border-b space-y-1">
        <h1 class="font-bold text-2xl">Verifications</h1>
        <div class="text-gray-400 text-sm">Configure your verifications settings</div>
      </div>

      <div class="flex h-full">
        <div class="w-1/4 border-r flex flex-col">
          <div class="flex items-center border-b h-16">
            <i class="far fa-search block px-3 text-gray-400"></i>
            <input class="w-full  px-3 focus:ring-0 focus:outline-none" type="text" placeholder="Search verifications" />
          </div>

          <div class="overflow-y-auto h-full">
            <ul class="h-0">
              <li class="w-full py-5 px-8 font-semibold hover:bg-gray-200 cursor-pointer space-x-2" v-for="verification in verifications">
                <i class="fas fa-circle text-green-600 text-xs"></i>
                <span>{{ verification.name }}</span>
              </li>
            </ul>
          </div>
        </div>

        <div class="w-3/4 flex flex-col">
          <div class="border-b h-16 flex justify-between items-center">
            <div class="px-5 space-y-1">
              <h2 class="font-bold">iDIN</h2>
              <div class="text-gray-400 text-xs">Settings</div>
            </div>

            <button class="h-full bg-blue-500 text-white px-8 font-bold hover:bg-blue-600">Save</button>
          </div>


          <div class="overflow-y-auto h-full">
            <div class="h-0 p-5 divide-y">
              <div class="p-5 space-y-5">
                <div class="space-y-2">
                  <h3 class="font-semibold text-sm modal-title">Mode</h3>
                  <div class="text-gray-400 text-xs">
                    When enabled you can do (partial) refunds directly from your Lightspeed admin area <a href="https://support.buckaroo.nl/categorieen/plugins/lightspeed-app/faq-lightspeed/hoe-doe-ik-een-refund-vanuit-lightspeed-admin" target="_blank" class="text-fourthly font-semibold">Read more</a>
                  </div>
                </div>


                <div class="flex rounded shadow border justify-between w-80 overflow-hidden font-bold text-sm">
                  <button class="w-1/3 h-12 bg-primary text-white space-x-1">
                    <i class="far fa-check"></i>
                    <span>Live</span>
                  </button>
                  <button class="w-1/3 h-12 hover:bg-yellow-400 hover:text-white">Testing</button>
                  <button class="w-1/3 h-12 hover:bg-gray-800 hover:text-white">Off</button>
                </div>
              </div>

              <div class="p-5 space-y-5">
                <div class="space-y-2">
                  <h2 class="font-semibold text-sm">Frontend label</h2>
                  <div class="text-gray-400 text-xs">This name will be displayed in the shop.</div>
                </div>

                <div class="relative">
                  <input type="text" id="frontend_label" class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-primary peer" placeholder=" " />
                  <label for="frontend_label" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
                    Frontend label
                  </label>
                </div>
              </div>

              <div class="p-5 space-y-5">
                <div class="space-y-2">
                  <h2 class="font-semibold text-sm">Payment fee incl VAT</h2>
                  <div class="text-gray-400 text-xs">You can charge a fee when the customer select this payment.</div>
                </div>

                <div class="relative">
                  <input type="number" id="fee" class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-primary peer" placeholder=" " />
                  <label for="fee" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
                    Payment Fee
                  </label>
                </div>
              </div>

              <div class="p-5 space-y-5">
                <div class="space-y-2">
                  <h2 class="font-semibold text-sm">Order amount allowed</h2>
                  <div class="text-gray-400 text-xs">This method will only be shown when this condition is met.</div>
                </div>

                <div class="flex space-x-5">
                  <div class="relative w-full">
                    <input type="number" id="fee" class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-primary peer" placeholder=" " />
                    <label for="fee" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
                      Minimum order amount
                    </label>
                  </div>

                  <div class="relative w-full">
                    <input type="number" id="fee" class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-primary peer" placeholder=" " />
                    <label for="fee" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
                      Maximum order amount
                    </label>
                  </div>
                </div>

              </div>

              <div class="p-5 space-y-5">
                <div class="flex justify-between items-center">
                  <div class="space-y-2">
                    <h2 class="font-semibold text-sm">Countries</h2>
                    <div class="text-gray-400 text-xs">Only selected countries will this method be shown, leave empty to enable for all countries.</div>
                  </div>

                  <div>
                    <button class="border border-green-400 rounded-l p-1 px-2 text-xs text-green-500 hover:bg-green-400 hover:text-white" @click="addAllCountries">All Countries</button>
                    <button class="border border-red-400 rounded-r p-1 px-2 text-xs text-red-500 hover:bg-red-400 hover:text-white">Clear</button>
                  </div>
                </div>


                <CountrySelect v-model="selectCountry" />

                <ul class="flex flex-wrap text-sm">
                  <li v-for="country in enabledCountries" class="rounded flex border border-gray-300 p-2 space-x-2 my-1 mr-2 shadow text-sm">
                    <button class="hover:text-red-500" @click="removeCountry(country)">
                      <i class="fas fa-times text-xs"></i>
                    </button>
                    <div class="flex items-center space-x-2">
                      <img :src="`/images/flags/${ country.icon }`" class="w-4" />
                      <span class="block">{{ country.name }}</span>
                    </div>
                  </li>

                  <li v-if="countries && countries.length > 10" @click="showAllCountries = !showAllCountries" class="rounded flex border border-gray-300 p-2 space-x-2 my-1 mr-2 bg-gray-200 cursor-pointer">
                    <span v-if="!showAllCountries">and {{ (countries.length - 10) }} more...</span>
                    <span v-else>Show less</span>
                  </li>
                </ul>
              </div>

            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed } from "vue";
import { useApi } from "../lib/api.ts"
import CountrySelect from "../components/CountrySelect.vue";

export default {
  name: "PaymentMethods",
  components: {
    CountrySelect
  },
  watch: {
    selectCountry(value, oldValue) {
      this.appendCountry(value)
    }
  },
  setup() {
    const verifications = ref([])
    const { get, data, loading } = useApi('payments')
    const selectCountry = ref(null)
    const countries = ref([])
    const countriesApi = useApi('countries')
    const showAllCountries = ref(false)

    get({ type: 'verification' }).then(() => {
      if(data.value.status) {
        verifications.value = data.value.payments
      }
    })

    const addAllCountries = () => {
      countriesApi.get().then(() => {
        if(countriesApi.data.value.status) {
          countriesApi.data.value.countries.forEach((country) => {
            appendCountry(country)
          })
        }
      })
    }

    const appendCountry = (country) => {
      if(!countries.value.find(c => c.id === country.id)) {
        countries.value.push(country)
      }
    }

    const removeCountry = (country) => {
      countries.value = countries.value.filter(ec => ec.id !== country.id)
    }

    const removeAllCountries = () => {
      countries.value = config_value.value ?? []
      countries.value = []
    }

    const enabledCountries = computed(() => {
      if(countries.value) {
        if(countries.value.length > 10 && !showAllCountries.value)
        {
          return countries.value.slice(0, 10)
        }
      }

      return countries.value ?? []
    })

    return {
      verifications,
      loading,
      selectCountry,
      addAllCountries,
      appendCountry,
      countries,
      removeCountry,
      removeAllCountries,
      enabledCountries,
      showAllCountries
    }
  }
}
</script>
