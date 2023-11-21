<template>
    <div>
        <div class="space-y-2">
            <div class="flex justify-between">
                <slot></slot>
                <div class="grid items-center">
                    <label :for="id" class="inline-flex relative items-center cursor-pointer">
                        <input type="checkbox" :value="true" :id="id" class="sr-only peer" v-model="selected">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-secondary rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary"></div>
                    </label>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref } from 'vue'

export default {
    name: "EnableClientSide.vue",
    props: ['modelValue', 'id'],
    watch: {
        selected(curr) {
            this.$emit('update:modelValue', curr);
        },
        modelValue(state) {
            this.selected = state
        }
    },
    setup(props) {
        const selected = ref(props.modelValue)

        return {
            selected
        }
    }
}
</script>
