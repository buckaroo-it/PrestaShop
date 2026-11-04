<template>
  <div class="px-5 py-4 border-b flex flex-col xl:flex-row xl:items-center xl:justify-between gap-3">
    <div class="min-w-0">
      <h1 class="m-0 font-bold md:text-xl text-lg">{{ title }}</h1>
      <div v-if="subtitle" class="text-gray-400 md:text-sm text-xs">{{ subtitle }}</div>
    </div>

    <div v-if="$slots.actions" class="flex flex-col sm:flex-row sm:items-center gap-2">
      <slot name="actions"/>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PageHeader',
  props: {
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
  },
}
</script>
