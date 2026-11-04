<template>
  <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 text-xs font-semibold" role="group" :aria-label="label">
    <button v-for="option in options" :key="option.value" type="button"
            class="px-3 py-1.5 rounded-md flex items-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-fifthly"
            :class="modelValue === option.value ? 'bg-primary text-fifthly shadow' : 'text-gray-500 hover:text-fifthly'"
            :aria-pressed="modelValue === option.value"
            @click="$emit('update:modelValue', option.value)">
      <i v-if="modelValue === option.value" class="fas fa-check text-xs" aria-hidden="true"></i>
      <span>{{ option.label }}</span>
    </button>
  </div>
</template>

<script>
/**
 * Pill-style choice between a few options; same look as the payment fee mode
 * selector on the Settings page. Use with v-model.
 */
export default {
  name: 'SegmentedControl',
  props: {
    modelValue: { type: [String, Number], default: null },
    options: { type: Array, required: true }, // [{ value, label }]
    label: { type: String, default: '' },
  },
  emits: ['update:modelValue'],
}
</script>
