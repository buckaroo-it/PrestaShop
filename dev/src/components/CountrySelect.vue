<template>
    <div class="relative rounded-lg border border-gray-300">
        <input type="text" id="country" v-model="query" class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent appearance-none focus:outline-none focus:ring-0 focus:border-primary peer" placeholder=" " />
        <label for="country" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
            {{ $t(`dashboard.country_select.search_countries`) }}
        </label>

        <ul class="text-sm" v-if="query">
            <li v-for="country in filteredCountries" class="p-3 flex space-x-2 hover:bg-secondary hover:text-gray-700 cursor-pointer" @click="selectCountry(country)">
                <img :src="`/img/flags/${ country.icon }`" class="w-4" alt=""/>
                <span class="block">{{ $t(`countries.${ country.name }`) }}</span>
            </li>
        </ul>
    </div>
</template>

<script>
import { useCountries } from "../lib/countries";

export default {
    components:{
    },
    setup(props, { emit }) {
        const { filteredCountries, query } = useCountries()

        const selectCountry = (country) => {
            emit('update:modelValue', country)
            query.value = null
        }

        return {
            query,
            filteredCountries,
            selectCountry
        }
    }
}
</script>
