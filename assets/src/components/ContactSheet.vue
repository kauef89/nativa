<template>
  <Dialog 
    v-model:visible="isOpen" 
    modal 
    position="bottom" 
    :dismissableMask="true"
    class="!w-full !m-0 !bg-surface-900 !border-t !border-surface-700 !rounded-t-3xl"
    :pt="{
        header: { class: '!hidden' },
        content: { class: '!bg-surface-900 !p-6' },
        mask: { class: '!bg-black/80 backdrop-blur-sm' }
    }"
  >
    <div class="flex flex-col items-center gap-6 pb-6">
        <div class="w-12 h-1.5 bg-surface-700 rounded-full mb-2"></div>

        <div class="text-center">
            <h3 class="text-xl font-bold text-white">Fale Conosco</h3>
            <p class="text-surface-400 text-sm mt-1">Escolha como prefere ser atendido</p>
        </div>

        <div class="w-full space-y-3">
            <a href="https://wa.me/5547999999999" target="_blank" class="flex items-center p-4 bg-green-600 hover:bg-green-500 text-white rounded-xl shadow-lg transition-all active:scale-95 w-full">
                <i class="fa-brands fa-whatsapp text-3xl mr-4"></i>
                <div class="flex flex-col text-left">
                    <span class="font-bold text-lg">WhatsApp</span>
                    <span class="text-green-100 text-xs">Resposta rápida</span>
                </div>
                <i class="fa-solid fa-chevron-right ml-auto text-green-200"></i>
            </a>

            <a href="tel:+554734430000" class="flex items-center p-4 bg-surface-800 hover:bg-surface-700 text-white rounded-xl border border-surface-700 transition-all active:scale-95 w-full">
                <i class="fa-solid fa-phone text-2xl mr-4 text-surface-400"></i>
                <div class="flex flex-col text-left">
                    <span class="font-bold">Ligar Agora</span>
                    <span class="text-surface-400 text-xs">(47) 3443-0000</span>
                </div>
            </a>
        </div>

        <button class="text-surface-500 font-bold text-sm mt-2" @click="isOpen = false">Cancelar</button>
    </div>
  </Dialog>
</template>

<script setup>
import { ref } from 'vue';
import Dialog from 'primevue/dialog';

const isOpen = ref(false);
const open = () => isOpen.value = true;
defineExpose({ open });
</script>