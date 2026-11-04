<template>
    <div class="text-white text-sm relative">
        <div>
            <div class="inline-block hover:bg-white/10 px-2 py-1.5 cursor-pointer rounded-lg" role="button" tabindex="0" :aria-expanded="showMenu"
                 @click="showMenu = !showMenu" @keydown.enter.prevent="showMenu = !showMenu" @keydown.space.prevent="showMenu = !showMenu">
              <div v-for="({name,flag}) in filterCurrentLanguage(languages)" class="flex items-center space-x-1">
                  <img :src="'../../../../../img/flags/'+flag+'.jpg'" class="w-4 h-auto shrink-0 self-center" alt=""/>
                  <span class="text-xs">{{name}} <i class="fas text-[8px]" :class="dropUp ? 'fa-chevron-up' : 'fa-chevron-down'" aria-hidden="true"></i></span>
              </div>
            </div>
        </div>

        <Transition enter-from-class="opacity-0 translate-y-3"
                    enter-to-class="opacity-100 translate-y-0"
                    enter-active-class="transform transition ease-out duration-200"
                    leave-active-class="transform transition ease-in duration-150"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 translate-y-3">
            <ul v-if="showMenu" ref="languageMenuRef" class="bg-white text-gray-800 rounded-lg inline-block shadow-xl absolute w-40 z-10 overflow-hidden"
                :class="dropUp ? 'bottom-full mb-1 left-0' : 'mt-1 right-0'">
              <li v-for="({name,flag,code}) in filterCurrentLanguage(languages,false)" @click="changeLanguage(code)" class="p-2 flex items-center space-x-2 cursor-pointer hover:bg-gray-200">
                  <img :src="'../../../../../img/flags/'+flag+'.jpg'"  alt="" class="w-4 h-auto shrink-0 self-center"/>
                  <div>{{name}}</div>
              </li>
            </ul>
        </Transition>
    </div>
</template>

<script>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { onClickOutside } from '@vueuse/core';

export default {
  name: 'LanguageSelector',
  props: {
    // Open the list above the toggle (used at the bottom of the sidebar)
    dropUp: { type: Boolean, default: false },
  },
  setup() {
    const { locale } = useI18n();
    const showMenu = ref(false);
    const languageMenuRef = ref(null);
    const currentLanguage = ref(locale.value);
    onClickOutside(languageMenuRef, () => (showMenu.value = false));
    const languages = [
      {
        code: "en",
        name: "English",
        flag: "gb"
      },
      {
        code: "nl",
        name: "Dutch",
        flag: "nl"
      },
      {
        code: "de",
        name: "German",
        flag: "de"
      },
      {
        code: "fr",
        name: "French",
        flag: "fr"
      }
    ]
    const filterCurrentLanguage = (obj,include = true) => {
      return obj.filter((item) => {
        return include ? item.code === currentLanguage.value : item.code !== currentLanguage.value
      });
    }
    const changeLanguage = (lang) => {
      locale.value = lang;
      showMenu.value = false;
      currentLanguage.value = lang;
    };
    return {
      showMenu,
      languageMenuRef,
      currentLanguage,
      changeLanguage,
      languages,
      filterCurrentLanguage
    };
  },
};
</script>
