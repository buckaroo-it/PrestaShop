<template>
    <dialog v-if="showModal" class="block relative w-full z-50 p-0">
        <div
            class="md:p-7 flex items-center justify-center overflow-x-hidden overflow-y-auto fixed left-0 top-0 w-full h-full bg-gray-900 backdrop-blur bg-opacity-50 transition-opacity duration-300 z-50"
        >
            <div class="bg-white flex md:rounded-lg xl:w-1/4 md:w-1/2 w-full relative">
                <div class="flex flex-col items-start w-full">
                    <div class="md:p-7 p-3 flex items-start w-full">
                        <div class="space-y-2">
                            <h4 class="font-semibold text-xl text-gray-800">
                                <span v-if="mode === 'add'">{{ $t(`dashboard.pages.payments.add`) }}</span>
                                <span v-if="mode === 'edit'">{{ $t(`dashboard.pages.payments.edit`) }}</span>
                                {{ $t(`dashboard.pages.payments.custom_giftcard`) }}
                            </h4>
                            <div class="text-gray-500 font-bold text-xs">
                                {{ $t(`dashboard.pages.payments.enter_giftcard_details`) }}
                            </div>
                        </div>
                        <svg
                            class="ml-auto fill-current text-gray-700 w-5 h-5 cursor-pointer"
                            viewBox="0 0 18 18"
                            xmlns="http://www.w3.org/2000/svg"
                            @click="showModal = false"
                        >
                            <path
                                d="M14.53 4.53l-1.06-1.06L9 7.94 4.53 3.47 3.47 4.53 7.94 9l-4.47 4.47 1.06 1.06L9 10.06l4.47 4.47 1.06-1.06L10.06 9z"
                            />
                        </svg>
                    </div>

                    <div class="w-full space-y-3">
                        <div class="w-full divide-y">
                            <div class="md:px-7 md:pb-7 px-3 pb-3 space-y-5">
                                <div class="relative">
                                    <input
                                        id="giftcard_name"
                                        v-model="giftcard.name"
                                        class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-primary peer"
                                        placeholder=" "
                                        type="text"
                                    />
                                    <label
                                        class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1"
                                        for="giftcard_name"
                                    >
                                        {{ $t(`dashboard.pages.payments.giftcard_name`) }}
                                    </label>
                                </div>

                                <div class="space-y-1">
                                    <div class="relative">
                                        <input
                                            id="giftcard_service_code"
                                            v-model="giftcard.service_code"
                                            class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-primary peer"
                                            placeholder=" "
                                            type="text"
                                        />
                                        <label
                                            class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1"
                                            for="giftcard_service_code"
                                        >
                                            {{ $t(`dashboard.pages.payments.service_code`) }}
                                        </label>
                                    </div>

                                    <div
                                        class="text-gray-600 text-xs"
                                        v-html="$t(`dashboard.pages.payments.service_code_label`)"
                                    ></div>
                                </div>

                                <div class="relative">
                                    <input
                                        id="custom_giftcard_url"
                                        v-model="giftcard.logo_url"
                                        class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-primary peer"
                                        placeholder=" "
                                        type="text"
                                    />
                                    <label
                                        class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1"
                                        for="custom_giftcard_url"
                                    >
                                        {{ $t(`dashboard.pages.payments.custom_icon_url`) }}
                                    </label>
                                </div>

                                <div v-if="!loading && mode === 'add'" class="flex items-center justify-end">
                                    <button
                                        class="bg-primary font-bold text-white rounded-lg px-8 py-3 hover:shadow-lg"
                                        @click="createGiftcard"
                                    >
                                        {{ $t(`dashboard.pages.payments.add`) }}
                                    </button>
                                </div>

                                <div v-if="!loading && mode === 'edit'" class="flex items-center justify-between">
                                    <button
                                        class="bg-red-600 font-bold text-white rounded-lg px-8 py-3 hover:shadow-lg"
                                        @click="removeGiftcard"
                                    >
                                        {{ $t(`dashboard.pages.payments.remove`) }}
                                    </button>
                                    <button
                                        class="bg-primary font-bold text-white rounded-lg px-8 py-3 hover:shadow-lg"
                                        @click="editGiftcard"
                                    >
                                        {{ $t(`dashboard.pages.payments.edit`) }}
                                    </button>
                                </div>

                                <Loading v-if="loading" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </dialog>
</template>

<script>
import { ref } from 'vue';
import { useApi } from '../../lib/api';

export default {
    name: 'GiftcardModal.vue',
    setup(props, { emit }) {
        const showModal = ref(false);
        const mode = ref(null);
        const giftcard = ref(null);

        const { post, data, loading } = useApi('buckaroo_config_giftcards');
        const { post: editGiftcardPost, data: editGiftcardData } = useApi('buckaroo_edit_giftcard');
        const { post: removeGiftcardPost, data: removeGiftcardData } = useApi('buckaroo_remove_giftcard');

        const add = () => {
            mode.value = 'add';

            giftcard.value = {
                name: null,
                service_code: null,
                logo_url: null,
            };

            showModal.value = true;
        };

        const edit = editGiftcard => {
            mode.value = 'edit';

            giftcard.value = editGiftcard;

            showModal.value = true;
        };

        const createGiftcard = () => {
            if (giftcard.value.name && giftcard.value.service_code) {
                post(giftcard.value).then(() => {
                    if (data.value.status) {
                        const giftcard = data.value.custom_giftcard;
                        giftcard.isCustom = true;

                        emit('appendCustomGiftcard', giftcard);

                        showModal.value = false;
                    }
                });
            }
        };

        const editGiftcard = () => {
            if (giftcard.value.name && giftcard.value.service_code) {
                editGiftcardPost(giftcard.value).then(() => {
                    if (editGiftcardData.value.status) {
                        showModal.value = false;
                    }
                });
            }
        };

        const removeGiftcard = () => {
            removeGiftcardPost(giftcard.value).then(() => {
                if (removeGiftcardData.value.status) {
                    emit('removeCustomGiftcard', giftcard.value);

                    showModal.value = false;
                }
            });
        };

        return {
            add,
            mode,
            showModal,
            giftcard,
            loading,
            createGiftcard,
            edit,
            editGiftcard,
            removeGiftcard,
        };
    },
};
</script>
