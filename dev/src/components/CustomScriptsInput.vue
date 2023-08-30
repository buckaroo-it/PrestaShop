<template>
    <div class="py-5 space-y-5">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-sm">{{ $t('dashboard.pages.settings.custom_scripts') }}</h2>
                <div class="text-gray-400 text-xs">{{ $t('dashboard.pages.settings.custom_scripts_label') }}</div>
            </div>

            <button class="border border-green-500 rounded text-green-500 text-sm p-1 hover:bg-green-500 hover:text-white hover:shadow-lg" @click="addScript"><i class="fal fa-plus-circle"></i> {{ $t('dashboard.pages.settings.add') }}</button>
        </div>


        <div class="space-y-5">
            <div v-for="script in scripts">
                <div class="relative">
                    <input type="text" :id="`script_${ script.id }`" class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-primary peer" placeholder=" " v-model="script.path" />
                    <label :for="`script_${ script.id }`" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
                        {{ $t('dashboard.pages.settings.custom_scripts_placeholder') }}
                    </label>
                </div>

                <div class="flex justify-end">
                    <button class="text-red-500 text-sm" @click="remove(script)">{{ $t('dashboard.pages.settings.remove') }}</button>
                </div>
            </div>

        </div>
    </div>
</template>

<script>
import { ref } from 'vue'
import { v4 as uuidv4 } from 'uuid';

export default {
    name: "CustomScriptsInput",
    props: ['modelValue'],
    watch: {
        scripts(value) {
            if(value.length === 0) {
                this.addScript()
            }

            this.$emit('update:modelValue', value)
        }
    },
    setup(props) {
        const scripts = ref(props.modelValue)

        const addScript = () => {
            scripts.value.push({
                id: uuidv4(),
                path: null
            })
        }

        const remove = (script) => {
            scripts.value = scripts.value.filter(s => s.id !== script.id)
        }

        if(scripts.value.length === 0) {
            addScript()
        }

        return {
            scripts,
            addScript,
            remove
        }
    }
}
</script>

<style scoped>

</style>
