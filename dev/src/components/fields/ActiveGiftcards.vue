<template>
    <div class="p-5 space-y-5">
        <div class="flex justify-between items-center">
            <div class="space-y-2">
                <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.allowed_giftcards`) }}</h2>
                <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.allowed_giftcards_label`) }}</div>
            </div>

            <button class="border border-blue-500 rounded text-blue-500 text-sm p-1 hover:bg-blue-500 hover:text-white hover:shadow-lg capitalize" @click="$refs.giftCardModal.add()">
                <i class="fal fa-plus-circle"></i> {{ $t(`dashboard.pages.payments.add_custom_giftcard`) }}
            </button>
        </div>
        <div class="relative rounded-lg border border-gray-300 max-h-96 overflow-y-auto">
            <div class="relative">
                <input type="text" id="search-giftcard" v-model="query"
                       class="bk-no-close block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent appearance-none focus:outline-none focus:ring-0 focus:border-primary peer"
                       placeholder=" " />
                <label for="search-giftcard "
                       class="bk-no-close absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:top-8 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
                    {{ $t(`dashboard.pages.payments.search_giftcard`) }}
                </label>
            </div>

            <ul class="text-sm">
                <li v-for="giftcard in filteredGiftcards" class="p-3 flex space-x-2 cursor-pointer justify-between"
                    v-bind:class="{
                        'bg-primary text-white': (giftcard.isCustom && activatedGiftcards.customGiftcards && activatedGiftcards.customGiftcards.find(cG => cG.id === giftcard.id)) || (!giftcard.isCustom && activatedGiftcards.giftcards && activatedGiftcards.giftcards.find(g => g.id === giftcard.id)),
                        'hover:bg-gray-200 hover:text-gray-700': (giftcard.isCustom && activatedGiftcards.customGiftcards && !activatedGiftcards.customGiftcards.find(cG => cG.id === giftcard.id)) || (!giftcard.isCustom && activatedGiftcards.giftcards && !activatedGiftcards.giftcards.find(g => g.id === giftcard.id))
                    }"
                    @click="toggleGiftcard(giftcard)">

                    <div class="flex space-x-2">
                        <img v-if="giftcard.logo_url" :src="giftcard.logo_url" class="w-4" />
                        <span class="block">{{ giftcard.name }}</span>
                    </div>


                    <button @click.stop.prevent="$refs.giftCardModal.edit(giftcard)" class="border border-green-500 hover:bg-green-500 text-green-500 hover:text-white py-1 px-2 rounded" v-if="giftcard.isCustom"><i class="far fa-edit"></i></button>

                </li>
            </ul>
        </div>
    </div>

    <GiftcardModal ref="giftCardModal" @appendCustomGiftcard="appendCustomGiftcard" @removeCustomGiftcard="removeCustomGiftcard"  />
</template>

<script>
import { computed, ref, watch } from 'vue';
import { useApi } from '../../lib/api';
import GiftcardModal from './GiftcardModal.vue'

export default {
    name: "ActiveGiftcards.vue",
    props:["modelValue"],
    components: {
        GiftcardModal
    },
    watch: {
        modelValue(value) {
            this.activatedGiftcards = value
        },
        activatedGiftcards:{
            handler(value, oldValue) {
                this.$emit('update:modelValue', value)
            },
            deep: true
        }
    },
    setup(props, { emit }) {
        const query = ref('');
        const giftcards = ref([])
        const customGiftcards = ref([])
        const activatedGiftcards = ref(props.modelValue ?? []);

        const { get, data } = useApi('giftcards');

        get().then(() => {
            if(data.value.status) {
                giftcards.value = data.value.giftcards.map((giftcard) => {
                  const { get, data } = useApi('/index.php?fc=module&module=buckaroo3&controller=giftcards');
                    giftcard.giftcard = false

                    return giftcard
                })

                customGiftcards.value = data.value.custom_giftcards.map((giftcard) => {
                    giftcard.isCustom = true

                    return giftcard
                })
            }
        })

        const filteredGiftcards = computed(
            () => {
                if (query.value.trim().length === 0) {
                    return giftcards.value.concat(customGiftcards.value);
                }

                return giftcards.value.filter(giftcardHasName(query.value)).concat(customGiftcards.value.filter(giftcardHasName(query.value)))
            }
        )

        const giftcardHasName = (partialName) => {
            return (giftcard) => giftcard.name.toLowerCase().includes(partialName.toLowerCase())
        }

        const appendCustomGiftcard = (customGiftcard) => {
            customGiftcards.value.push(customGiftcard)
        }

        const removeCustomGiftcard = (customGiftcard) => {
            customGiftcards.value = customGiftcards.value.filter(giftcard => customGiftcard.id !== giftcard.id)
        }

        const toggleGiftcard = (giftcard) => {
            const customGiftcards = activatedGiftcards.value.customGiftcards ?? []
            const giftcards = activatedGiftcards.value.giftcards ?? []

            if(giftcard.isCustom) {
                if(customGiftcards.find(cg => cg.id === giftcard.id)) {
                    activatedGiftcards.value.customGiftcards = customGiftcards.filter(cg => cg.id !== giftcard.id)

                    return
                }

                customGiftcards.push(giftcard)
            }

            if(!giftcard.isCustom) {
                if (giftcards.find(g => g.id === giftcard.id)) {
                    activatedGiftcards.value.giftcards = giftcards.filter(g => g.id !== giftcard.id)

                    return
                }

                giftcards.push(giftcard)
            }

            activatedGiftcards.value = {
                customGiftcards: customGiftcards,
                giftcards: giftcards
            }
        }

        return {
            query,
            customGiftcards,
            filteredGiftcards,
            toggleGiftcard,
            activatedGiftcards,
            appendCustomGiftcard,
            removeCustomGiftcard
        }
    }
}
</script>

<style scoped>

</style>
