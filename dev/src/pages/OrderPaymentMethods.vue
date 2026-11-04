<template>
  <div>
    <div class="2xl:pr-4 2xl:pb-8 2xl:flex-1 min-w-0 w-full">
      <div class="flex flex-col bg-white shadow rounded-2xl overflow-hidden h-[640px] md:h-[calc(100vh-157px)] md:min-h-[480px]">
        <PageHeader :title="$t(`dashboard.pages.order_payment_methods.order_payment_methods`)"
                    :subtitle="$t(`dashboard.pages.order_payment_methods.order_payment_methods_label`)">
          <template #actions>
            <span v-if="hasUnsavedChanges" class="flex items-center gap-2 text-sm text-fifthly" role="status">
              <span class="inline-block w-2 h-2 rounded-full bg-yellow-500" aria-hidden="true"></span>
              {{ $t('dashboard.common.unsaved_changes') }}
            </span>
            <button type="button" class="self-start sm:self-auto bg-primary font-bold text-fifthly rounded-lg px-6 py-2 hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="!hasUnsavedChanges" @click="update">{{ $t('dashboard.pages.settings.save') }}</button>
          </template>
        </PageHeader>
        <div class="flex md:flex-row flex-col h-full">
          <div class="md:w-56 md:shrink-0 w-full md:border-r border-b md:border-b-0 flex flex-col">
            <div class="h-14 shrink-0 px-3 flex items-center border-b">
              <div class="relative w-full">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs" aria-hidden="true"></i>
                <input type="search" v-model="query"
                       :placeholder="$t(`dashboard.pages.order_payment_methods.search_country`)"
                       :aria-label="$t(`dashboard.pages.order_payment_methods.search_country`)"
                       class="w-full h-9 pl-8 pr-3 text-sm rounded-lg border border-gray-300 bg-white focus:outline-none focus:border-fifthly" />
              </div>
            </div>

            <div class="md:flex-1 md:min-h-0 md:overflow-y-auto overflow-x-auto">
              <ul class="m-0 p-2 flex md:flex-col gap-1">
                <li>
                  <button type="button"
                          class="w-full flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-left whitespace-nowrap focus:outline-none focus-visible:ring-2 focus-visible:ring-fifthly"
                          :class="selectedCountry === null ? 'bg-primary/40 text-fifthly font-semibold' : 'text-gray-600 hover:bg-gray-100'"
                          :aria-pressed="selectedCountry === null"
                          @click="selectCountry(null)">
                    <i class="fas fa-globe-europe w-4 text-center text-gray-400" aria-hidden="true"></i>
                    <span>{{ $t(`dashboard.pages.order_payment_methods.all_countries`) }}</span>
                  </button>
                </li>
                <li v-for="country in filteredCountries" :key="country.id">
                  <button type="button"
                          class="w-full flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-left whitespace-nowrap focus:outline-none focus-visible:ring-2 focus-visible:ring-fifthly"
                          :class="selectedCountry && selectedCountry.name === country.name ? 'bg-primary/40 text-fifthly font-semibold' : 'text-gray-600 hover:bg-gray-100'"
                          :aria-pressed="!!(selectedCountry && selectedCountry.name === country.name)"
                          @click="selectCountry(country)">
                    <img :src="`/img/flags/${ country.icon }`" alt="" class="w-4 h-auto shrink-0" />
                    <span>{{ country.name }}</span>
                  </button>
                </li>
              </ul>
            </div>
          </div>

          <div class="md:flex-1 md:min-w-0 w-full flex flex-col h-full">
            <div class="border-b h-14 shrink-0 px-5 flex flex-wrap items-center justify-between gap-x-4">
              <div role="heading" aria-level="2" class="flex items-center gap-2 text-sm font-semibold text-fifthly">
                <img v-if="selectedCountry" :src="`/img/flags/${ selectedCountry.icon }`" alt="" class="w-4 h-auto shrink-0" />
                <i v-else class="fas fa-globe-europe w-4 text-center text-gray-400" aria-hidden="true"></i>
                <span>{{ selectedCountry ? selectedCountry.name : $t('dashboard.pages.order_payment_methods.all_countries') }}</span>
              </div>
              <div class="text-xs text-gray-400 max-md:hidden">{{ $t('dashboard.pages.order_payment_methods.reorder_hint') }}</div>
            </div>

            <div class="overflow-y-auto h-full">
              <div class="h-0 p-5 divide-y" v-if="paymentOrderings">
                <draggable
                    :list="paymentOrderings.value"
                    item-key="order"
                    class="divide-y border rounded"
                    ghost-class="opacity-0.50"
                    @end="markChanged"
                >
                  <template #item="{ element, index }">
                    <div class="p-3 flex items-center space-x-2 cursor-move bg-white" :data-order-row="index">
                      <img v-if="element.icon" :src="`/modules/buckaroo3/views/img/buckaroo/Payment methods/SVG/${ element.icon }`" alt="" class="w-8">
                      <div class="flex-1">
                        {{ displayName(element) }}
                      </div>
                      <div class="flex items-center gap-1">
                        <button type="button" data-move="up"
                                class="w-8 h-8 flex items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-fifthly disabled:opacity-25 disabled:cursor-not-allowed focus:outline-none focus-visible:ring-2 focus-visible:ring-fifthly"
                                :disabled="index === 0"
                                :aria-label="`${$t('dashboard.pages.order_payment_methods.move_up')}: ${displayName(element)}`"
                                :title="$t('dashboard.pages.order_payment_methods.move_up')"
                                @click="moveItem(index, -1)">
                          <i class="fas fa-chevron-up text-xs" aria-hidden="true"></i>
                        </button>
                        <button type="button" data-move="down"
                                class="w-8 h-8 flex items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-fifthly disabled:opacity-25 disabled:cursor-not-allowed focus:outline-none focus-visible:ring-2 focus-visible:ring-fifthly"
                                :disabled="index === paymentOrderings.value.length - 1"
                                :aria-label="`${$t('dashboard.pages.order_payment_methods.move_down')}: ${displayName(element)}`"
                                :title="$t('dashboard.pages.order_payment_methods.move_down')"
                                @click="moveItem(index, 1)">
                          <i class="fas fa-chevron-down text-xs" aria-hidden="true"></i>
                        </button>
                        <i class="fas fa-grip-vertical text-gray-300 pl-1" aria-hidden="true"></i>
                      </div>
                    </div>
                  </template>
                </draggable>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <HelpPanel :title="$t('dashboard.pages.order_payment_methods.how_to_order_payment_method')" breakpoint="2xl">

      <p class="text-sm leading-relaxed">
        {{ $t(`dashboard.pages.order_payment_methods.explanation_intro`) }}
      </p>

      <iframe class="rounded-xl" width="100%" height="180" src="https://www.youtube.com/embed/sAw16-VPkhY" title="Buckaroo Smart Checkout" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>

      <ol class="m-0 p-0 list-none space-y-3 text-xs leading-relaxed">
        <li v-for="(step, index) in ['step_one', 'step_two', 'step_three']" :key="step" class="flex gap-2.5">
          <span class="shrink-0 w-5 h-5 mt-px rounded-full bg-fifthly text-white text-[11px] font-bold flex items-center justify-center" aria-hidden="true">{{ index + 1 }}</span>
          <span class="text-gray-600" v-html="$t(`dashboard.pages.order_payment_methods.${step}`)"></span>
        </li>
      </ol>

      <p class="text-xs leading-loose">
        {{ $t('dashboard.pages.order_payment_methods.if_you_have_any_questions') }}
        <a href='mailto:support@buckaroo.nl' class='text-fifthly font-bold'>support@buckaroo.nl</a>
        {{ $t('dashboard.pages.settings.if_you_have_any_questions_link') }}
        <a href='tel:+31307115020' class='text-fifthly font-bold'>+31 (0) 30 711 50 20</a>.
      </p>
    </HelpPanel>
  </div>

</template>

<script>
import { ref, watch, inject, nextTick, onMounted, onBeforeUnmount } from "vue";
import { useOrderingsService } from "../lib/orderingsService";
import { useCountries } from "../lib/countries";

import CountrySelect from "../components/CountrySelect.vue";
import draggable from 'vuedraggable';
import HelpPanel from "../components/HelpPanel.vue";
import PageHeader from "../components/PageHeader.vue";
import { useToastr } from "../lib/toastr.ts"
import {useI18n} from "vue-i18n";

export default {
  name: "OrderPaymentMethods",
  components: {
    CountrySelect,
    draggable,
    HelpPanel,
    PageHeader
  },
  setup() {
    const { filteredCountries, query } = useCountries();
    const orderingsService = useOrderingsService();
    const selectedCountry = ref(null);
    const {toastr} = useToastr()
    const { t } = useI18n();
    const hasUnsavedChanges = inject('hasUnsavedChanges', ref(false));

    const displayName = (element) => element.name === 'ideal' ? 'iDEAL | Wero' : t(`payment_methods.${element.name}`);

    const markChanged = () => {
      hasUnsavedChanges.value = true;
    };

    // Move a payment method with the up/down buttons (keyboard-friendly alternative to dragging).
    const moveItem = (index, delta) => {
      const items = orderingsService.paymentOrderings.value?.value;
      const target = index + delta;
      if (!items || target < 0 || target >= items.length) {
        return;
      }

      const [item] = items.splice(index, 1);
      items.splice(target, 0, item);
      markChanged();

      // Keep keyboard focus on the moved row so it can be moved again.
      nextTick(() => {
        const row = document.querySelector(`#app [data-order-row="${target}"]`);
        if (!row) return;
        const same = row.querySelector(`[data-move="${delta < 0 ? 'up' : 'down'}"]`);
        const other = row.querySelector(`[data-move="${delta < 0 ? 'down' : 'up'}"]`);
        (same && !same.disabled ? same : other)?.focus();
      });
    };

    const selectCountry = (country) => {
      const sameCountry = (country?.name ?? null) === (selectedCountry.value?.name ?? null);
      if (sameCountry) return;
      if (hasUnsavedChanges.value && !window.confirm(t('dashboard.common.leave_unsaved_confirm'))) {
        return;
      }
      hasUnsavedChanges.value = false;
      selectedCountry.value = country;
    };

    const warnBeforeLeaving = (event) => {
      if (hasUnsavedChanges.value) {
        event.preventDefault();
        event.returnValue = '';
      }
    };
    onMounted(() => window.addEventListener('beforeunload', warnBeforeLeaving));
    onBeforeUnmount(() => {
      window.removeEventListener('beforeunload', warnBeforeLeaving);
      hasUnsavedChanges.value = false;
    });

    watch(selectedCountry, (newVal, oldVal) => {
      orderingsService.getOrdering(
          newVal ? newVal.iso_code_2 : ''
      );
    });

    const update = () => {
      orderingsService.updateOrderings(orderingsService.paymentOrderings.value)
          .then(() => {
            if(orderingsService.data.value.status) {
              hasUnsavedChanges.value = false
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
      filteredCountries,
      hasUnsavedChanges,
      displayName,
      markChanged,
      moveItem,
      selectCountry
    }
  }
}
</script>
