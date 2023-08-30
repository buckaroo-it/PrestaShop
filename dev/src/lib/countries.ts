import {computed, ref} from "vue";
import {useApi} from "./api";

const countries = ref([])
const query = ref(null)



export const useCountries = () => {

    const { get, data } = useApi('/index.php?fc=module&module=buckaroo3&controller=countries')

    get().then(() => {
        if(data.value.status) {
            countries.value = data.value.countries
        }
    })

    const filteredCountries = computed(() => {
            if (!query.value || query.value.trim().length === 0) {
                return countries.value
            }

            return countries.value.filter((country) => {
                return country.name.toLowerCase().includes(query.value.toLowerCase()) || (`countries.${ country.name }`).toLowerCase().includes(query.value.toLowerCase())
            })
        }
    )

    return {
        query,
        filteredCountries,
        countries
    }
}
