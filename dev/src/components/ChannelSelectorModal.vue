<template>
    <dialog class="block relative w-full z-50 p-0" v-if="showModal && app">
        <div class="md:p-7 flex justify-center items-start overflow-x-hidden overflow-y-auto fixed left-0 top-0 w-full h-full bg-gray-900 backdrop-blur bg-opacity-50 transition-opacity duration-300 z-50">
            <div class="bg-white flex md:rounded-lg xl:w-1/4 md:w-1/2 w-full relative">
                <div class="flex flex-col items-start w-full">
                    <div class="md:p-7 p-3 flex items-start w-full">
                        <div class="space-y-2">
                            <h4 class="font-semibold text-xl text-gray-800">{{ $t('dashboard.channel_selector.select_channel') }}</h4>
                            <div class="text-gray-500 font-bold text-xs">{{ $t('dashboard.channel_selector.select_channel_label') }}</div>
                        </div>
                    </div>

                    <div class="w-full space-y-3">
                        <div class="w-full divide-y">
                            <div class="md:px-7 md:pb-7 px-3 pb-3 space-y-5">
                                <ul class="rounded-xl border divide-y overflow-hidden">
                                    <li v-for="channel in app.channels" class="p-3 flex items-center hover:bg-gray-100 cursor-pointer space-x-2 justify-between" @click="selectChannel(channel)">
                                        <div>
                                            <div class="flex items-center space-x-1">
                                                <h3>{{ channel.name }}</h3>
                                                <i v-if="channel.is_enabled" class="fas fa-check-circle text-xs text-green-800"></i>
                                                <i v-else class="fas fa-times-circle text-xs text-red-800"></i>
                                            </div>

                                            <div class="text-xs text-gray-500">{{ channel.url }}</div>
                                        </div>

                                        <div>
                                            <i class="fas fa-chevron-right text-xs text-gray-600"></i>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </dialog>
</template>

<script>
import {inject, ref, watch} from "vue";
import { useApi } from "../lib/api";

export default {
    name: "ChannelSelectorModal",
    setup(props, { emit }) {
        const showModal = ref(false)
        const app = inject('app')
        const selectedChannel = inject('selectedChannel')

        const { post, data } = useApi('app/select-channel')

        const selectChannel = (channel) => {
            selectedChannel.value = channel
            showModal.value = false
        }

        watch(app, (appValue) => {
            if(!selectedChannel.value && appValue.channels.length > 1) {
                showModal.value = true
            }

            if(appValue.channels.length === 1) {
                selectedChannel.value = appValue.channels[0];
            }

        })

        return {
            app,
            showModal,
            selectedChannel,
            selectChannel
        }
    }
}
</script>

<style scoped>

</style>
