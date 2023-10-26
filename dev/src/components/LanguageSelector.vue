<template>
    <div class="md:px-6 w-full text-white text-sm relative">
        <div>
            <div class="inline-block hover:bg-sixthly p-2 cursor-pointer rounded-lg" @click="showMenu = !showMenu">
                <div v-if="currentLanguage === 'en'" class="flex space-x-1">
                    <img src="../../../../../img/flags/gb.jpg" class="w-4"/>
                    <span class="text-xs">English <i class="fas fa-chevron-down text-[8px]"></i></span>
                </div>

                <div v-if="currentLanguage === 'nl'" class="flex space-x-1">
                    <img src="../../../../../img/flags/nl.jpg" class="w-4"/>
                    <span class="text-xs">Nederlands <i class="fas fa-chevron-down text-[8px]"></i></span>
                </div>
            </div>
        </div>

        <Transition enter-from-class="opacity-0 translate-y-3"
                    enter-to-class="opacity-100 translate-y-0"
                    enter-active-class="transform transition ease-out duration-200"
                    leave-active-class="transform transition ease-in duration-150"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 translate-y-3">
            <ul v-if="showMenu" ref="languageMenuRef" class="bg-white text-gray-800 rounded-lg inline-block shadow-xl mt-1 absolute w-1/2 overflow-hidden">
                <li v-if="currentLanguage !== 'nl'" @click="changeLanguage('nl')" class="p-2 flex space-x-2 cursor-pointer hover:bg-gray-200"><img src="../../../../../img/flags/nl.jpg" class="w-4"/> <div>Nederlands</div></li>
                <li v-if="currentLanguage !== 'en'" @click="changeLanguage('en')" class="p-2 flex space-x-2 cursor-pointer hover:bg-gray-200"><img src="../../../../../img/flags/gb.jpg" class="w-4"/> <div>English</div></li>

                <!--                <li class="p-2 flex space-x-2 cursor-pointer hover:bg-gray-200"><img src="/images/flags/germany.svg" class="w-4"/> <div>German</div></li>-->
<!--                <li class="p-2 flex space-x-2 cursor-pointer hover:bg-gray-200"><img src="/images/flags/france.svg" class="w-4"/> <div>French</div></li>-->
<!--                <li class="p-2 flex space-x-2 cursor-pointer hover:bg-gray-200"><img src="/images/flags/spain.svg" class="w-4"/> <div>Spanish</div></li>-->
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
  setup() {
    const { locale } = useI18n();
    const showMenu = ref(false);
    const languageMenuRef = ref(null);
    const currentLanguage = ref(locale.value);

    onClickOutside(languageMenuRef, () => (showMenu.value = false));

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
    };
  },
};
</script>
