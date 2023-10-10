<template>
  <div class="md:border-l-2 md:border-primary space-y-5">
    <div>
      <div class="md:px-8 md:py-5 p-5 md:w-52 w-36">
        <img src="../../../views/img/logo.png" />
      </div>

      <LanguageSelector />
    </div>

    <ul class="text-sm space-y-2 flex md:flex-col">
      <li class="text-white md:p-5 p-2 cursor-pointer transition ease-in-out duration-300 flex-1 md:text-left text-left space-y-2 md:block md:space-x-1 flex flex-col justify-center" @click="setView('settings')" v-bind:class="{
                'bg-sixthly text-white': view === 'settings',
                'hover:bg-seventhly text-eightly': view !== 'settings',
            }">
        <i class="fas fa-cogs md:text-base text-xl"></i> <span class="md:inline block md:text-sm text-xs">{{$t(`dashboard.menu.settings`)}}</span>
      </li>
      <li class="text-white md:p-5 p-2 cursor-pointer transition ease-in-out duration-300 flex-1 md:text-left text-left space-y-2 md:block md:space-x-1 flex flex-col justify-center" @click="setView('payment_methods')" v-bind:class="{
                'bg-sixthly text-white': view === 'payment_methods',
                'hover:bg-seventhly text-eightly': view !== 'payment_methods',
            }">
        <i class="fas fa-credit-card md:text-base text-xl"></i> <span class="md:inline block md:text-sm text-xs">{{$t(`dashboard.menu.payment_methods`)}}}</span>
      </li>
      <li class="text-white md:p-5 p-2 cursor-pointer transition ease-in-out duration-300 flex-1 md:text-left text-left space-y-2 md:block md:space-x-1 flex flex-col justify-center" @click="setView('order_payment_methods')" v-bind:class="{
                'bg-sixthly text-white': view === 'order_payment_methods',
                'hover:bg-seventhly text-eightly': view !== 'order_payment_methods',
            }">
        <i class="fas fa-sort-numeric-up md:text-base text-xl"></i> <span class="md:inline block md:text-sm text-xs">{{ $t(`dashboard.pages.order_payment_methods.order_payment_methods`) }}</span>
      </li>
    </ul>
  </div>
</template>

<script>
import { inject } from 'vue'
import LanguageSelector from "./LanguageSelector.vue"

export default {
    name: "Menu.vue",
    components: {
        LanguageSelector
    },
    setup(props, { emit }) {
        const view = inject('view')
        const app = inject('app')

        const setView = (newView) => {
            view.value = newView
            emit('changedView', newView)
        }

        return {
            view,
            app,
            setView
        }
    }
}
</script>

<style scoped>

</style>
