<template>
  <aside :class="collapsed ? variant.collapsed : variant.open" :aria-label="title">
    <div class="relative flex items-center justify-between gap-2">
      <h2 class="m-0 font-bold text-base leading-snug" :class="collapsed ? variant.hideTitle : 'pr-7'">{{ title }}</h2>

      <button type="button"
              class="shrink-0 flex items-center justify-center rounded-full text-gray-500 hover:bg-gray-200 hover:text-fifthly focus:outline-none focus-visible:ring-2 focus-visible:ring-fifthly"
              :class="collapsed ? 'w-9 h-9' : 'absolute -top-2 -right-2 w-8 h-8'"
              :aria-expanded="!collapsed"
              :title="collapsed ? $t('dashboard.common.show_help') : $t('dashboard.common.hide_help')"
              @click="toggle">
        <i class="fas" :class="collapsed ? 'fa-question-circle text-lg' : 'fa-times'" aria-hidden="true"></i>
        <span class="sr-only">{{ collapsed ? $t('dashboard.common.show_help') : $t('dashboard.common.hide_help') }}</span>
      </button>
    </div>

    <div v-show="!collapsed" class="space-y-5">
      <slot/>
    </div>
  </aside>
</template>

<script>
import { ref } from 'vue'

const STORAGE_KEY = 'buckaroo3.helpPanelCollapsed'

// Tailwind needs complete class names, so each breakpoint gets its own literal set.
const VARIANTS = {
  xl: {
    open: 'xl:w-64 w-full xl:mt-0 mt-4 bg-thirdly p-5 space-y-5 text-gray-600 shadow rounded-2xl xl:shrink-0 xl:self-start xl:sticky xl:top-[141px] xl:h-[calc(100vh-157px)] xl:overflow-y-auto',
    collapsed: 'xl:w-auto w-full xl:mt-0 mt-4 bg-thirdly xl:p-2 p-5 text-gray-600 shadow rounded-2xl xl:shrink-0 xl:self-start xl:sticky xl:top-[141px]',
    hideTitle: 'xl:hidden',
  },
  '2xl': {
    open: '2xl:w-64 w-full 2xl:mt-0 mt-4 bg-thirdly p-5 space-y-5 text-gray-600 shadow rounded-2xl 2xl:shrink-0 2xl:self-start 2xl:sticky 2xl:top-[141px] 2xl:h-[calc(100vh-157px)] 2xl:overflow-y-auto',
    collapsed: '2xl:w-auto w-full 2xl:mt-0 mt-4 bg-thirdly 2xl:p-2 p-5 text-gray-600 shadow rounded-2xl 2xl:shrink-0 2xl:self-start 2xl:sticky 2xl:top-[141px]',
    hideTitle: '2xl:hidden',
  },
  lg: {
    open: 'lg:w-1/5 w-full lg:mt-0 mt-4 bg-thirdly p-5 space-y-5 text-gray-600 shadow rounded-2xl lg:shrink-0 lg:self-start lg:sticky lg:top-[141px] lg:h-[calc(100vh-157px)] lg:overflow-y-auto',
    collapsed: 'lg:w-auto w-full lg:mt-0 mt-4 bg-thirdly lg:p-2 p-5 text-gray-600 shadow rounded-2xl lg:shrink-0 lg:self-start lg:sticky lg:top-[141px]',
    hideTitle: 'lg:hidden',
  },
  md: {
    open: 'md:w-1/5 w-full md:mt-0 mt-4 bg-thirdly p-5 space-y-5 text-gray-600 shadow rounded-2xl md:shrink-0 md:self-start md:sticky md:top-[141px] md:h-[calc(100vh-157px)] md:overflow-y-auto',
    collapsed: 'md:w-auto w-full md:mt-0 mt-4 bg-thirdly md:p-2 p-5 text-gray-600 shadow rounded-2xl md:shrink-0 md:self-start md:sticky md:top-[141px]',
    hideTitle: 'md:hidden',
  },
}

const readCollapsed = () => {
  try {
    return window.localStorage.getItem(STORAGE_KEY) === '1'
  } catch (e) {
    return false
  }
}

export default {
  name: 'HelpPanel',
  props: {
    title: { type: String, required: true },
    // Breakpoint at which the panel sits beside the content: 'md', 'lg', 'xl' or '2xl'
    breakpoint: { type: String, default: 'lg' },
  },
  setup(props) {
    const collapsed = ref(readCollapsed())
    const variant = VARIANTS[props.breakpoint] || VARIANTS.lg

    const toggle = () => {
      collapsed.value = !collapsed.value
      try {
        window.localStorage.setItem(STORAGE_KEY, collapsed.value ? '1' : '0')
      } catch (e) {
        // Storage can be unavailable (private mode); the panel still works for this visit.
      }
    }

    return { collapsed, variant, toggle }
  },
}
</script>
