<template>
    <div class="p-5 space-y-5">
        <div class="flex justify-between items-center">
            <div class="space-y-2">
                <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.allowed_creditcards`) }}</h2>
                <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.allowed_creditcards_label`) }}</div>
            </div>
        </div>
        <div class="relative rounded-lg border border-gray-300 max-h-96 overflow-y-auto" ref="filterRef" @click="showCards = true">
            <div class="relative">
                <input type="text" id="search-giftcard " v-model="query"
                       class="bk-no-close block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent appearance-none focus:outline-none focus:ring-0 border-none peer"
                       placeholder=" "
                />
                <label for="search-giftcard "
                       class="bk-no-close absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:top-8 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
                    {{ $t(`dashboard.pages.payments.search_creditcard`) }}
                </label>
            </div>

            <ul class="text-sm"  v-if="showCards">
                <li v-for="creditcard in filteredCreditcards" class="p-3 flex space-x-2 cursor-pointer items-center"
                    v-bind:class="{
                        'bg-primary text-white': activatedCreditcards.find(cc => cc.id === creditcard.id),
                        'hover:bg-gray-200 hover:text-gray-700': !activatedCreditcards.find(cc => cc.id === creditcard.id)
                    }"
                    @click="toggleCreditcard(creditcard)">
                    <img v-if="creditcard.icon" :src="`${baseUrl}/modules/buckaroo3/views/img/buckaroo/Creditcard issuers/SVG/${ creditcard.icon }`" class="w-4" alt="" />
                    <span class="block">{{ creditcard.name }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>

<script>
import { computed, inject, ref, watch } from 'vue';
import { useApi } from '../../lib/api';
import { onClickOutside } from '@vueuse/core'
export default {
    name: "ActiveCreditcards.vue",
    props:["modelValue"],
    methods: {
      toggleCreditcard(creditcard) {
        if (this.activatedCreditcards.find(cc => cc.id === creditcard.id)) {
          this.activatedCreditcards = this.activatedCreditcards.filter(cc => cc.id !== creditcard.id)

          return
        }

        this.activatedCreditcards.push(creditcard)
      }
    },
    watch: {
        modelValue(value) {
            this.activatedCreditcards = value
        },
        activatedCreditcards:{
            handler(value, oldValue) {
                this.$emit('update:modelValue', value)
            },
            deep: true
        }
    },
    setup(props, { emit }) {
        const query = ref('');
        const showCards = ref(false);
        const filterRef = ref(null);

        const creditcards = ref([])
        const customGiftcards = ref([])
        const activatedCreditcards = ref(props.modelValue ?? []);
        const baseUrl = inject('baseUrl');

        const { get, data } = useApi('buckaroo_config_creditcards');

        get().then(() => {
            if(data.value.status) {
                creditcards.value = data.value.creditcards
            }
        })

        onClickOutside(filterRef, () => (showCards.value = false));

        const filteredCreditcards = computed(
            () => {
                if (query.value.trim().length === 0) {
                    return creditcards.value
                }
                return creditcards.value.filter((creditcard) => creditcard.name.includes(query.value))
            }
        )
        return {
            showCards,
            filterRef,
            query,
            customGiftcards,
            filteredCreditcards,
            activatedCreditcards,
            baseUrl
        }
    }
}
</script>
