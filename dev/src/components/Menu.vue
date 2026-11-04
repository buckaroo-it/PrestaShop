<template>
  <div class="flex flex-col">
    <!-- Logo; below 1280px the language picker sits next to it -->
    <div class="flex items-center justify-between px-4 pt-4 md:px-5 xl:px-6 xl:pt-7 xl:pb-5">
      <div class="xl:w-40 w-32">
        <img src="../assets/img/logo.png" alt="Buckaroo payments for PrestaShop"/>
      </div>

      <LanguageSelector class="xl:hidden" />
    </div>

    <!-- Tabs: a row of four below 1280px, a vertical list in the sidebar above -->
    <ul class="m-0 text-sm grid grid-cols-4 gap-1 p-2 md:gap-2 md:p-3 xl:px-3 xl:py-0 xl:mb-6 xl:flex xl:flex-col xl:gap-1" role="tablist">
      <li v-for="item in items" :key="item.view"
          role="tab" tabindex="0"
          :aria-selected="view === item.view"
          class="cursor-pointer rounded-lg transition-colors duration-200 p-2 md:py-2.5 xl:px-3 flex flex-col md:flex-row items-center justify-center xl:justify-start gap-1 md:gap-2 xl:gap-3 text-center xl:text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
          :class="view === item.view ? 'bg-white/10 text-white font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white'"
          @click="setView(item.view)"
          @keydown.enter.prevent="setView(item.view)"
          @keydown.space.prevent="setView(item.view)">
        <i :class="['fas', item.icon, 'md:w-5 md:text-sm text-xl text-center', view === item.view ? 'text-primary' : 'text-white/50']" aria-hidden="true"></i>
        <span class="md:text-[13px] xl:text-sm text-xs leading-tight lg:whitespace-nowrap">{{ $t(item.label) }}</span>
      </li>
    </ul>

    <!-- Language picker at the bottom of the sidebar from 1280px -->
    <div class="max-xl:hidden mt-auto mx-3 pt-3 border-t border-white/10">
      <LanguageSelector drop-up />
    </div>
  </div>
</template>

<script>
import { inject, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import LanguageSelector from "./LanguageSelector.vue"

export default {
    name: "Menu.vue",
    components: {
        LanguageSelector
    },
    setup(props, { emit }) {
        const view = inject('view')
        const app = inject('app')

        const hasUnsavedChanges = inject('hasUnsavedChanges', ref(false))
        const { t } = useI18n()

        const items = [
            { view: 'settings', icon: 'fa-cogs', label: 'dashboard.menu.settings' },
            { view: 'payment_methods', icon: 'fa-credit-card', label: 'dashboard.menu.payment_methods' },
            { view: 'verification_methods', icon: 'fa-check-circle', label: 'dashboard.menu.verification_methods' },
            { view: 'order_payment_methods', icon: 'fa-sort-numeric-up', label: 'dashboard.menu.order_payment_methods' },
        ]

        const setView = (newView) => {
            if (hasUnsavedChanges.value && !window.confirm(t('dashboard.common.leave_unsaved_confirm'))) {
                return
            }

            hasUnsavedChanges.value = false
            view.value = newView
            emit('changedView', newView)
        }

        return {
            view,
            app,
            items,
            setView
        }
    }
}
</script>
