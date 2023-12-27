<template>
  <div>
    <div class="px-5 space-y-5">
      <div class="flex justify-between items-center">
        <div class="space-y-2">
          <h2 class="font-semibold text-sm">{{ $t(`dashboard.pages.payments.allowed_giftcards`) }}</h2>
          <div class="text-gray-400 text-xs">{{ $t(`dashboard.pages.payments.allowed_giftcards_label`) }}</div>
        </div>
      </div>
      <div class="relative rounded-lg border border-gray-300 max-h-96 overflow-y-auto" ref="filterRef" @click="showCards = true">
        <div class="relative">
          <input type="text" id="search-giftcard " v-model="query"
                 class="bk-no-close block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent appearance-none focus:outline-none focus:ring-0 border-none peer"
                 placeholder=" "
          />
          <label for="search-giftcard "
                 class="bk-no-close absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:top-8 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
            {{ $t(`dashboard.pages.payments.search_giftcard`) }}
          </label>
        </div>
        <ul class="text-sm"  v-show="showCards">
          <li v-for="giftcard in filteredGiftcards" class="p-3 flex space-x-2 cursor-pointer items-center"
              v-bind:class="{
                          'bg-primary text-white': activeGiftcards.includes(giftcard.id),
                          'hover:bg-gray-200 hover:text-gray-700': !activeGiftcards.includes(giftcard.id)
                      }"
              @click="toggleGiftcard(giftcard.id)">
            <img v-if="giftcard.logo" :src="`${baseUrl}/modules/buckaroo3/views/img/buckaroo/Giftcards/SVG/${ giftcard.logo }`" class="w-4" alt="" />
            <span class="block w-full">{{ giftcard.name }}</span>
            <button class="border-l-blue-500" @click.stop="selectGiftcard(giftcard)"><i class="fas fa-edit"></i></button>
            <button class="accent-red-800" @click.stop="selectGiftcard(giftcard)"><i class="fas fa-trash"></i></button>
          </li>
        </ul>
      </div>
    </div>
    <div class="px-5 space-y-5">
      <div class="space-y-2">
        <h2 class="font-semibold text-sm">Add Custom Giftcard</h2>
      </div>
      <div>
        <form @submit.prevent="checkAction" ref="formRef" action="" enctype="multipart/form-data">
          <input type="hidden" name="id" v-model="selectedGiftcard.id">
          <input type="hidden" name="action" v-model="actionType">
          <div class="flex gap-2">
            <div class="relative w-full">
              <input type="text" required name="name" id="gc_name" v-model="selectedGiftcard.name" class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-primary peer" placeholder=" " />
              <label for="gc_name" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
                Name
              </label>
            </div>
            <div class="relative w-full">
              <input type="text" required  name="code" id="gc_code" v-model="selectedGiftcard.code" class="block px-2.5 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent rounded-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-primary peer" placeholder=" " />
              <label for="gc_code" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1">
                Code
              </label>
            </div>
          </div>
          <div class="m-2">
            <label for="gc_logo" class="block">
              <img v-if="previewSrc" :src="previewSrc"  alt="" class="h-6" >
              <span v-if="!previewSrc" >Add a logo</span>
            </label>
            <input type="file" name="logo" id="gc_logo" class="p-1 hidden" @change="onFileChange">
          </div>
          <div class="flex gap-2">
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-full w-full" @click="actionType = 'add'" >
              <span>Add</span>
            </button>
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-full w-full" @click="actionType = 'update'">
              <span>Update</span>
            </button>
            <button formnovalidate class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-full w-full" @click="actionType = 'delete'">
              <span>Delete</span>
            </button>
            <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-full w-full" @click="clearSelection">
              <span>Clear</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { computed, inject, ref } from 'vue';
import { useApi } from '../../lib/api';
import { onClickOutside } from '@vueuse/core'
import { useToastr } from "@/lib/toastr";

export default {
  name: "ActiveGiftcards.vue",
  props:{
    modelValue: {
      type: Array,
      default: () => []
    }
  },
  watch: {
    modelValue: {
      immediate: true,
      handler(val) {
        this.activeGiftcards = val
      }
    }
  },
  methods: {
    toggleGiftcard(giftcardId) {
      if (this.activeGiftcards.find(id => id === giftcardId)) {
        this.activeGiftcards = this.activeGiftcards.filter(id => id !== giftcardId)
      }else {
        this.activeGiftcards.push(giftcardId)
      }
      this.$emit('update:modelValue', this.activeGiftcards)
    },
    checkAction(){
      if (!this.loading) {
        const data = new FormData(this.formRef)
        if(!this.newLogo){
          data.set('logo', this.selectedGiftcard.logo)
        }
        switch (data.get('action')) {
          case 'add':
            return this.action(data).then(() => {
              if(this.data.status){
                this.giftcards.push(this.data.giftcard)
                this.selectedGiftcard = this.data.giftcard
              }
            })
          case 'update':
            return this.action(data).then(() => {
              if(this.data.status) {
                let index = this.giftcards.findIndex(giftcard => giftcard.id === this.data.giftcard.id)
                this.giftcards[index] = this.data.giftcard
              }
            })
          case 'delete':
            return this.action(data).then(() => {
              if(this.data.status){
                this.giftcards = this.giftcards.filter(giftcard => giftcard.id !== this.selectedGiftcard.id)
                this.clearSelection()
              }
            })
        }
      }
    },
    action(data) {
      return this.post(data).then(() => {
        if (this.data.status) {
          this.toastr.success(this.data.message)
        }
      }).catch(() => {
        this.toastr.error(this.errorMessage ?? 'Something went wrong...')
      })
    },
    selectGiftcard(giftcard) {
      this.showCards = false
      this.newLogo = ''
      this.selectedGiftcard = JSON.parse(JSON.stringify(giftcard))
    },
    onFileChange(e) {
      const file = e.target.files[0];
      if(file){
        this.newLogo = URL.createObjectURL(file);
      }else{
        this.newLogo = ''
      }
    },
    clearSelection(){
      this.selectedGiftcard = {
        id: null,
        name: null,
        code: null,
        logo: ''
      }
      this.newLogo = ''
    }
  },
  setup(props) {
    const query = ref('');
    const showCards = ref(false);
    const filterRef = ref(null);
    const newLogo = ref('');
    const giftcards = ref([]);
    const formRef = ref(null);
    const actionType = ref('');

    const selectedGiftcard = ref({
      id: null,
      name: null,
      code: null,
      logo: ''
    });

    const previewSrc = computed(() => {
      if(newLogo.value){
        return newLogo.value
      }
      if (selectedGiftcard.value.logo) {
        return `${baseUrl}/modules/buckaroo3/views/img/buckaroo/Giftcards/SVG/${ selectedGiftcard.value.logo }`
      }
      return null
    })
    const { toastr } = useToastr()
    const activeGiftcards = ref(props.modelValue);
    const baseUrl = inject('baseUrl');
    const { get , data , post , loading , errorMessage } = useApi('buckaroo_config_giftcards');

    get().then(() => {
      if(data.value.status) {
        giftcards.value = data.value.giftcards
      }
    })

    onClickOutside(filterRef, () => (showCards.value = false));

    const filteredGiftcards = computed(
        () => {
          if (query.value.trim().length === 0) {
            return giftcards.value
          }
          return giftcards.value.filter((giftcard) => giftcard.name.includes(query.value))
        }
    )
    return {
      showCards,
      filterRef,
      query,
      giftcards,
      filteredGiftcards,
      baseUrl,
      data,
      post,
      activeGiftcards,
      toastr,
      selectedGiftcard,
      newLogo,
      previewSrc,
      loading,
      formRef,
      actionType,
      errorMessage
    }
  }
}
</script>
