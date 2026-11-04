<template>
  <div class="space-y-3">
    <div class="flex flex-wrap justify-between items-start gap-2">
      <div>
        <div role="heading" aria-level="3" class="font-semibold text-sm">
          {{ $t(`dashboard.pages.payments.countries`) }}
          <span v-if="selectedCount" class="ml-1 text-xs font-normal text-gray-400">{{ $t(`dashboard.pages.payments.countries_selected`, { count: selectedCount }) }}</span>
        </div>
        <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.countries_label`) }}</div>
      </div>

      <div class="flex items-center gap-1 text-xs font-semibold">
        <button type="button" class="px-2 py-1 rounded-md text-fifthly hover:bg-gray-100" @click="selectAll">{{ $t(`dashboard.pages.payments.select_all_countries`) }}</button>
        <button type="button" class="px-2 py-1 rounded-md text-gray-500 hover:bg-gray-100 hover:text-red-600 disabled:opacity-40 disabled:cursor-not-allowed"
                :disabled="!selectedCount" @click="clear">{{ $t(`dashboard.pages.payments.clear`) }}</button>
      </div>
    </div>

    <CountrySelect v-model="picked" />

    <div v-if="!selectedCount" class="flex items-center gap-2 text-xs text-gray-500">
      <i class="fas fa-globe-europe" aria-hidden="true"></i>
      {{ $t(`dashboard.pages.payments.countries_all_hint`) }}
    </div>

    <ul v-else class="flex flex-wrap gap-2 text-sm m-0 p-0">
      <li v-for="country in visibleCountries" :key="country.id" class="flex items-center gap-2 rounded-full border border-gray-200 bg-gray-50 pl-3 pr-1 py-1">
        <img :src="`/img/flags/${ country.icon }`" class="w-4 h-auto shrink-0" alt="" />
        <span>{{ country.name }}</span>
        <button type="button" class="w-6 h-6 rounded-full flex items-center justify-center text-gray-400 hover:bg-gray-200 hover:text-red-600"
                :aria-label="`${$t('dashboard.pages.payments.remove')}: ${country.name}`"
                @click="remove(country)">
          <i class="fas fa-times text-xs" aria-hidden="true"></i>
        </button>
      </li>

      <li v-if="selectedCount > 10">
        <button type="button" @click="showAll = !showAll" class="rounded-full border border-gray-200 px-3 py-1.5 text-xs font-semibold text-fifthly hover:bg-gray-100">
          <span v-if="!showAll">{{ $t(`dashboard.pages.payments.show_country_number_and_more`, { country_number: (selectedCount - 10) }) }}</span>
          <span v-else>{{ $t(`dashboard.pages.payments.show_less`) }}</span>
        </button>
      </li>
    </ul>
  </div>
</template>

<script>
import { ref, computed, watch } from 'vue'
import CountrySelect from './CountrySelect.vue'
import { useCountries } from '../lib/countries'

/**
 * Pick the countries a payment or verification method is shown in.
 * v-model is the list of selected country objects; an empty list means all countries.
 */
export default {
  name: 'CountryAvailability',
  components: { CountrySelect },
  props: {
    modelValue: { type: Array, default: () => [] },
  },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    const { countries } = useCountries()
    const picked = ref(null)
    const showAll = ref(false)

    const selected = computed(() => props.modelValue ?? [])
    const selectedCount = computed(() => selected.value.length)
    const visibleCountries = computed(() =>
      selectedCount.value > 10 && !showAll.value ? selected.value.slice(0, 10) : selected.value
    )

    // Adding a country from the search; ignore ones that are already selected
    watch(picked, (country) => {
      if (country && !selected.value.some(c => c.id === country.id)) {
        emit('update:modelValue', [...selected.value, country])
      }
      picked.value = null
    })

    const selectAll = () => emit('update:modelValue', [...countries.value])
    const clear = () => emit('update:modelValue', [])
    const remove = (country) => emit('update:modelValue', selected.value.filter(c => c.id !== country.id))

    return { picked, showAll, selectedCount, visibleCountries, selectAll, clear, remove }
  },
}
</script>
