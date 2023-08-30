import { useApi } from "./api";
import {computed, ref} from 'vue'

const { get, data } = useApi('countries')

const countries = ref([])
const showAllCountries = ref(false)

export const usePaymentCountryConfig = () => {
    const addAllCountries = () => {
        get().then(() => {
            if(data.value.status) {
                data.value.countries.forEach((country) => {
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

    const setCountries = (setCountries) => {
        countries.value = setCountries
    }

    const removeCountry = (country) => {
        countries.value = countries.value.filter(ec => ec.id !== country.id)
    }

    const removeAllCountries = () => {
        countries.value = []
    }

    const enabledCountries = computed(() => {
        if(countries.value) {
            if(countries.value.length > 10 && !showAllCountries.value) {
                return countries.value.slice(0, 10)
            }
        }

        return countries.value ?? []
    })

    return {
        countries,
        showAllCountries,
        addAllCountries,
        setCountries,
        enabledCountries,
        removeCountry,
        removeAllCountries
    }
}
