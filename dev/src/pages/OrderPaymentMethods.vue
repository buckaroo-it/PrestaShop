<template>
  <div>
    <div class="md:p-8 p-3 md:w-4/5 w-full">
      <div class="flex flex-col bg-white shadow rounded md:h-full h-[640px]">
        <div class="p-5 border-b space-y-1">
          <h1 class="font-bold md:text-2xl text-lg">{{ $t(`dashboard.pages.order_payment_methods.order_payment_methods`) }}</h1>
          <div class="text-gray-400 md:text-sm text-xs">{{ $t(`dashboard.pages.order_payment_methods.order_payment_methods_label`) }}</div>
        </div>
        <div class="flex md:flex-row flex-col h-full">
          <div class="md:w-1/4 w-full border-r flex flex-col border-b">
            <div class="flex items-center border-b h-16">
              <i class="far fa-search block px-3 text-gray-400"></i>
              <input class="w-full px-3 border-0 focus:bg-transparent hover:bg-transparent focus:ring-0 focus:outline-none" type="text" :placeholder="$t(`dashboard.pages.order_payment_methods.search_country`)" v-model="query" />
            </div>

            <div class="overflow-y-auto md:h-full">
              <ul class="md:h-0 flex md:flex-col">
                <li class="w-full md:py-5 md:px-8 py-2 px-3 md:text-base text-sm md:space-y-0 space-y-2 font-semibold hover:bg-gray-200 cursor-pointer space-x-2 md:block md:text-left flex flex-col items-center justify-center text-left" v-bind:class="{'bg-gray-100': selectedCountry === null}" @click="selectedCountry = null">
                  <i class="far fa-globe-europe"></i>
                  <span>{{ $t(`dashboard.pages.order_payment_methods.all_countries`) }}</span>
                </li>
                <li class="w-full md:py-5 md:px-8 px-3 md:text-base text-sm md:space-y-0 space-y-2 font-semibold hover:bg-gray-200 cursor-pointer space-x-2 md:block md:text-left flex flex-col items-center justify-center text-left" v-for="country in filteredCountries" v-bind:class="{'bg-gray-100': selectedCountry && selectedCountry.name === country.name}" @click="selectedCountry = country">
                  <img :src="`/img/flags/${ country.icon }`" class="w-4 inline" />
                  <span>{{ $t(`countries.${ country.name }`) }}</span>
                </li>
              </ul>
            </div>
          </div>

          <div class="md:w-3/4 w-full flex flex-col h-full">
            <div class="border-b h-16 flex justify-between items-center">
              <div class="px-5 space-y-1">
                <h2 v-if="selectedCountry" class="font-bold"><img :src="`/img/flags/${ selectedCountry.icon }`" class="w-6 inline" /> {{ $t(`countries.${ selectedCountry.name }`) }}</h2>
                <h2 v-else class="font-bold">All Countries</h2>
              </div>

              <button class="h-full bg-blue-500 text-white px-8 font-bold hover:bg-blue-600" @click="update">Save</button>
            </div>

            <div class="overflow-y-auto h-full">
              <div class="h-0 p-5 divide-y" v-if="paymentOrderings">
                <draggable
                    :list="paymentOrderings.value"
                    item-key="order"
                    class="divide-y border rounded"
                    ghost-class="opacity-0.50"
                    @end="updateOrdering"
                >
                  <template #item="{ element }">
                    <div class="p-3 flex items-center space-x-2 cursor-move bg-white">
                      <img v-if="element.icon" :src="`/modules/buckaroo3/views/img/buckaroo/Payment methods/SVG/${ element.icon }`" alt="icon" class="w-8">
                      <div class="flex-1">{{ $t(`payment_methods.${ element.name }`) }} </div>
                      <i class="fas fa-arrows-alt-v"></i>
                    </div>
                  </template>
                </draggable>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="md:w-1/5 w-full bg-thirdly p-5 space-y-5 text-gray-600 border-l-2 border-gray-100 h-full">
      <h2 class="font-bold text-lg">{{ $t(`dashboard.pages.order_payment_methods.how_to_order_payment_method`) }}</h2>

      <p class="text-sm leading-relaxed">
        {{ $t(`dashboard.pages.order_payment_methods.explanation_intro`) }}
      </p>

      <iframe class="rounded-xl" width="100%" height="180" src="https://www.youtube.com/embed/sAw16-VPkhY" title="Buckaroo Smart Checkout" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>

      <ol class="font-semibold space-y-3 text-xs leading-relaxed">
        <li>
          <div class="inline-block border border-gray-400 rounded w-4 h-4 text-xs text-left mr-1"> 1 </div> <span v-html="$t(`dashboard.pages.order_payment_methods.step_one`)"></span>
        </li>
        <li>
          <div class="inline-block border border-gray-400 rounded w-4 h-4 text-xs text-left mr-1"> 2 </div> <span v-html="$t(`dashboard.pages.order_payment_methods.step_two`)"></span>
        </li>
        <li>
          <div class="inline-block border border-gray-400 rounded w-4 h-4 text-xs text-left mr-1"> 3 </div> <span v-html="$t(`dashboard.pages.order_payment_methods.step_three`)"></span>
        </li>
      </ol>

      <p class="text-xs leading-loose">
        {{ $t('dashboard.pages.order_payment_methods.if_you_have_any_questions') }}
        <a href='mailto:support@buckaroo.nl' class='text-fourthly font-bold'>support@buckaroo.nl</a>
        {{ $t('dashboard.pages.settings.if_you_have_any_questions_link') }}
        <a href='tel:+31307115020' class='text-fourthly font-bold'>+31 (0) 30 711 50 20</a>.
      </p>
    </div>
  </div>

</template>

<script>
import { ref, watch } from "vue";
import { useOrderingsService } from "../lib/orderingsService";
import { useCountries } from "../lib/countries";

import CountrySelect from "../components/CountrySelect.vue";
import draggable from 'vuedraggable';

export default {
  name: "OrderPaymentMethods",
  components: {
    CountrySelect,
    draggable
  },
  setup() {
    const { filteredCountries, query } = useCountries();
    const orderingsService = useOrderingsService();
    const selectedCountry = ref(null);

    watch(selectedCountry, (newVal, oldVal) => {
      orderingsService.getOrdering(
          newVal ? newVal.iso_code_2 : ''
      );
    });

    const update = () => {
      orderingsService.updateOrderings(orderingsService.paymentOrderings.value)
          .then(() => {
            if(data.value.status) {
              toastr.success(t('dashboard.pages.order_payment_methods.payment_method_order_updated_successfully'))
              return;
            }
            toastr.error(t('dashboard.pages.order_payment_methods.something_went_wrong'))
          });
    };

    orderingsService.getOrdering('');

    return {
      loading: orderingsService.loading,
      paymentOrderings: orderingsService.paymentOrderings,
      selectedCountry,
      update,
      query,
      filteredCountries
    }
  }
}
</script>