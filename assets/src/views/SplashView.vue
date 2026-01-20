<template>
  <div class="h-full w-full relative overflow-hidden flex flex-col items-center justify-between p-8 pb-12">
    <div class="mt-16 animate-fade-in-down">
        <img src="@/svg/logo-branca.svg" alt="Nativa" class="h-16 w-auto drop-shadow-2xl" />
    </div>

    <div v-if="isLoading" class="absolute inset-0 flex items-center justify-center z-30">
            <i class="fa-solid fa-circle-notch fa-spin text-white/50 text-3xl"></i>
    </div>

    <div class="w-full max-w-xs animate-fade-in-up flex flex-col items-center">
        <button 
            class="w-full h-14 bg-white/10 backdrop-blur-md border border-white/20 text-white font-black text-lg rounded-full shadow-[0_0_30px_rgba(0,0,0,0.3)] hover:bg-white/20 hover:scale-105 active:scale-95 transition-all flex items-center justify-center gap-3 uppercase tracking-wide mb-8 group"
            @click="$router.push('/home')"
        >
            Iniciar <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
        </button>

        <div class="flex items-center justify-center gap-5 w-full">
            <div v-if="!isLoading">
                <div v-if="isOpen" class="px-3 py-1 rounded-full bg-green-500/20 backdrop-blur-md border border-green-500/30 text-green-100 font-bold text-[10px] uppercase tracking-widest shadow-lg flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse"></span> Aberto
                </div>
                <div v-else class="px-3 py-1 rounded-full bg-red-500/20 backdrop-blur-md border border-red-500/30 text-red-100 font-bold text-[10px] uppercase tracking-widest shadow-lg flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span> Fechado
                </div>
            </div>

            <div class="w-px h-4 bg-white/20"></div>

            <button class="text-white/60 hover:text-white transition-colors text-[10px] font-bold uppercase tracking-widest flex items-center gap-1.5 py-1" @click="$router.push('/pdv')">
                <i class="fa-solid fa-lock text-[10px]"></i> Staff
            </button>
        </div>
        
        <p class="text-white/30 text-[9px] text-center mt-6 font-medium tracking-widest">&copy; 2024 Pastelaria Nativa</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useSettingsStore } from '@/stores/settings-store';

const settingsStore = useSettingsStore();
const isLoading = ref(true);
const isOpen = computed(() => settingsStore.status.general);

onMounted(async () => {
    // Apenas garante que o status esteja carregado, a imagem já foi definida no App.vue
    if (!settingsStore.status.general && !settingsStore.status.delivery) {
       await settingsStore.checkRealtimeStatus();
    }
    setTimeout(() => { isLoading.value = false; }, 300);
});
</script>