import { ref } from "vue";
import { useApi } from "./api";

export const useOrderingsService = () => {
    const { get, post, data } = useApi(`index.php?fc=module&module=buckaroo3&controller=orderings`);
    const paymentOrderings = ref(null);

    const getOrdering = (countryCode: string) => {
        return get({ country: countryCode }).then(() => {
            if(data.value.status) {
                paymentOrderings.value = data.value.orderings;
            }
        });
    };

    const updateOrderings = (orderings: any) => {
        return post(orderings);
    };

    return {
        getOrdering,
        updateOrderings,
        paymentOrderings,
        data
    };
};
